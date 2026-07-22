<?php

namespace MediaWiki\Extension\TestKitchen\Tests\Unit\TestKitchen\Coordination;

use Generator;
use MediaWiki\Extension\TestKitchen\Coordination\EnrollmentRequest;
use MediaWiki\Extension\TestKitchen\Coordination\EnrollmentResultBuilder;
use MediaWiki\Extension\TestKitchen\Coordination\OverridesEnrollmentAuthority;
use MediaWikiUnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \MediaWiki\Extension\TestKitchen\Coordination\OverridesEnrollmentAuthority
 */
class OverridesEnrollmentAuthorityTest extends MediaWikiUnitTestCase {
	private EnrollmentRequest $request;
	private EnrollmentResultBuilder $result;
	private LoggerInterface $logger;
	private OverridesEnrollmentAuthority $authority;

	public function setUp(): void {
		parent::setUp();

		$this->request = $this->createMock( EnrollmentRequest::class );
		$this->result = new EnrollmentResultBuilder();
		$this->logger = $this->createMock( LoggerInterface::class );
		$this->authority = new OverridesEnrollmentAuthority( $this->logger );
	}

	public function testQueryIsEmpty(): void {
		$this->request->expects( $this->once() )
			->method( 'getRawEnrollmentOverridesFromQuery' )
			->willReturn( '' );

		$this->authority->enrollUser( $this->request, $this->result );

		$this->assertEquals( new EnrollmentResultBuilder(), $this->result );
	}

	/**
	 * @dataProvider provideQuery
	 */
	public function testQuery(
		string $rawQuery,
		array $expectedOverrides
	): void {
		$this->request->expects( $this->once() )
			->method( 'getRawEnrollmentOverridesFromQuery' )
			->willReturn( $rawQuery );

		$expectedResult = new EnrollmentResultBuilder();

		foreach ( $expectedOverrides as $experimentName => $groupName ) {
			$expectedResult->addExperiment( $experimentName, 'overridden', 'overridden' );
			$expectedResult->addAssignment( $experimentName, $groupName, true );
		}

		$this->authority->enrollUser( $this->request, $this->result );

		$this->assertEquals( $expectedResult, $this->result );
	}

	public static function provideQuery(): Generator {
		yield [
			'qux:quux',
			[ 'qux' => 'quux' ],
		];
		yield [
			'foo:bar;qux:quux',
			[
				'foo' => 'bar',
				'qux' => 'quux',
			],
		];
	}

	public function testMalformedQuery(): void {
		$this->request->expects( $this->once() )
			->method( 'getRawEnrollmentOverridesFromQuery' )
			->willReturn( '51qdu1' );

		$this->logger->expects( $this->once() )
			->method( 'error' )
			->with( 'The raw enrollment overrides could not be parsed properly. They are malformed.' );

		$this->authority->enrollUser( $this->request, $this->result );

		$this->assertEquals(
			new EnrollmentResultBuilder(),
			$this->result,
			'If the query is malformed, then it isn\'t processed'
		);
	}
}
