const DRAIN_QUEUE_DELAY = 5000; // 5 (s)

/**
 * @type {Map<string,Object[]>}
 *
 * @ignore
 */
let queues = new Map();

let isDocumentUnloading = false;
let drainQueueTimeout = null;

function doSendEvents( events, url ) {
	try {
		navigator.sendBeacon( url, JSON.stringify( events ) );
	} catch ( e ) {
		// Some browsers throw when sending a beacon to a blocked URL (by an adblocker, for
		// example). Some browser extensions remove Navigator#sendBeacon() altogether. See also:
		//
		// 1. https://phabricator.wikimedia.org/T86680
		// 2. https://phabricator.wikimedia.org/T273374
		// 3. https://phabricator.wikimedia.org/T308311
		//
		// Regardless, ignore all errors for now.
	}
}
function drainQueue() {
	queues.forEach( doSendEvents );

	queues = new Map();
	drainQueueTimeout = null;
}

function onPageHide() {
	isDocumentUnloading = true;

	drainQueue();
}

function onPageShow() {
	isDocumentUnloading = false;
}

function onVisibilityChange( documentHidden ) {
	if ( documentHidden ) {
		drainQueue();
	}
}

/**
 * @classdesc This class and supporting code is the same as
 *  [repos/data-engineering/metrics-platform/js/src/DefaultEventSubmitter.js][0]. That class was
 *  written and maintained by the authors of this extension.
 *
 *  [0]: https://gitlab.wikimedia.org/repos/data-engineering/metrics-platform/-/blob/759ce7203ad50776d1e29b1c0979ef3bb50c6a33/js/src/DefaultEventSubmitter.js
 *
 * @class EventSender
 * @implements {mw.testKitchen.EventSenderInterface}
 * @hideconstructor
 * @singleton
 * @memberof mw.testKitchen
 *
 * @borrows mw.testKitchen.EventSenderInterface#sendEvent as #sendEvent
 *
 * @package
 */
module.exports = {
	sendEvent( event, url ) {
		if ( isDocumentUnloading ) {
			doSendEvents( [ event ], url );

			return;
		}

		if ( !queues.has( url ) ) {
			queues.set( url, [ event ] );
		} else {
			queues.get( url ).push( event );
		}

		if ( !drainQueueTimeout ) {
			drainQueueTimeout = setTimeout( drainQueue, DRAIN_QUEUE_DELAY );
		}
	}
};

if ( window.QUnit ) {
	module.exports = Object.assign( module.exports, {
		onPageHide,
		onPageShow,
		onVisibilityChange,

		/**
		 * @ignore
		 */
		reset() {
			queues = new Map();
			isDocumentUnloading = false;

			if ( drainQueueTimeout ) {
				clearTimeout( drainQueueTimeout );

				drainQueueTimeout = null;
			}
		}
	} );
} else {
	window.addEventListener( 'pagehide', onPageHide );
	window.addEventListener( 'pageshow', onPageShow );
	document.addEventListener(
		'visibilitychange',
		() => onVisibilityChange( document.hidden )
	);
}
