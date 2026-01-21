<?php

namespace MediaWiki\Extension\TestKitchen\Sdk;

/**
 * This class is the same as [`MediaWiki\Extension\EventLogging\Libs\UserBucketProvider\UserBucketService`][0]. You
 * can find the names of the authors and maintainers of that class [here][0].
 *
 * [0] https://gerrit.wikimedia.org/r/plugins/gitiles/mediawiki/extensions/EventLogging/+log/de06180f92aa89802d50fa1b9d2165f483022ac8/includes/Libs/UserBucketProvider
 *
 * @internal
 */
class UserEditCountService {

	/**
	 * Gets the coarse bucket corresponding given the user's edit count.
	 *
	 * The buckets are as follows:
	 *
	 * * 0 edits
	 * * 1-4 edits
	 * * 5-99 edits
	 * * 100-999 edits
	 * * 1000+ edits
	 *
	 * These buckets are the current standard but are subject to change in the future. They are usually safe to keep in
	 * sanitized streams and should remain so even if they are changed.
	 *
	 * @param int $userEditCount
	 */
	public function getUserEditCountBucket( int $userEditCount ): string {
		if ( $userEditCount >= 1000 ) {
			return '1000+ edits';
		}
		if ( $userEditCount >= 100 ) {
			return '100-999 edits';
		}
		if ( $userEditCount >= 5 ) {
			return '5-99 edits';
		}
		if ( $userEditCount >= 1 ) {
			return '1-4 edits';
		}
		return '0 edits';
	}
}
