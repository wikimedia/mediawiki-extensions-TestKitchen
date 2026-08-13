/**
 * @classdesc Sends analytics events to the event intake service immediately using
 *  [the Beacon API][0].
 *
 *  Previously events were added to a queue which drained after 5 seconds, when the document
 *  was hidden, or when the document was unloaded. This made it difficult to reason about
 *  when events were submitted and contributed to event loss (see T384687), so events
 *  are now sent as soon as they are received.
 *
 *  [0]: https://developer.mozilla.org/en-US/docs/Web/API/Beacon_API
 *
 * @class InternalEventSender
 * @hideconstructor
 * @singleton
 * @memberof mw.testKitchen
 *
 * @package
 */
module.exports = {

	/**
	 * Sends the event to the URL using [the Beacon API][0].
	 *
	 * [0]: https://developer.mozilla.org/en-US/docs/Web/API/Beacon_API
	 *
	 * @method sendEvent
	 * @instance
	 * @memberof mw.testKitchen.InternalEventSender
	 *
	 * @param {Object} event
	 * @param {string} url
	 */
	sendEvent( event, url ) {
		try {
			navigator.sendBeacon( url, JSON.stringify( [ event ] ) );
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
};
