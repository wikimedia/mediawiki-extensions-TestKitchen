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
			'LoggedInExperimentEventIntakeServiceUrl' =>
				$config->get( 'TestKitchenLoggedInExperimentEventIntakeServiceUrl' ),
			'InstrumentEventIntakeServiceUrl' => $config->get( 'TestKitchenInstrumentEventIntakeServiceUrl' ),

			'experimentConfigs' => self::getExperimentConfigs( $config ),
			'instrumentConfigs' => self::getInstrumentConfigs(),
		];
	}

	/**
	 * Gets the configs for experiments configured in Test Kitchen UI.
	 *
	 * Currently, experiment streams and contextual attributes aren't configured in Test Kitchen so this method
	 * processes the stream configs for the streams in the `$wgTestKitchenExperimentStreamNames` config variable into
	 * a map of experiment to contextual attributes.
	 *
	 * In future, this method will process the experiment configs from Test Kitchen UI like
	 * {@link Hooks::getInstrumentConfigs()}.
	 *
	 * @param Config $config
	 * @return array
	 */
	private static function getExperimentConfigs( Config $config ): array {
		$streamNames = $config->get( 'TestKitchenExperimentStreamNames' );

		// NOTE: TestKitchen has a hard dependency on EventStreamConfig. If this code is executing, then
		// EventStreamConfig is loaded and this service is defined.
		$streamConfigs = MediaWikiServices::getInstance()->getService( 'EventStreamConfig.StreamConfigs' )
			->get( $streamNames );

		$result = [];

		foreach ( $streamConfigs as $streamName => $streamConfig ) {
			if ( isset( $streamConfig['producers']['metrics_platform_client']['provide_values'] ) ) {
				$result[ $streamName ]['contextual_attributes'] =
					$streamConfig['producers']['metrics_platform_client']['provide_values'];
			}
		}

		return $result;
	}

	/**
	 * Gets the configs for instruments configured in Test Kitchen UI.
	 *
	 * Note well that the stream configs are limited copies of the originals. The copies only contain the
	 * `producers.metrics_platform_client` and `sample` properties.
	 * This helps keep the `ext.testKitchen` ResourceLoader module small.
	 *
	 * @return array
	 */
	private static function getInstrumentConfigs(): array {
		$instrumentConfigs = Services::getConfigsFetcher()->getInstrumentConfigs();
		$result = [];

		foreach ( $instrumentConfigs as $instrumentConfig ) {
			$instrumentName = $instrumentConfig['name'];

			$result[ $instrumentName ] = [
				'sample' => $instrumentConfig['sample'],
				'stream_name' => $instrumentConfig['stream_name'],
				'contextual_attributes' => $instrumentConfig['contextual_attributes'],

				// TODO: 'schema_id' => ???
			];
		}

		return $result;
	}
}
