<?php

namespace MediaWiki\Extension\TestKitchen\Sdk;

use MediaWiki\Extension\EventBus\EventBus;

/**
 * Sends events using the EventBus MediaWiki extension.
 *
 * @internal
 */
class EventSender extends BaseEventSender {
	public function __construct(
		private readonly EventBus $eventBus
	) {
		parent::__construct();
	}

	protected function doSendEvents( array $events ): void {
		$this->eventBus->send( $events );
	}
}
