<?php

namespace MediaWiki\Extension\TestKitchen\Sdk;

use MediaWiki\Extension\EventLogging\EventSubmitter\EventSubmitter;

/**
 * Represents a non-existing Instrument or an existing one that is not in sample
 */
class UnsampledInstrument extends Instrument {

	public function __construct(
		private readonly EventSubmitter $eventSubmitter,
		private readonly EventFactory $eventFactory,
		private array $instrumentConfig
	) {
		parent::__construct(
			$this->eventSubmitter,
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
