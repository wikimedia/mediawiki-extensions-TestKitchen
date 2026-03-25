<?php

namespace MediaWiki\Extension\TestKitchen\Sdk;

/**
 * Tier 1: exposure logging tracker (in-memory, per request)
 *
 * This tracker prevents duplicate exposure events within a single HTTP request.
 * It acts as the floor of deduplication, ensuring at most one exposure is sent
 * per experiment per request, regardless of downstream failures.
 *
 * Note: Cross-request deduplication (e.g., per session/tab) is handled client-side.
 */
class ExposureLogTracker {
	/**
	 * In-memory set of logged exposure keys.
	 *
	 * Keys are of the form:
	 *   tk_exposure.<experimentName>:<assignedGroup>:<exposureVersion>
	 *
	 * @var array<string,bool>
	 */
	private array $loggedExposures = [];

	/**
	 * Mark an exposure as logged for this request.
	 *
	 * Once recorded, later checks for the same key will prevent re-sending.
	 *
	 * @param string $key Stable exposure deduplication key
	 */
	public function addLog( string $key ): void {
		// Tier 1: mark as seen for the lifetime of this request.
		$this->loggedExposures[ $key ] = true;
	}

	/**
	 * Determine whether an exposure event should be sent.
	 *
	 * Returns true if the exposure has not yet been logged during this request.
	 *
	 * @param string $key Stable exposure deduplication key
	 * @return bool True if the exposure should be sent
	 */
	public function checkShouldSend( string $key ): bool {
		// Allow send only if we have not seen this key in this request.
		return !isset( $this->loggedExposures[ $key ] );
	}

	/**
	 * Build a stable exposure key for deduplication.
	 *
	 * The key incorporates:
	 * - experiment name
	 * - assigned group
	 * - exposure version (for invalidation when experiment config changes)
	 *
	 * @param string $experimentName
	 * @param string $assignedGroup
	 * @param string|null $version Exposure version (defaults to 'v0')
	 * @return string Stable deduplication key
	 */
	public function makeKey(
		string $experimentName,
		string $assignedGroup,
		?string $version = null
	): string {
		$exposureVersion = $version ?? 'v0';

		return "tk_exposure.$experimentName:$assignedGroup:$exposureVersion";
	}
}
