<?php

namespace MediaWiki\Extension\TestKitchen\Sdk;

use MediaWiki\Extension\EventLogging\EventSubmitter\EventSubmitter;
use Wikimedia\Stats\StatsFactory;

/**
 * Represents an enrollment experiment for the current user
 */
class Experiment implements ExperimentInterface {

	public function __construct(
		private readonly EventSubmitter $eventSubmitter,
		private readonly EventFactory $eventFactory,
		private readonly StatsFactory $statsFactory,
		protected readonly array $experimentConfig
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function getAssignedGroup(): ?string {
		return $this->experimentConfig['assigned'] ?? null;
	}

	/**
	 * @inheritDoc
	 */
	public function isAssignedGroup( ...$groups ): bool {
		return in_array( $this->getAssignedGroup(), $groups, true );
	}

	/**
	 * @inheritDoc
	 */
	public function send( string $action, ?array $interactionData = null ): void {
		// Only submit the event if experiment details exist and are valid.
		if ( $this->isEnrolled() ) {
			$experimentConfig = $this->experimentConfig;

			$streamName = $experimentConfig['stream_name'];
			$schemaID = $experimentConfig['schema_id'];
			$contextualAttributes = $experimentConfig['contextual_attributes'];

			// Extract SDK-specific experiment config
			$keys = [ 'enrolled', 'assigned', 'subject_id', 'sampling_unit', 'coordinator' ];
			$experiment = array_intersect_key( $experimentConfig, array_fill_keys( $keys, true ) );

			$interactionData = array_merge(
				$interactionData ?? [],
				[
					'experiment' => $experiment,
				]
			);

			$event = $this->eventFactory->newEvent(
				$schemaID,
				$contextualAttributes,
				$action,
				$interactionData
			);

			$this->eventSubmitter->submit( $streamName, $event );

			// Increment the total number of events sent for each experiment (T401706).
			$this->incrementExperimentEventsSentTotal( $this->experimentConfig['enrolled'] );
		}
	}

	/**
	 * Get the config for the experiment.
	 *
	 * @return array|null
	 */
	public function getExperimentConfig(): ?array {
		return $this->experimentConfig;
	}

	/**
	 * Checks if the user is enrolled in an experiment group.
	 *
	 * @return bool
	 */
	private function isEnrolled(): bool {
		return $this->getAssignedGroup() !== null;
	}

	/**
	 * Increment experiment send event counts.
	 *
	 * @param string $experimentName
	 */
	private function incrementExperimentEventsSentTotal( string $experimentName ): void {
		$this->statsFactory->withComponent( 'TestKitchen' )
			->getCounter( 'experiment_events_sent_total' )
			->setLabel( 'experiment', $experimentName )
			->increment();
	}
}
