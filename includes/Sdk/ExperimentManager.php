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

	private EnrollmentResultBuilder $enrollments;
	private array $enrollmentResult;

	private array $baseStreamContextualAttributes;
	private StreamConfigs $streamConfigs;

	public function __construct(
		private readonly LoggerInterface $logger,
		private readonly EventSender $eventSender,
		private readonly EventFactory $eventFactory,
		private readonly StatsFactory $statsFactory,
		private readonly RequestEnrollmentsProcessor $requestEnrollmentsProcessor,
		private readonly EnrollmentsProcessor $enrollmentsProcessor,
		private readonly CentralIdLookup $centralIdLookup,
		private readonly ConfigsFetcher $configsFetcher,
		StreamConfigs $staticStreamConfigs
	) {
		$this->enrollmentResult = [];
		$this->streamConfigs = $staticStreamConfigs;
		$this->baseStreamContextualAttributes =
			$staticStreamConfigs->getContextualAttributesForStream( self::BASE_STREAM );

		$this->enrollments = new EnrollmentResultBuilder();
		$this->enrollmentResult = [];
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
		$this->enrollments = $this->requestEnrollmentsProcessor->process( $request );

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

		// B/C
		$this->enrollmentResult = $this->enrollments->build();
	}

	/**
	 * Get the current user's experiment object.
	 *
	 * @param string $experimentName
	 * @return Experiment
	 */
	public function getExperiment( string $experimentName ): Experiment {
		$activeExperiments = $this->enrollmentResult['active_experiments'] ?? [];
		$enrolledExperiments = $this->enrollmentResult['enrolled'] ?? [];

		if (
			in_array( $experimentName, $activeExperiments, true ) &&
			$this->enrollmentResult['sampling_units'][ $experimentName ] === 'mw-user' &&
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
				$this->streamConfigs
			);
		} else {
			if ( !in_array( $experimentName, $enrolledExperiments, true ) ) {
				return new UnenrolledExperiment(
					$this->eventSender,
					$this->eventFactory,
					$this->statsFactory,
					$this->streamConfigs
				);
			}
		}

		$experimentConfig = $this->getExperimentConfig( $experimentName );

		// The experiment enrolment has been overridden
		if ( $experimentConfig['coordinator'] === 'forced' ) {
			return new OverriddenExperiment(
				$this->eventSender,
				$this->eventFactory,
				$this->statsFactory,
				$this->streamConfigs,
				$this->logger,
				$experimentConfig
			);
		}

		return new Experiment(
			$this->eventSender,
			$this->eventFactory,
			$this->statsFactory,
			$this->streamConfigs,
			$experimentConfig
		);
	}

	/**
	 * Get the current user's experiment enrollment details.
	 *
	 * @param string $experimentName
	 */
	private function getExperimentConfig( string $experimentName ): array {
		return [
			'enrolled' => $experimentName,
			'assigned' => $this->enrollmentResult['assigned'][ $experimentName ],
			'subject_id' => $this->enrollmentResult['subject_ids'][ $experimentName ],
			'sampling_unit' => $this->enrollmentResult['sampling_units'][ $experimentName ],
			'coordinator' => $this->enrollmentResult['coordinator'][ $experimentName ],
			'stream_name' => self::BASE_STREAM,
			'schema_id' => self::BASE_SCHEMA_ID,
			'contextual_attributes' => $this->baseStreamContextualAttributes,
		];
	}

	public function getEnrollments(): EnrollmentResultBuilder {
		return $this->enrollments;
	}
}
