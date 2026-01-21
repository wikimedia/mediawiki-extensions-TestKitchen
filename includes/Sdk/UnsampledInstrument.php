<?php

namespace MediaWiki\Extension\TestKitchen\Sdk;

/**
 * Represents a non-existing Instrument or an existing one that is not in sample
 */
class UnsampledInstrument extends Instrument {

	public function __construct(
		private readonly EventSender $eventSender,
		private readonly EventFactory $eventFactory,
		private array $instrumentConfig
	) {
		parent::__construct(
			$this->eventSender,
			$this->eventFactory,
			$instrumentConfig
		);
	}

	/**
	 * @inheritDoc
	 */
	public function send( string $action, ?array $interactionData = [] ): void {
	}

	/**
	 * @inheritDoc
	 */
	public function setSchema( string $schemaID ): Instrument {
		return $this;
	}
}
