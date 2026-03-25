const SHORT_TTL = 5 * 60 * 1000; // 5 minutes in milliseconds
const LONG_TTL = 24 * 60 * 60 * 1000; // 24 hours in milliseconds
const THRESHOLD = 10;
const moduleConfig = require( './config.json' );
// The epoch of the last exposure logging reset, `globalResetEpoch` is a coarse
// invalidation mechanism for exposure memory.
//
// Any stored exposure with a timestamp (`ts`) earlier than this value is treated
// as stale and ignored on read. This enables a "global reset" without actively
// clearing browser storage.
//
// This complements `exposure_version`, which handles per-experiment invalidation.
const globalResetEpoch = moduleConfig.exposureResetEpoch ?
	moduleConfig.exposureResetEpoch * 1000 :
	Date.now() - ( 60 * 60 * 1000 );
/**
 * @typedef {Object} LogEntry
 * @property {number} expires_at
 * @property {number} count
 *
 * @ignore
 */
/**
 * @return {LogEntry}
 *
 * @ignore
 */
function newSession() {
	return {
		expires_at: Date.now() - ( 60 * 60 * 1000 ),
		count: 0
	};
}
/**
 * Manages experiment exposure deduplication across two client-side tiers.
 *
 * Tier 1 (in-memory):
 * - Scope: current page view
 * - Prevents duplicate sends within a single page load
 * - Acts as the "floor" guarantee (at most once per page)
 *
 * Tier 2 (sessionStorage):
 * - Scope: current browser tab/session
 * - Reduces repeated sends across navigations
 * - Uses TTL + global reset + versioned keys for invalidation
 *
 * Together, these tiers ensure:
 * - No more than one exposure per page view
 * - Best-effort suppression of repeated exposures within a session
 *
 * Note: PHP also provides a per-request Tier 1 guard server-side.
 *
 * @class ExposureLogTracker
 * @memberof mw.testKitchen
 * @package
 */
class ExposureLogTracker {
	constructor() {
		this.exposuresThisPage = new Set();
	}

	/**
	 * Retrieve and validate session-scoped exposure data for a given key.
	 *
	 * This method is responsible for validating application-level freshness only.
	 *
	 * - Expiry (TTL) is handled by `mw.storage.session`. If the entry is expired,
	 *   `get()` will return `null` and no additional checks are needed here.
	 * - This method only enforces the global reset epoch which invalidates
	 *   previously recorded exposures when experiment configuration changes.
	 *
	 * If the stored data is malformed or predates the reset epoch, it is treated
	 * as invalid and removed.
	 *
	 * @param {string} key Storage key for the exposure entry
	 * @param {number} resetEpoch Unix timestamp (seconds) representing the
	 *  earliest valid exposure time
	 * @return {LogEntry} Parsed log entry if valid; otherwise `null`
	 *
	 * @ignore
	 */
	getValidSessionData( key, resetEpoch ) {
		// Tier 2: session storage lookup.
		// If the entry is expired or missing, storage returns null.
		const raw = mw.storage.session.get( key );
		if ( !raw ) {
			return newSession();
		}
		try {
			const entry = JSON.parse( raw );
			// If the stored exposure predates the reset epoch, invalidate it.
			if ( entry.ts < resetEpoch ) {
				// Remove stale data so future lookups don't re-parse it.
				mw.storage.session.remove( key );
				return newSession();
			}
			// Entry is structurally valid and passes reset checks.
			return entry;
		} catch ( e ) {
			// Malformed JSON (e.g., manual corruption or partial writes).
			// Treat as invalid and ignore.
			return newSession();
		}
	}

	/**
	 * Persist an exposure in both deduplication tiers.
	 *
	 * Tier 1 (in-memory) prevents duplicate sends within the current page view.
	 * Tier 2 (sessionStorage) reduces repeat sends across navigations in the same tab.
	 *
	 * The stored entry includes:
	 * - `count`: number of times this exposure has been attempted
	 * - `ts`: write timestamp (seconds), used for global reset invalidation
	 *
	 * TTL strategy:
	 * - Short TTL for early exposures to allow quick retries (e.g., after failures)
	 * - Long TTL after a threshold to suppress repeated sends for stable exposures
	 *
	 * @param {string} key Stable deduplication key
	 * @param {LogEntry} LogEntry
	 *
	 * @ignore
	 */
	addLog( key, LogEntry ) {
		const count = LogEntry.count + 1;
		// Use shorter TTL for early attempts, longer TTL once exposure stabilizes
		const ttl = ( count <= THRESHOLD ) ? SHORT_TTL : LONG_TTL;
		// Update Tier 1 Memory (Immediate): mark as seen for this page view
		this.exposuresThisPage.add( key );
		// Update Tier 2 Session Storage: persist exposure state across navigations (per tab)
		mw.storage.session.set(
			key,
			JSON.stringify( {
				expires_at: Date.now() + ttl,
				count
			} )
		);
	}

	/**
	 * Build a stable exposure key for Tier 1 + Tier 2 deduplication.
	 *
	 * @param {Object} params
	 * @param {string} params.enrolled
	 * @param {string} params.assigned
	 * @param {string} params.version
	 * @return {string}
	 */
	makeKey( { enrolled, assigned, version } ) {
		const exposureVersion = version !== undefined && version !== null ?
			version :
			'v0';
		return `tk_exposure.${ enrolled }:${ assigned }:${ exposureVersion }`;
	}

	/**
	 * Attempts to send an experiment exposure event while enforcing exposure
	 * deduplication across two tiers of storage.
	 *
	 * Tier 1 uses in-memory store to prevent sending the same exposure more than
	 * once per page view.
	 * Tier 2 uses session storage to reduce repeated exposure
	 * events across navigations within the same tab using TTL-based storage.
	 *
	 * Execution flow:
	 *
	 * 1. If the key exists in Tier 1, do nothing.
	 * 2. If valid session data exists (Tier 2), hydrate Tier 1 and do nothing.
	 * 3. Otherwise:
	 *    - Invoke `sendFn`
	 *    - On success: record exposure in both tiers
	 *    - On failure: Tier 1 has already been populated; error is propagated
	 *
	 * This guarantees:
	 * - No duplicate sends per page view
	 * - Best-effort suppression across navigations
	 * - No retry loops on failure within the same page
	 *
	 * @param {string} key Stable deduplication key for the exposure event
	 * @param {Function} sendFn Callback that performs the actual send
	 * @param {Object} sendFn.logState
	 * @param {number} sendFn.logState.count
	 */
	trySend( key, sendFn ) {
		// Tier 1: already sent during this page view so no-op
		if ( this.exposuresThisPage.has( key ) ) {
			return;
		}
		// Tier 2: check session storage for prior exposure
		const sessionData = this.getValidSessionData( key, globalResetEpoch );
		// Populate Tier 1 so future checks are fast and consistent
		this.exposuresThisPage.add( key );

		if ( sessionData.expires_at > Date.now() ) {
			return;
		}

		// Attempt to send exposure event
		sendFn();
		this.addLog( key, sessionData );
	}
}
module.exports = ExposureLogTracker;
