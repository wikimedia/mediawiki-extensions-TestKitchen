<?php

namespace MediaWiki\Extension\TestKitchen\Tests\Unit\TestKitchen\Coordination;

use MediaWiki\Extension\TestKitchen\Coordination\EnrollmentRequest;
use MediaWiki\Extension\TestKitchen\Coordination\EnrollmentResultBuilder;
use MediaWiki\Extension\TestKitchen\Coordination\LoggedInExperimentsEnrollmentAuthority;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\TestKitchen\Coordination\LoggedInExperimentsEnrollmentAuthority
 */
class LoggedInExperimentsEnrollmentAuthorityTest extends MediaWikiUnitTestCase {
	private UserIdentity $user;
	private EnrollmentRequest $request;
	private EnrollmentResultBuilder $result;
	private CentralIdLookup $centralIdLookup;
	private LoggedInExperimentsEnrollmentAuthority $authority;

	public function setUp(): void {
		parent::setUp();

		$this->user = new UserIdentityValue( 1, self::class );

		$this->request = $this->createMock( EnrollmentRequest::class );
		$this->request->expects( $this->any() )
			->method( 'getGlobalUser' )
			->willReturn( $this->user );

		$this->centralIdLookup = $this->createMock( CentralIdLookup::class );
		$this->centralIdLookup->expects( $this->any() )
			->method( 'centralIdFromName' )
			->with( $this->user->getName() )
			->willReturn( 2 );

		$this->result = $this->createMock( EnrollmentResultBuilder::class );

		$this->authority = new LoggedInExperimentsEnrollmentAuthority( $this->centralIdLookup );
	}

	public function testNoActiveExperiments(): void {
		$this->request->expects( $this->once() )
			->method( 'getActiveLoggedInExperiments' )
			->willReturn( [] );

		$this->result->expects( $this->never() )
			->method( 'addExperiment' );

		$this->result->expects( $this->never() )
			->method( 'addAssignment' );

		$this->authority->enrollUser( $this->request, $this->result );
	}

	public function testOneActiveExperiment(): void {
		$this->request->expects( $this->once() )
			->method( 'getActiveLoggedInExperiments' )
			->willReturn( [
				[
					'name' => 'foo',
					'sample' => [
						'rate' => 1,
					],
					'groups' => [
						'control',
						'treatment',
					],
				],
			] );

		$this->result->expects( $this->once() )
			->method( 'addExperiment' )
			->with( 'foo', '377195904c99497c2cdb7aaecaf541ca717f34e5357dace55ebb1711d54190c2' );

		$this->result->expects( $this->once() )
			->method( 'addAssignment' )
			->with( 'foo', 'control' );

		$this->authority->enrollUser( $this->request, $this->result );
	}

	public function testMultipleActiveExperiments(): void {
		$this->request->expects( $this->once() )
			->method( 'getActiveLoggedInExperiments' )
			->willReturn( [
				[
					'name' => 'foo',
					'sample' => [
						'rate' => 1,
					],
					'groups' => [
						'control',
						'treatment',
					],
				],
				[
					'name' => 'bar',
					'sample' => [
						'rate' => 0.5,
					],
					'groups' => [
						'control',
						'treatment',
					]
				]
			] );

		$addExperimentExpectedParameters = [
			[
				'foo',
				'377195904c99497c2cdb7aaecaf541ca717f34e5357dace55ebb1711d54190c2'
			],
			[
				'bar',
				'92bd577d056dc2d6fe69083f638d4ce8bf4e8e4b88b351bcb8bbdf2dcef6a437',
			],
		];
		$this->result->expects( $this->exactly( 2 ) )
			->method( 'addExperiment' )
			->willReturnCallback( function ( ...$parameters ) use ( &$addExperimentExpectedParameters ): void {
				$expectedParameters = array_shift( $addExperimentExpectedParameters );
				$this->assertSame( $expectedParameters, $parameters );
			} );

		$addEnrollmentExpectedParameters = [
			[ 'foo', 'control', false ],
			[ 'bar', 'treatment', false ],
		];
		$this->result->expects( $this->exactly( 2 ) )
			->method( 'addAssignment' )
			->willReturnCallback( function ( ...$parameters ) use ( &$addEnrollmentExpectedParameters ): void {
				$expectedParameters = array_shift( $addEnrollmentExpectedParameters );
				$this->assertSame( $expectedParameters, $parameters );
			} );

		$this->authority->enrollUser( $this->request, $this->result );
	}

	public function testNoCentralID(): void {
		$centralIdLookup = $this->createMock( CentralIdLookup::class );
		$centralIdLookup->expects( $this->once() )
			->method( 'centralIdFromName' )
			->with( $this->user->getName() )
			->willReturn( 0 );

		$this->request->expects( $this->never() )
			->method( 'getActiveLoggedInExperiments' );

		$authority = new LoggedInExperimentsEnrollmentAuthority( $centralIdLookup );
		$authority->enrollUser( $this->request, $this->result );
	}
}
