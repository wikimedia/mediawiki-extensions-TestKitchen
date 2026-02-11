<?php

namespace MediaWiki\Extension\TestKitchen\Sdk;

use MediaWiki\Extension\EventStreamConfig\StreamConfigs as BaseStreamConfigs;

/**
 * This class is used to retrieve information from stream configurations.
 *
 * TODO: Merge MediaWiki\Extension\TestKitchen\ResourceLoader\Hooks::getMinimumUsableStreamConfigs and
 *  ::getMinimumUsableStreamConfig into this class
 *
 * @internal
 */
readonly class StreamConfigs {
	public function __construct(
		private BaseStreamConfigs $baseStreamConfigs
	) {
	}

	/**
	 * Gets the contextual attributes for the given stream.
	 *
	 * @param string $streamName
	 */
	public function getContextualAttributesForStream( string $streamName ): array {
		$rawStreamConfigs = $this->baseStreamConfigs->get( [ $streamName ] );
		$rawStreamConfig = $rawStreamConfigs[ $streamName ] ?? null;

		if ( !$rawStreamConfig ) {
			return [];
		}

		return $rawStreamConfig['producers']['metrics_platform_client']['provide_values'] ?? [];
	}
}
