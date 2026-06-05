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

	protected function setUp(): void {
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
			],
			'TestKitchenExposureResetEpoch' => 0,
		] );
	}

	public function testGetConfigForTestKitchenModule(): void {
		$this->assertSame( 0, $this->config->get( 'TestKitchenExposureResetEpoch' ) );

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
	 * Tests that {@link Hooks::getExperimentConfigs()} passes through normalized experiment
	 * config fields from {@link ConfigsFetcher}.
	 */
	public function testGetExperimentConfigs(): void {
		$lunchConfig = $this->makeExperimentConfig(
			'lunch',
			[
				'contextual_attributes' => [ 'performer_is_logged_in', 'performer_is_temp' ],
			]
		);
		$supperConfig = $this->makeExperimentConfig(
			'supper',
			[
				'contextual_attributes' => [ 'page_id' ],
			]
		);

		$schemaId = '/analytics/product_metrics/web/base/2.1.0';
		$lunchConfig['schema_id'] = $schemaId;
		$lunchConfig['exposure_version'] = $this->computeExpectedExposureVersion( $lunchConfig, $schemaId );
		$lunchConfig['version'] = $lunchConfig['exposure_version'];

		$supperConfig['schema_id'] = $schemaId;
		$supperConfig['exposure_version'] = $this->computeExpectedExposureVersion( $supperConfig, $schemaId );
		$supperConfig['version'] = 'api-provided-version';

		$experimentConfigs = [
			$lunchConfig,
			$supperConfig,
		];

		$this->configsFetcher->expects( $this->once() )
			->method( 'getExperimentConfigs' )
			->willReturn( $experimentConfigs );

		$configForTestKitchenModule = Hooks::getConfigForTestKitchenModule( $this->context, $this->config );
		$actual = $configForTestKitchenModule['experimentConfigs'];

		$this->assertArrayHasKey( 'lunch', $actual );
		$this->assertArrayHasKey( 'supper', $actual );

		$lunch = $actual['lunch'];
		$supper = $actual['supper'];

		// Assert stable fields (exclude version fields)
		$this->assertSame(
			[
				'user_identifier_type' => 'mw-user',
				'stream_name' => 'product_metrics.web_base',
				'schema_id' => '/analytics/product_metrics/web/base/2.1.0',
				'contextual_attributes' => [ 'performer_is_logged_in', 'performer_is_temp' ],
				'phase_index' => 0,
			],
			$this->withoutVersionFields( $lunch )
		);

		$this->assertSame(
			[
				'user_identifier_type' => 'mw-user',
				'stream_name' => 'product_metrics.web_base',
				'schema_id' => '/analytics/product_metrics/web/base/2.1.0',
				'contextual_attributes' => [ 'page_id' ],
				'phase_index' => 0,
			],
			$this->withoutVersionFields( $supper )
		);

		$this->assertSame( $lunchConfig['exposure_version'], $lunch['exposure_version'] );
		$this->assertSame( $lunchConfig['version'], $lunch['version'] );
		$this->assertSame( $supperConfig['exposure_version'], $supper['exposure_version'] );
		$this->assertSame( $supperConfig['version'], $supper['version'] );
	}

	private function makeExperimentConfig( string $name, array $overrides = [] ): array {
		return array_replace_recursive(
			[
				'name' => $name,
				'start' => '2026-03-20T14:30:00Z',
				'end' => '2026-04-20T14:30:00Z',
				'user_identifier_type' => 'mw-user',
				'sample_rate' => [
					'default' => 1,
				],
				'groups' => [ 'control', 'treatment' ],
				'stream_name' => 'product_metrics.web_base',
				'contextual_attributes' => [ 'page_id' ],
				'phase_index' => 0,
			],
			$overrides
		);
	}

	/**
	 * Remove version fields from an experiment config for stable field assertions.
	 *
	 * @param array $config
	 * @return array
	 */
	private function withoutVersionFields( array $config ): array {
		unset( $config['exposure_version'], $config['version'] );
		return $config;
	}

	/**
	 * Compute the expected exposure version using the same logic as production code.
	 *
	 * @param array $experimentConfig
	 * @param string $schemaID
	 * @return string
	 */
	private function computeExpectedExposureVersion( array $experimentConfig, string $schemaID ): string {
		$groups = $experimentConfig['groups'] ?? [];
		sort( $groups );

		$semanticConfig = [
			'name' => $experimentConfig['name'],
			'user_identifier_type' => $experimentConfig['user_identifier_type'],
			'groups' => $groups,
			'sample_rate' => $experimentConfig['sample_rate'] ?? [],
			'stream_name' => $experimentConfig['stream_name'],
			'schema_id' => $schemaID,
			'contextual_attributes' => $experimentConfig['contextual_attributes'] ?? []
		];

		$json = json_encode(
			$semanticConfig,
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);

		return substr( hash( 'sha256', $json ), 0, 16 );
	}
}
