<?php

namespace MediaWiki\Extension\TestKitchen\Sdk;

use MediaWiki\Extension\TestKitchen\ConfigsFetcher;

class InstrumentManager implements InstrumentManagerInterface {

	private const BASE_SCHEMA_ID = '/analytics/product_metrics/web/base/2.0.0';

	private ?array $instrumentConfigs = null;

	public function __construct(
		private readonly EventSender $eventSender,
		private readonly EventFactory $eventFactory,
		private readonly ConfigsFetcher $configsFetcher
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function getInstrument( string $instrumentName ): InstrumentInterface {
		if ( $this->instrumentConfigs === null ) {
			$this->instrumentConfigs = $this->configsFetcher->getInstrumentConfigs();
		}

		$instrumentConfig = null;

		// Checks whether the instrument exists
		foreach ( $this->instrumentConfigs as $config ) {
			if ( $config['name'] === $instrumentName ) {
				$instrumentConfig = $config;
				break;
			}
		}

		// The instrument doesn't exist
		if ( $instrumentConfig == null ) {
			return new UnsampledInstrument(
				$this->eventSender,
				$this->eventFactory,
				[]
			);
		}
		// TODO Analytics sampling is not available for PHP SDK. If the instrument exists, that means the insstrument
		// is in sample

		// By default, base schemaID will be set
		$instrumentConfig['schema_id'] = self::BASE_SCHEMA_ID;

		// Required contextual attributes are added automatically by EventFactory, by taking them from $instrumentConfig
		return new Instrument(
			$this->eventSender,
			$this->eventFactory,
			$instrumentConfig
		);
	}
}
