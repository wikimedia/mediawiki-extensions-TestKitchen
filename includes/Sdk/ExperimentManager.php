<?php

namespace MediaWiki\Extension\TestKitchen\Sdk;

use Psr\Log\LoggerInterface;
use Wikimedia\Stats\StatsFactory;

class ExperimentManager implements ExperimentManagerInterface {

	private const BASE_STREAM = 'product_metrics.web_base';
	private const BASE_SCHEMA_ID = '/analytics/product_metrics/web/base/2.0.0';

	private array $enrollmentResult;
	private array $baseStreamContextualAttributes;
	private StreamConfigs $streamConfigs;

	public function __construct(
		private readonly LoggerInterface $logger,
		private readonly EventSender $eventSender,
		private readonly EventFactory $eventFactory,
		private readonly StatsFactory $statsFactory,
		StreamConfigs $staticStreamConfigs
	) {
		$this->enrollmentResult = [];
		$this->streamConfigs = $staticStreamConfigs;
		$this->baseStreamContextualAttributes =
			$staticStreamConfigs->getContextualAttributesForStream( self::BASE_STREAM );
	}

	/**
	 * This method SHOULD NOT be called by code outside the TestKitchen extension (or the Test Kitchen codebase). As an
	 * interim solution GrowthExperiments uses it on account creation until T405074 is resolved.
	 *
	 * Don't use this unless you've spoken with Experiment Platform team.
	 *
	 * @param array $enrollmentResult
	 */
	public function initialize( array $enrollmentResult ): void {
		$this->enrollmentResult = $enrollmentResult;
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
}
