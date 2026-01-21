<?php

namespace MediaWiki\Extension\TestKitchen\Tests\Unit\TestKitchen\Sdk;

use MediaWiki\Extension\EventStreamConfig\StreamConfigs as BaseStreamConfigs;
use MediaWiki\Extension\TestKitchen\ConfigsFetcher;
use MediaWiki\Extension\TestKitchen\Sdk\EventFactory;
use MediaWiki\Extension\TestKitchen\Sdk\EventSender;
use MediaWiki\Extension\TestKitchen\Sdk\Instrument;
use MediaWiki\Extension\TestKitchen\Sdk\InstrumentManager;
use MediaWiki\Extension\TestKitchen\Sdk\StreamConfigs;
use MediaWiki\Extension\TestKitchen\Sdk\UnsampledInstrument;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\TestKitchen\Sdk\InstrumentManager
 */
class InstrumentManagerTest extends MediaWikiUnitTestCase {
	private EventSender $eventSender;
	private EventFactory $eventFactory;
	private InstrumentManager $instrumentManager;
	private StreamConfigs $staticStreamConfigs;
	private ConfigsFetcher $configsFetcher;

	public function setUp(): void {
		parent::setUp();

		$this->eventSender = $this->createMock( EventSender::class );
		$this->eventFactory = $this->createMock( EventFactory::class );
		$this->configsFetcher = $this->createMock( ConfigsFetcher::class );

		$baseStreamConfigs = new BaseStreamConfigs(
			[
				'product_metrics.web_base' => [
					'producers' => [
						'metrics_platform_client' => [
							'provide_values' => [
								'performer_name',
								'page_id'
							]
						],
					],
				],
			],
			[]
		);
		$this->staticStreamConfigs = new StreamConfigs( $baseStreamConfigs );

		$this->instrumentManager = new InstrumentManager(
			$this->eventSender,
			$this->eventFactory,
			$this->configsFetcher
		);
	}

	public function testGetInstrument(): void {
		$instrumentConfigs = [
			[
				'name' => 'my-instrument',
				'sample' => [
					'unit' => 'session',
					'rate' => 1,
				],
				'stream_name' => 'product_metrics.web_base.foo',
				'schema_title' => 'analytics/product_metrics/web/base',
				'contextual_attributes' => [
					'performer_name',
					'page_id',
				],
			],
			[
				'name' => 'other-instrument',
				'sample' => [
					'unit' => 'session',
					'rate' => 0.5,
				],
				'stream_name' => 'product_metrics.web_base.bar',
				'schema_title' => 'analytics/product_metrics/web/base',
				'contextual_attributes' => [
					'mediawiki_database',
					'performer_is_bot',
				],
			],
		];

		$this->configsFetcher->expects( $this->once() )
			->method( 'getInstrumentConfigs' )
			->willReturn( $instrumentConfigs );

		$expectedInstrument = new Instrument(
			$this->eventSender,
			$this->eventFactory,
			[
				'name' => 'my-instrument',
				'sample' => [
					'unit' => 'session',
					'rate' => 1,
				],
				'stream_name' => 'product_metrics.web_base.foo',
				'schema_title' => 'analytics/product_metrics/web/base',
				'schema_id' => 'analytics/product_metrics/web/base/2.0.0',
				'contextual_attributes' => [
					'performer_name',
					'page_id',
				]
			]
		);
		$actualInstrument = $this->instrumentManager->getInstrument( 'my-instrument' );
		$actualInstrument->setSchema( 'analytics/product_metrics/web/base/2.0.0' );

		$this->assertEquals( $expectedInstrument, $actualInstrument );
	}

	public function testGetNonExistingInstrument(): void {
		$expectedInstrument = new UnsampledInstrument(
			$this->eventSender,
			$this->eventFactory,
			[]
		);
		$actualInstrument = $this->instrumentManager->getInstrument( 'non-existing-instrument' );

		$this->assertEquals( $expectedInstrument, $actualInstrument );
	}

	public function testGetUnsampledInstrument(): void {
		$expectedInstrument = new UnsampledInstrument(
			$this->eventSender,
			$this->eventFactory,
			[]
		);
		$actualInstrument = $this->instrumentManager->getInstrument( 'an-unsampled-instrument' );

		$this->assertEquals( $expectedInstrument, $actualInstrument );
	}
}
