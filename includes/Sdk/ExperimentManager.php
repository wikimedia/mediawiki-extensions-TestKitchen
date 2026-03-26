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
		// Get experiment configs from Test Kitchen UI.
		$experiments = $this->configsFetcher->getExperimentConfigs();
		$enrolledExperiments = $this->enrollmentResult['enrolled'] ?? [];

		if (
			isset( $experiments[ $experimentName ] ) &&
			$experiments[$experimentName]['user_identifier_type'] === 'mw-user' &&
			!in_array( $experimentName, $enrolledExperiments, true )
		) {
			// For logged-in experiments we know whether the experiment is active, but the current user
			// is not enrolled in it
			$this->logger->info( 'The current user is not enrolled in ' .
				'the ' . $experimentName . ' experiment' );
			return new UnenrolledExperiment(
				$this->eventSender,
				$this->eventFactory,
				$this->statsFactory,
				$this->streamConfigs,
				$this->exposureLogTracker
			);
		} else {
			if ( !in_array( $experimentName, $enrolledExperiments, true ) ) {
				return new UnenrolledExperiment(
					$this->eventSender,
					$this->eventFactory,
					$this->statsFactory,
					$this->streamConfigs,
					$this->exposureLogTracker
				);
			}
		}

		// Combine experiment enrollments from Test Kitchen Coordination into the user's
		// experiment config from the Test Kitchen UI into a single experiment config.
		$experimentConfig = $this->getExperimentConfig( $experimentName, $experiments );

		// The experiment enrolment has been overridden
		if ( $experimentConfig['coordinator'] === 'forced' ) {
			return new OverriddenExperiment(
				$this->eventSender,
				$this->eventFactory,
				$this->statsFactory,
				$this->streamConfigs,
				$this->logger,
				$this->exposureLogTracker,
				$experimentConfig
			);
		}

		return new Experiment(
			$this->eventSender,
			$this->eventFactory,
			$this->statsFactory,
			$this->streamConfigs,
			$this->exposureLogTracker,
			$experimentConfig
		);
	}

	/**
	 * Get the current user's experiment enrollment details.
	 *
	 * Stitches together the user's experiment configs and enrollment results.
	 * 1. Get experiment configs from Test Kitchen UI.
	 * 2. Get experiment enrollments from Test Kitchen Coordination.
	 *
	 * @param string $experimentName
	 * @param array $experiments
	 * @return array
	 */
	private function getExperimentConfig( string $experimentName, array $experiments ): array {
		// Until we pass schema ids in the Test Kitchen API response, we will default to web base.
		// In the interim, experiment owners can set schema id with Experiment::setSchema().
		$schemaID = $experiments[ $experimentName ]['schema_id'] ?? self::BASE_SCHEMA_ID;

		// If the experiment is overridden, other keys will be overridden.
		$isOverridden = in_array( $experimentName, $this->enrollmentResult['overrides'] );
		$samplingUnit = $isOverridden ?
			self::OVERRIDDEN_EXPERIMENT_SAMPLING_UNIT :
			$experiments[ $experimentName ]['user_identifier_type'];

		return [
			'enrolled' => $experimentName,
			'assigned' => $this->enrollmentResult['assigned'][ $experimentName ],
			'subject_id' => $this->enrollmentResult['subject_ids'][ $experimentName ],
			'sampling_unit' => $samplingUnit,
			'coordinator' => $isOverridden ? 'forced' : 'default',
			'stream_name' => $isOverridden ? self::BASE_STREAM : $experiments[ $experimentName ][ 'stream_name' ],
			'schema_id' => $schemaID,
			'contextual_attributes' => $isOverridden ? [] : $experiments[ $experimentName ]['contextual_attributes']
		];
	}

	public function getEnrollments(): EnrollmentResultBuilder {
		return $this->enrollments;
	}
}
