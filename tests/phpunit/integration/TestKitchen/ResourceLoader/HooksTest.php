<?php

namespace MediaWiki\Extension\TestKitchen\Tests\Integration\TestKitchen\ResourceLoader;

use Generator;
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
			'EventLoggingServiceUri' => 'http://baz.qux',
			'TestKitchenExperimentStreamNames' => [
				'test_kitchen.web_base',
			]
		] );
	}

	public function testGetConfigForTestKitchenModule(): void {
		$configForTestKitchenModule = Hooks::getConfigForTestKitchenModule( $this->context, $this->config );

		$this->assertArrayContains(
			[
				'EveryoneExperimentEventIntakeServiceUrl' => 'http://foo.bar',
				'LoggedInExperimentEventIntakeServiceUrl' => 'http://baz.qux',
				'InstrumentEventIntakeServiceUrl' => 'http://baz.qux',
			],
			$configForTestKitchenModule
		);
	}

	public static function provideInstrumentConfigs(): Generator {
		yield [
			[],
			[
				[
					'name' => 'foo',
					'sample' => [
						'unit' => 'session',
						'rate' => 1,
					],
					'stream_name' => 'test_kitchen.web_base.foo',
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
					'stream_name' => 'test_kitchen.web_base.bar',
					'contextual_attributes' => [
						'mediawiki_database',
						'performer_is_bot',
					],
				],
			],
			[
				'foo' => [
					'producers' => [
						'metrics_platform_client' => [
							'provide_values' => [
								'page_namespace_id',
								'mediawiki_skin',
							],
							'stream_name' => 'test_kitchen.web_base.foo',
						],
					],
					'sample' => [
						'unit' => 'session',
						'rate' => 1,
					],
				],
				'bar' => [
					'producers' => [
						'metrics_platform_client' => [
							'provide_values' => [
								'mediawiki_database',
								'performer_is_bot',
							],
							'stream_name' => 'test_kitchen.web_base.bar',
						],
					],
					'sample' => [
						'unit' => 'session',
						'rate' => 0.5,
					],
				],
			],
		];

		// Configs for streams referenced in instrumentConfig.producers.metrics_platform_client.stream_name are copied.
		yield [
			[
				'test_kitchen.web_base' => [
					'producers' => [
						'metrics_platform_client' => [
							'provide_values' => [
								'page_namespace_id',
								'mediawiki_skin',
							],
						],
					],
					'sample' => [
						'unit' => 'session',
						'rate' => 1,
					],
				],
			],
			[
				[
					'name' => 'foo',
					'sample' => [
						'unit' => 'session',
						'rate' => 1,
					],
					'stream_name' => 'test_kitchen.web_base',
					'contextual_attributes' => [
						'page_namespace_id',
						'mediawiki_skin',
					],
				],
			],
			[
				'foo' => [
					'producers' => [
						'metrics_platform_client' => [
							'provide_values' => [
								'page_namespace_id',
								'mediawiki_skin',
							],
							'stream_name' => 'test_kitchen.web_base',
						],
					],
					'sample' => [
						'unit' => 'session',
						'rate' => 1,
					],
				],
				'test_kitchen.web_base' => [
					'producers' => [
						'metrics_platform_client' => [
							'provide_values' => [
								'page_namespace_id',
								'mediawiki_skin',
							],
						],
					],
					'sample' => [
						'unit' => 'session',
						'rate' => 1,
					],
				],
			]
		];
	}

	/**
	 * @dataProvider provideInstrumentConfigs
	 */
	public function testGetStreamConfigsForInstruments(
		$streamConfigs,
		array $instrumentConfigs,
		array $expectedStreamConfigs
	): void {
		$this->overrideConfigValue( 'EventStreams', $streamConfigs );

		$this->configsFetcher->expects( $this->once() )
			->method( 'getInstrumentConfigs' )
			->willReturn( $instrumentConfigs );

		$configForTestKitchenModule = Hooks::getConfigForTestKitchenModule( $this->context, $this->config );
		$actualStreamConfigs = $configForTestKitchenModule[ 'instrumentConfigs' ];

		$this->assertEquals( $expectedStreamConfigs, $actualStreamConfigs );
	}
}
