<?php

namespace MediaWiki\Extension\TestKitchen\Sdk;

use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Extension\EventBus\EventBus;

/**
 * @internal
 */
class EventSender {
	private array $events;
	private bool $isCliMode;
	private bool $callableUpdateAdded;

	public function __construct(
		private readonly EventBus $eventBus
	) {
		$this->events = [];
		$this->isCliMode = wfIsCLI();
		$this->callableUpdateAdded = false;
	}

	/**
	 * Sends the event via EventBus.
	 *
	 * Typically, events will be added to an internal queue, which will get sent in a single post-send update. However,
	 * if PHP is running in CLI mode, then the event will be sent immediately.
	 *
	 * @param array $event
	 */
	public function sendEvent( array $event ): void {
		if ( $this->isCliMode ) {
			$this->eventBus->send( [ $event ] );

			return;
		}

		$this->events[] = $event;

		if ( !$this->callableUpdateAdded ) {
			DeferredUpdates::addCallableUpdate( function () {
				$this->eventBus->send( $this->events );
				$this->events = [];
				$this->callableUpdateAdded = false;
			} );

			$this->callableUpdateAdded = true;
		}
	}
}
