<?php

namespace MediaWiki\Extension\TestKitchen\Tests\Unit\TestKitchen\Coordination;

use Generator;
use MediaWiki\Config\Config;
use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\TestKitchen\ConfigsFetcher;
use MediaWiki\Extension\TestKitchen\Coordination\Coordinator;
use MediaWiki\Extension\TestKitchen\Coordination\UserSplitterInstrumentation;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\TestKitchen\Coordination\Coordinator
 */
class CoordinatorTest extends MediaWikiUnitTestCase {
	private Config $config;
	private ConfigsFetcher $configsFetcher;
	private UserSplitterInstrumentation $userSplitterInstrumentation;
	private Coordinator $coordinator;

	public function setUp(): void {
		parent::setUp();

		$this->config = new HashConfig();
		$this->configsFetcher = $this->createMock( ConfigsFetcher::class );
		$this->userSplitterInstrumentation = $this->createMock( UserSplitterInstrumentation::class );
		$this->coordinator = new Coordinator(
			$this->config,
			$this->configsFetcher,
			$this->userSplitterInstrumentation
		);
	}

	public static function provideNoDefinedExperiment(): Generator {
		yield [ [] ];

		yield [
			[
				[
					'name' => 'foo-bar-baz',
					'sample' => [
						'rate' => 0.5,
					],
					'groups' => [
						'control',
						'treatment',
					],
				],
			],
		];
	}

	/**
	 * @dataProvider provideNoDefinedExperiment
	 */
	public function testNoDefinedExperiment( array $experimentConfigs ): void {
		$identifier = '0x0ff1c3';
		$experimentName = 'my-awesome-experiment';

		$this->configsFetcher->expects( $this->once() )
			->method( 'getExperimentConfigs' )
			->willReturn( $experimentConfigs );

		$this->userSplitterInstrumentation->expects( $this->never() )
			->method( 'getUserHash' );

		$this->userSplitterInstrumentation->expects( $this->never() )
			->method( 'isSampled' );

		$this->assertSame(
			null,
			$this->coordinator->getAssignmentForUser( $identifier, $experimentName )
		);
	}

	public function testDefinedExperiment(): void {
		$identifier = '0x0ff1c3';
		$experimentName = 'my-awesome-experiment';
		$groups = [ 'control', 'treatment' ];
		$experimentConfigs = [
			[
				'name' => 'foo-bar-baz',
				'sample' => [
					'rate' => 0.5,
				],
				'groups' => [
					'control',
					'treatment',
				],
			],
			[
				'name' => $experimentName,
				'sample' => [
					'rate' => 0.5,
				],
				'groups' => $groups,
			],
		];

		$this->configsFetcher->expects( $this->once() )
			->method( 'getExperimentConfigs' )
			->willReturn( $experimentConfigs );

		$this->userSplitterInstrumentation->expects( $this->once() )
			->method( 'getUserHash' )
			->with( $identifier, $experimentName )
			->willReturn( 1.567 );

		$this->userSplitterInstrumentation->expects( $this->once() )
			->method( 'isSampled' )
			->with(
				0.5,
				$groups,
				1.567
			)
			->willReturn( true );

		$this->userSplitterInstrumentation->expects( $this->once() )
			->method( 'getBucket' )
			->with( $groups, 1.567 )
			->willReturn( 'treatment' );

		$this->assertSame(
			'treatment',
			$this->coordinator->getAssignmentForUser( $identifier, $experimentName )
		);
	}
}
