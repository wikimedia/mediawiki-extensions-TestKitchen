<?php

namespace MediaWiki\Extension\TestKitchen\ResourceLoader;

use MediaWiki\Config\Config;
use MediaWiki\Extension\TestKitchen\Services;
use MediaWiki\MediaWikiServices;
use MediaWiki\ResourceLoader as RL;

class Hooks {
	private const BASE_SCHEMA_ID = '/analytics/product_metrics/web/base/2.0.0';

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
			'streamNameToContextualAttributesMap' => self::getStreamNameToContextualAttributesMap( $config ),
		];
	}

	/**
	 * Gets a map of experiment configs from the Test Kitchen UI keyed by the experiment name.
	 *
	 * Fetches experiment configurations from the ConfigsFetcher service and
	 * extracts only the fields required by Test Kitchen SDKs.
	 *
	 * Resulting structure:
	 * [
	 *   'experiment_name' => [
	 *     'user_identifier_type' => array,
	 *     'stream_name' => array,
	 *     'schema_id' => string,
	 *     'contextual_attributes' => string[],
	 *   ],
	 *   ...
	 * ]
	 *
	 * @return array
	 */
	private static function getExperimentConfigs(): array {
		$experimentConfigs = Services::getConfigsFetcher()->getExperimentConfigs();
		$result = [];

		foreach ( $experimentConfigs as $experimentConfig ) {
			$experimentName = $experimentConfig['name'];
			$schemaId = array_key_exists( 'schema_id', $experimentConfig )
				? $experimentConfig['schema_id']
				: self::BASE_SCHEMA_ID;

			$result[ $experimentName ] = [
				'user_identifier_type' => $experimentConfig['user_identifier_type'],
				'stream_name' => $experimentConfig['stream_name'],
				'schema_id' => $schemaId,
				'contextual_attributes' => $experimentConfig['contextual_attributes'],
			];
		}
		return $result;
	}

	/**
	 * Builds a map of stream names to their contextual attributes.
	 *
	 * Retrieves stream configurations from the EventStreamConfig service for the streams in
	 * the `$wgTestKitchenExperimentStreamNames` config variable into a map of the experiment
	 * to their corresponding contextual attributes.
	 *
	 * Streams without contextual attributes are omitted.
	 *
	 * @param Config $config Configuration containing wgTestKitchenExperimentStreamNames
	 * @return array<string,string[]>
	 * @deprecated This method provides data for `mw.testKitchen.Experiment#setStream()`,
	 * which will be removed in a future release.
	 * @see ConfigsFetcher::getExperimentConfigs()
	 */
	private static function getStreamNameToContextualAttributesMap( Config $config ): array {
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
