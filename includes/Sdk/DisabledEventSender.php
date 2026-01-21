<?php

namespace MediaWiki\Extension\TestKitchen\Sdk;

/**
 * This event sender will not send events. Importantly, it doesn't have a dependency on EventBus and so can be used
 * in unit and integration tests when side effects could cause failures.
 *
 * @internal
 */
class DisabledEventSender extends EventSender {
	public function __construct() {
	}

	public function sendEvent( array $event ): void {
	}
}
