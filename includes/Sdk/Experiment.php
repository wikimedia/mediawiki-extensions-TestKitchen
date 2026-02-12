<?php

namespace MediaWiki\Extension\TestKitchen\Sdk;

use Wikimedia\Stats\StatsFactory;

/**
 * Represents an enrollment experiment for the current user
 */
class Experiment implements ExperimentInterface {

	private const EXPOSURE_CONTEXTUAL_ATTRIBUTES = [
		'performer_is_logged_in',
		'performer_is_temp',
		'performer_is_bot',
		'mediawiki_database'
	];

	protected array $experimentConfig;

	public function __construct(
		private readonly EventSender $eventSender,
		private readonly EventFactory $eventFactory,
		private readonly StatsFactory $statsFactory,
		private readonly StreamConfigs $staticStreamConfigs,
		array $experimentConfig
	) {
		$this->experimentConfig = $experimentConfig;
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
	public function send( string $action,
						  array $interactionData = [],
						  array $contextualAttributes = [] ): void {
		// Only submit the event if experiment details exist and are valid.
		if ( $this->isEnrolled() ) {
			$experimentConfig = $this->experimentConfig;

			$streamName = $experimentConfig['stream_name'];
			$schemaID = $experimentConfig['schema_id'];
			$eventContextualAttributes = $experimentConfig['contextual_attributes'];
			// When per-event contextual attributes are present, those not already included will be added to the event
			if ( $contextualAttributes !== [] ) {
				$eventContextualAttributes = array_unique(
					array_merge(
						$eventContextualAttributes,
						$contextualAttributes
					)
				);
			}

			// Extract SDK-specific experiment config
			$keys = [ 'enrolled', 'assigned', 'subject_id', 'sampling_unit', 'coordinator' ];
			$experiment = array_intersect_key( $experimentConfig, array_fill_keys( $keys, true ) );

			$interactionData = array_merge(
				$interactionData,
				[
					'experiment' => $experiment,
				]
			);

			$event = $this->eventFactory->newEvent(
				$streamName,
				$schemaID,
				$eventContextualAttributes,
				$action,
				$interactionData
			);

			$this->eventSender->sendEvent( $event );

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
	 * Sets the stream and its corresponding contextual attributes to send analytics events.
	 *
	 * @param string $streamName
	 * @return $this
	 */
	public function setStream( string $streamName ): self {
		$this->experimentConfig['stream_name'] = $streamName;
		$this->experimentConfig['contextual_attributes'] =
			$this->staticStreamConfigs->getContextualAttributesForStream( $streamName );
		return $this;
	}

	/**
	 * Sets the ID of the schema used to validate analytics events sent.
	 *
	 * @param string $schemaId
	 * @return $this
	 */
	public function setSchema( string $schemaId ): self {
		$this->experimentConfig['schema_id'] = $schemaId;
		return $this;
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

	/**
	 * @inheritDoc
	 */
	public function sendExposure(): void {
		if ( !$this->isEnrolled() ) {
			return;
		}

		$this->send( 'experiment_exposure', contextualAttributes: self::EXPOSURE_CONTEXTUAL_ATTRIBUTES );
	}
}
