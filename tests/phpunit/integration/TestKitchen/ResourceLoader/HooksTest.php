<?php

namespace MediaWiki\Extension\TestKitchen\Tests\Integration\TestKitchen\ResourceLoader;

use MediaWiki\Config\Config;
use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\TestKitchen\ConfigsFetcher;
use MediaWiki\Extension\TestKitchen\ResourceLoader\Hooks;
use MediaWiki\ResourceLoader as RL;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\TestKitchen\ResourceLoader\Hooks
 */
class HooksTest extends MediaWikiIntegrationTestCase {
	private ConfigsFetcher $configsFetcher;
	private RL\Context $context;
	private Config $config;

	public function setUp(): void {
		$this->configsFetcher = $this->createMock( ConfigsFetcher::class );

		$this->setService( 'TestKitchen.ConfigsFetcher', $this->configsFetcher );

		$this->context = RL\Context::newDummyContext();
		$this->config = new HashConfig( [
			'TestKitchenExperimentEventIntakeServiceUrl' => 'http://foo.bar',
			'TestKitchenLoggedInExperimentEventIntakeServiceUrl' => 'http://baz.qux',
			'TestKitchenInstrumentEventIntakeServiceUrl' => 'http://quux.corge',
			'TestKitchenExperimentStreamNames' => [
				'product_metrics.web_base',
				'foo.bar',
				'baz.qux',
			]
		] );
	}

	public function testGetConfigForTestKitchenModule(): void {
		$configForTestKitchenModule = Hooks::getConfigForTestKitchenModule( $this->context, $this->config );

		$this->assertArrayContains(
			[
				'EveryoneExperimentEventIntakeServiceUrl' => 'http://foo.bar',
				'LoggedInExperimentEventIntakeServiceUrl' => 'http://baz.qux',
				'InstrumentEventIntakeServiceUrl' => 'http://quux.corge',
			],
			$configForTestKitchenModule
		);
	}

	public function testGetInstrumentConfigs(): void {
		$instrumentConfigs = [
			[
				'name' => 'foo',
				'sample' => [
					'unit' => 'session',
					'rate' => 1,
				],
				'stream_name' => 'product_metrics.web_base.foo',
				'contextual_attributes' => [
					'page_namespace_id',
					'mediawiki_skin',
				],
			],
			[
				'name' => 'bar',
				'sample' => [
					'unit' => 'session',
					'rate' => 0.5,
				],
				'stream_name' => 'product_metrics.web_base.bar',
				'contextual_attributes' => [
					'mediawiki_database',
					'performer_is_bot',
				],
			],
		];

		$expected = [
			'foo' => [
				'sample' => [
					'unit' => 'session',
					'rate' => 1,
				],
				'stream_name' => 'product_metrics.web_base.foo',
				'contextual_attributes' => [
					'page_namespace_id',
					'mediawiki_skin',
				],
			],
			'bar' => [
				'sample' => [
					'unit' => 'session',
					'rate' => 0.5,
				],
				'stream_name' => 'product_metrics.web_base.bar',
				'contextual_attributes' => [
					'mediawiki_database',
					'performer_is_bot',
				],
			],
		];

		$this->configsFetcher->expects( $this->once() )
			->method( 'getInstrumentConfigs' )
			->willReturn( $instrumentConfigs );

		$configForTestKitchenModule = Hooks::getConfigForTestKitchenModule( $this->context, $this->config );
		$actual = $configForTestKitchenModule[ 'instrumentConfigs' ];

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * Tests that {@link Hooks::getExperimentConfigs()} filters out unknown streams
	 */
	public function testGetExperimentConfigs(): void {
		$this->overrideConfigValues( [
			'EventStreams' => [
				'product_metrics.web_base' => [
					'producers' => [
						'metrics_platform_client' => [
							'provide_values' => [
								'namespace_id',
								'namespace_name',
							],
						],
					],
				],
				'foo.bar' => [],
			],
		] );

		$expected = [
			'product_metrics.web_base' => [
				'contextual_attributes' => [
					'namespace_id',
					'namespace_name',
				],
			],
		];

		$configForTestKitchenModule = Hooks::getConfigForTestKitchenModule( $this->context, $this->config );
		$actual = $configForTestKitchenModule[ 'experimentConfigs' ];

		$this->assertEquals( $expected, $actual );
	}
}
