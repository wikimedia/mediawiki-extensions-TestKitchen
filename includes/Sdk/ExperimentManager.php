<?php

namespace MediaWiki\Extension\TestKitchen\Sdk;

use MediaWiki\Extension\TestKitchen\ConfigsFetcher;
use MediaWiki\Extension\TestKitchen\Coordination\EnrollmentResultBuilder;
use MediaWiki\Extension\TestKitchen\Coordination\EnrollmentsProcessor;
use MediaWiki\Extension\TestKitchen\Coordination\RequestEnrollmentsProcessor;
use MediaWiki\Request\WebRequest;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerInterface;
use Wikimedia\Assert\Assert;
use Wikimedia\Stats\StatsFactory;

class ExperimentManager implements
	ExperimentManagerInterface,
	ExperimentCoordinatorInterface
{
	private const BASE_STREAM = 'product_metrics.web_base';
	private const BASE_SCHEMA_ID = '/analytics/product_metrics/web/base/2.0.0';

	// The experiment.sampling_unit field can be one of "mw-user", "edge-unique", or "session" but, because overridden
	// experiments cannot send events, for clarity we can set "overridden" as the value.
	private const OVERRIDDEN_EXPERIMENT_SAMPLING_UNIT = 'overridden';

	private const COORDINATOR_FORCED = 'forced';
	private const COORDINATOR_DEFAULT = 'default';

	private EnrollmentResultBuilder $enrollments;
	private array $enrollmentResult;

	private array $baseStreamContextualAttributes;
	private StreamConfigs $streamConfigs;
	private array $streamNameToContextualAttributesMap;
	private ExposureLogTracker $exposureLogTracker;

	public function __construct(
		private readonly LoggerInterface $logger,
		private readonly EventSender $eventSender,
		private readonly EventFactory $eventFactory,
		private readonly StatsFactory $statsFactory,
		private readonly RequestEnrollmentsProcessor $requestEnrollmentsProcessor,
		private readonly EnrollmentsProcessor $enrollmentsProcessor,
		private readonly CentralIdLookup $centralIdLookup,
		private readonly ConfigsFetcher $configsFetcher,
		StreamConfigs $staticStreamConfigs,
		ExposureLogTracker $exposureLogTracker
	) {
		$this->enrollmentResult = [];
		$this->streamConfigs = $staticStreamConfigs;
		$this->baseStreamContextualAttributes =
			$staticStreamConfigs->getContextualAttributesForStream( self::BASE_STREAM );

		$this->enrollments = new EnrollmentResultBuilder();
		$this->enrollmentResult = [];
		$this->streamNameToContextualAttributesMap = [];
		$this->exposureLogTracker = $exposureLogTracker;
	}

	/**
	 * This method SHOULD NOT be called by code outside the TestKitchen extension (or the Test Kitchen codebase). As an
	 * interim solution, GrowthExperiments uses it on account creation until T405074 is resolved.
	 *
	 * Don't use this unless you've spoken with Experiment Platform team.
	 *
	 * @param array $enrollmentResult
	 *
	 * @deprecated Use {@link ExperimentCoordinatorInterface::updateUser() or
	 *  {@link ExperimentCoordinatorInterface::updateIdentifier()} instead
	 */
	public function initialize( array $enrollmentResult ): void {
		$this->enrollmentResult = $enrollmentResult;
	}

	public function setRequest( WebRequest $request ): void {
		$this->enrollments = $this->requestEnrollmentsProcessor->process( $request, $this->enrollments );

		// B/C
		$this->enrollmentResult = $this->enrollments->build();
	}

	public function updateUser( UserIdentity $user, bool $lookupCentralID = true ): void {
		if ( !$user->isRegistered() ) {
			return;
		}

		if ( $lookupCentralID ) {

			// CentralIdLookup::centralIdFromName() does not require to local account to be attached to a central
			// account, so it should reliably return the correct central ID.
			$identifier = $this->centralIdLookup->centralIdFromName( $user->getName() );
		} else {
			$identifier = $user->getId();
		}

		$this->updateIdentifier( self::IDENTIFIER_TYPE_MW_USER, (string)$identifier );
	}

	public function updateIdentifier( string $identifierType, string $identifier ): void {
		Assert::parameter(
			$identifierType === self::IDENTIFIER_TYPE_MW_USER,
			'$identifierType',
			'The identifier type must be "mw-user"'
		);

		$this->enrollmentsProcessor->process(
			$identifierType,
			$identifier,
			$this->configsFetcher->getExperimentConfigs(),
			$this->enrollments
		);

		$this->enrollmentResult = $this->enrollments->build();
	}

	/**
	 * @inheritDoc
	 */
	public function getExperiment( string $experimentName ): ExperimentInterface {
		$overriddenExperiments = $this->enrollmentResult['overrides'] ?? [];
		$isOverridden = in_array( $experimentName, $overriddenExperiments );

		if ( $isOverridden ) {
			return $this->newOverriddenExperiment( $experimentName );
		}

		// Get experiment configs from Test Kitchen UI.
		$experiments = $this->configsFetcher->getExperimentConfigs();

		// The experiment enrollment hasn't been overridden and we don't have a config for it? Treat the user as
		// unenrolled.
		//
		// However, in the case of everyone experiments, this could indicate that the everyone experiments enrollment
		// authority config has drifted from the config fetched via ConfigsFetcher. Increment a counter so
		// that this can be monitored.
		if ( !isset( $experiments[ $experimentName ] ) ) {
			$this->statsFactory->withComponent( 'TestKitchen' )
				->getCounter( 'experiment_unknown' )
				->setLabel( 'experiment', $experimentName )
				->increment();

			return $this->newUnenrolledExperiment();
		}

		$enrolledExperiments = $this->enrollmentResult['enrolled'] ?? [];

		if ( !in_array( $experimentName, $enrolledExperiments, true ) ) {
			if ( $experiments[$experimentName]['user_identifier_type'] === 'mw-user' ) {
				// For logged-in experiments we know whether the experiment is active, but the current user is not
				// enrolled in it.
				$this->logger->info( 'The current user is not enrolled in the ' . $experimentName . ' experiment' );
			}

			return $this->newUnenrolledExperiment();
		}

		$experimentConfig = $experiments[ $experimentName ];

		$this->statsFactory->withComponent( 'TestKitchen' )
			->getCounter( 'experiment_known' )
			->setLabel( 'experiment', $experimentName )
			->increment();

		return $this->newExperiment( $experimentName, $experimentConfig );
	}

	public function getEnrollments(): EnrollmentResultBuilder {
		return $this->enrollments;
	}

	private function newUnenrolledExperiment(): UnenrolledExperiment {
		// TODO: In the JS SDK, UnenrolledExperiment and OverriddenExperiment don't inherit from Experiment because of
		//  the large number of dependencies. Do the same in the PHP SDK.
		return new UnenrolledExperiment(
			$this->eventSender,
			$this->eventFactory,
			$this->statsFactory,
			$this->streamConfigs,
			$this->exposureLogTracker
		);
	}

	private function newOverriddenExperiment( string $experimentName ): OverriddenExperiment {
		return new OverriddenExperiment(
			$this->eventSender,
			$this->eventFactory,
			$this->statsFactory,
			$this->streamConfigs,
			$this->logger,
			$this->exposureLogTracker,
			[
				'enrolled' => $experimentName,
				'assigned' => $this->enrollmentResult['assigned'][ $experimentName ],
				'subject_id' => $this->enrollmentResult['subject_ids'][ $experimentName ],
				'sampling_unit' => self::OVERRIDDEN_EXPERIMENT_SAMPLING_UNIT,
				'coordinator' => self::COORDINATOR_FORCED,
				'stream_name' => self::BASE_STREAM,
				'schema_id' => self::BASE_SCHEMA_ID,
				'contextual_attributes' => [],
			]
		);
	}

	private function newExperiment( string $experimentName, array $experimentConfig ): Experiment {
		// Until we pass schema IDs in the Test Kitchen API response, we will default to web base.
		// In the interim, experiment owners can set schema id with Experiment::setSchema().
		$schemaID = $experimentConfig['schema_id'] ?? self::BASE_SCHEMA_ID;

		$contextualAttributes = $experimentConfig['contextual_attributes'] ?? [];

		return new Experiment(
			$this->eventSender,
			$this->eventFactory,
			$this->statsFactory,
			$this->streamConfigs,
			$this->exposureLogTracker,
			[
				'enrolled' => $experimentName,
				'assigned' => $this->enrollmentResult['assigned'][ $experimentName ],
				'subject_id' => $this->enrollmentResult['subject_ids'][ $experimentName ],
				'sampling_unit' => $experimentConfig['user_identifier_type'],
				'coordinator' => self::COORDINATOR_DEFAULT,
				'stream_name' => $experimentConfig[ 'stream_name' ],
				'schema_id' => $schemaID,
				'contextual_attributes' => $contextualAttributes,
			]
		);
	}
}
