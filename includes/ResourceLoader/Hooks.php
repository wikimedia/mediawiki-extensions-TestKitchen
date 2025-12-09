<?php

namespace MediaWiki\Extension\TestKitchen\ResourceLoader;

use MediaWiki\Config\Config;
use MediaWiki\Extension\TestKitchen\Services;
use MediaWiki\MediaWikiServices;
use MediaWiki\ResourceLoader as RL;

class Hooks {

	/**
	 * Gets the contents of the `config.json` file for the `ext.testKitchen` ResourceLoader module.
	 *
	 * @param RL\Context $context
	 * @param Config $config
	 * @return array
	 */
	public static function getConfigForTestKitchenModule( RL\Context $context, Config $config ): array {
		return [
			'EveryoneExperimentEventIntakeServiceUrl' =>
				$config->get( 'TestKitchenExperimentEventIntakeServiceUrl' ),

			// NOTE: TestKitchen has a hard dependency on EventLogging. If this code is executing, then
			// EventLogging is loaded and this config variable is defined.
			'LoggedInExperimentEventIntakeServiceUrl' => $config->get( 'EventLoggingServiceUri' ),

			'InstrumentEventIntakeServiceUrl' => $config->get( 'EventLoggingServiceUri' ),

			'streamConfigs' => self::getStreamConfigs( $config ),
			'instrumentConfigs' => self::getStreamConfigsForInstruments(),
		];
	}

	/**
	 * Gets the stream configs for experiments configured in Test Kitchen.
	 * Currently, the names of streams are statically configured using
	 * the `$wgTestKitchenExperimentStreamNames` config variable.
	 *
	 * Note well that the stream configs are limited copies of the originals. The copies only contain the
	 * `producers.metrics_platform_client` property because:
	 *
	 * 1. The Test Kitchen client treats streams as in-sample by default. Therefore, removing the analytics sampling
	 *    config from the copied stream config makes the stream always in-sample
	 *
	 * 2. It helps keep the `ext.testKitchen` ResourceLoader module small.
	 *
	 * @param Config $config
	 * @return array
	 */
	private static function getStreamConfigs( Config $config ): array {
		return self::getMinimumUsableStreamConfigs( $config->get( 'TestKitchenExperimentStreamNames' ) );
	}

	/**
	 * Gets the stream configs for instruments configured in Test Kitchen.
	 *
	 * Note well that the stream configs are limited copies of the originals. The copies only contain the
	 * `producers.metrics_platform_client` and `sample` properties.
	 * This helps keep the `ext.testKitchen` ResourceLoader module small.
	 *
	 * @return array
	 */
	private static function getStreamConfigsForInstruments(): array {
		$instrumentConfigs = Services::getConfigsFetcher()->getInstrumentConfigs();
		$instrumentStreamConfigs = [];
		$targetedStreams = [];

		foreach ( $instrumentConfigs as $instrumentConfig ) {
			$instrumentName = $instrumentConfig['slug'];
			$targetStreamName = $instrumentConfig['stream_name'];
			$targetedStreams[] = $targetStreamName;

			$instrumentStreamConfigs[ $instrumentName ] = [
				'producers' => [
					'metrics_platform_client' => [
						'provide_values' => $instrumentConfig['contextual_attributes'],
						'stream_name' => $targetStreamName,
					],
				],
				'sample' => $instrumentConfig['sample'],

				// TODO: 'schema_id' => ???
			];
		}

		// Get the stream configs for the streams targeted by the instruments
		$targetedStreamConfigs = self::getMinimumUsableStreamConfigs( $targetedStreams );

		return array_merge( $instrumentStreamConfigs, $targetedStreamConfigs );
	}

	/**
	 * @param string[] $streamNames
	 * @return array
	 */
	private static function getMinimumUsableStreamConfigs( array $streamNames ): array {
		return array_map(
			self::getMinimumUsableStreamConfig( ... ),

			// NOTE: TestKitchen has a hard dependency on EventStreamConfig. If this code is executing, then
			// EventStreamConfig is loaded and this service is defined.
			MediaWikiServices::getInstance()->getService( 'EventStreamConfig.StreamConfigs' )
				->get( $streamNames )
		);
	}

	/**
	 * Gets the minimum viable stream config usable by the Test Kitchen JS Client by removing all but the following
	 * properties:
	 *
	 * * `producers.metrics_platform_client`
	 * * `sample`
	 *
	 * @param array $streamConfig
	 * @return array
	 */
	private static function getMinimumUsableStreamConfig( array $streamConfig ): array {
		$result = array_intersect_key(
			$streamConfig,
			[
				'producers' => true,
				'sample' => true,
			]
		);

		if ( isset( $streamConfig['producers']['metrics_platform_client'] ) ) {
			$result['producers'] = [
				'metrics_platform_client' => $streamConfig['producers']['metrics_platform_client'],
			];
		}

		return $result;
	}
}
