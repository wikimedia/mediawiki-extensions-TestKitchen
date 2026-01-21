<?php

namespace MediaWiki\Extension\TestKitchen\Sdk;

/**
 * Represents an Instrument that is in sample
 */
class Instrument implements InstrumentInterface {

	private int $eventSequencePosition;

	public function __construct(
		private readonly EventSender $eventSender,
		private readonly EventFactory $eventFactory,
		private array $instrumentConfig
	) {
		$this->eventSequencePosition = 1;
	}

	/**
	 * @inheritDoc
	 */
	public function send( string $action, ?array $interactionData = [] ): void {
		$instrumentName = $this->instrumentConfig['name'];
		$streamName = $this->instrumentConfig['stream_name'];
		$schemaID = $this->instrumentConfig['schema_id'];
		$contextualAttributes = $this->instrumentConfig['contextual_attributes'];

		$interactionData = array_merge(
			$interactionData ?? [],
			[
				'instrument_name' => $instrumentName,
				'funnel_event_sequence_position' => $this->eventSequencePosition++,
			]
		);

		$event = $this->eventFactory->newEvent(
			$streamName,
			$schemaID,
			$contextualAttributes,
			$action,
			$interactionData
		);

		$this->eventSender->sendEvent( $event );
	}

	/**
	 * @inheritDoc
	 */
	public function setSchema( string $schemaID ): InstrumentInterface {
		$this->instrumentConfig['schema_id'] = $schemaID;
		return $this;
	}

	/**
	 * Get the config for the instrument.
	 *
	 * @return array
	 */
	public function getConfig(): array {
		return $this->instrumentConfig;
	}
}
