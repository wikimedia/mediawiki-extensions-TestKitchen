<?php

namespace MediaWiki\Extension\TestKitchen\ResourceLoader;

use MediaWiki\Config\Config;
use MediaWiki\Extension\TestKitchen\Services;
use MediaWiki\ResourceLoader as RL;

class Hooks {
	private const BASE_SCHEMA_ID = '/analytics/product_metrics/web/base/2.1.0';

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

			'experimentConfigs' => self::getExperimentConfigs(),
			'instrumentConfigs' => self::getInstrumentConfigs(),
			'exposureResetEpoch' => $config->get( 'TestKitchenExposureResetEpoch' ),
		];
	}

	/**
	 * Gets a map of experiment configs from the Test Kitchen UI keyed by the experiment name.
	 *
	 * Fetches experiment configurations from the ConfigsFetcher service and extracts only the fields required by the
	 * Test Kitchen SDKs.
	 *
	 * @return array<string,array{user_identifier_type:string,stream_name:string,schema_id:string,contextual_attributes:string,exposure_version:string,version:string}>
	 */
	private static function getExperimentConfigs(): array {
		$experimentConfigs = Services::getConfigsFetcher()->getExperimentConfigs();
		$result = [];

		foreach ( $experimentConfigs as $experimentConfig ) {
			$experimentName = $experimentConfig['name'];

			$result[ $experimentName ] = [
				'user_identifier_type' => $experimentConfig['user_identifier_type'],
				'stream_name' => $experimentConfig['stream_name'],
				'schema_id' => $experimentConfig['schema_id'],
				'contextual_attributes' => $experimentConfig['contextual_attributes'],
				'exposure_version' => $experimentConfig['exposure_version'],
				'version' => $experimentConfig['version'],
				'phase_index' => $experimentConfig['phase_index'],
			];
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
