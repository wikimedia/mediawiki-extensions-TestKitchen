<?php

namespace MediaWiki\Extension\TestKitchen\Sdk;

use MediaWiki\Deferred\DeferredUpdates;

/**
 * @internal
 */
abstract class BaseEventSender {
	private array $events;
	private bool $isCliMode;
	private bool $callableUpdateAdded;

	public function __construct() {
		$this->events = [];
		$this->isCliMode = wfIsCLI();
		$this->callableUpdateAdded = false;
	}

	/**
	 * Sends the event.
	 *
	 * Typically, events will be added to an internal queue, which will get sent in a single post-send update. However,
	 * if PHP is running in CLI mode, then the event will be sent immediately.
	 *
	 * @param array $event
	 */
	public function sendEvent( array $event ): void {
		if ( $this->isCliMode ) {
			$this->doSendEvents( [ $event ] );

			return;
		}

		$this->events[] = $event;

		if ( !$this->callableUpdateAdded ) {
			DeferredUpdates::addCallableUpdate( function () {
				$this->doSendEvents( $this->events );
				$this->events = [];
				$this->callableUpdateAdded = false;
			} );

			$this->callableUpdateAdded = true;
		}
	}

	abstract protected function doSendEvents( array $events ): void;
}
