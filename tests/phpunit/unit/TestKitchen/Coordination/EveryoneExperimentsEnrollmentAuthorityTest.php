<?php

namespace MediaWiki\Extension\TestKitchen\Tests\Unit\TestKitchen\Coordination;

use Generator;
use MediaWiki\Extension\TestKitchen\Coordination\EnrollmentRequest;
use MediaWiki\Extension\TestKitchen\Coordination\EnrollmentResultBuilder;
use MediaWiki\Extension\TestKitchen\Coordination\EveryoneExperimentsEnrollmentAuthority;
use MediaWikiUnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \MediaWiki\Extension\TestKitchen\Coordination\EveryoneExperimentsEnrollmentAuthority
 */
class EveryoneExperimentsEnrollmentAuthorityTest extends MediaWikiUnitTestCase {
	private EnrollmentRequest $request;
	private EnrollmentResultBuilder $result;
	private LoggerInterface $logger;
	private EveryoneExperimentsEnrollmentAuthority $authority;

	public function setUp(): void {
		parent::setUp();

		$this->request = $this->createMock( EnrollmentRequest::class );
		$this->result = $this->createMock( EnrollmentResultBuilder::class );

		$this->logger = $this->createMock( LoggerInterface::class );
		$this->authority = new EveryoneExperimentsEnrollmentAuthority( $this->logger );
	}

	public function testHeaderIsEmpty(): void {
		$this->request->expects( $this->once() )
			->method( 'getRawEveryoneExperimentsEnrollments' )
			->willReturn( '' );

		$this->result->expects( $this->never() )
			->method( 'addExperiment' );

		$this->result->expects( $this->never() )
			->method( 'addAssignment' );

		$this->logger->expects( $this->never() )
			->method( 'error' );

		$this->authority->enrollUser( $this->request, $this->result );
	}

	public function testHeaderHasOneAssignment(): void {
		$this->request->expects( $this->once() )
			->method( 'getRawEveryoneExperimentsEnrollments' )
			->willReturn( 'foo_experiment=bar;' );

		$this->result->expects( $this->once() )
			->method( 'addExperiment' )
			->with( 'foo_experiment', 'awaiting' );

		$this->result->expects( $this->once() )
			->method( 'addAssignment' )
			->with( 'foo_experiment', 'bar' );

		$this->logger->expects( $this->never() )
			->method( 'error' );

		$this->authority->enrollUser( $this->request, $this->result );
	}

	public function testHeaderHasMultipleAssignments(): void {
		$this->request->expects( $this->once() )
			->method( 'getRawEveryoneExperimentsEnrollments' )
			->willReturn( 'foo_experiment=bar;qux_experiment=quux;' );

		$addExperimentExpectedParameters = [
			[ 'foo_experiment', 'awaiting' ],
			[ 'qux_experiment', 'awaiting' ],
		];
		$this->result->expects( $this->exactly( 2 ) )
			->method( 'addExperiment' )
			->willReturnCallback( function ( ...$parameters ) use ( &$addExperimentExpectedParameters ): void {
				$expectedParameters = array_shift( $addExperimentExpectedParameters );
				$this->assertSame( $expectedParameters, $parameters );
			} );

		$addEnrollmentExpectedParameters = [
			[ 'foo_experiment', 'bar', false ],
			[ 'qux_experiment', 'quux', false ],
		];
		$this->result->expects( $this->exactly( 2 ) )
			->method( 'addAssignment' )
			->willReturnCallback( function ( ...$parameters ) use ( &$addEnrollmentExpectedParameters ): void {
				$expectedParameters = array_shift( $addEnrollmentExpectedParameters );
				$this->assertSame( $expectedParameters, $parameters );
			} );

		$this->authority->enrollUser( $this->request, $this->result );
	}

	/**
	 * @dataProvider provideMalformedHeader
	 */
	public function testHeaderIsMalformed(): void {
		$this->request->expects( $this->once() )
			->method( 'getRawEveryoneExperimentsEnrollments' )
			->willReturn( 'foo_experiment=bar;qux_experiment;' );

		$this->result->expects( $this->never() )
			->method( 'addExperiment' );

		$this->result->expects( $this->never() )
			->method( 'addAssignment' );

		$this->logger->expects( $this->once() )
			->method( 'error' )
			->with(
				'The X-Experiment-Enrollments header could not be parsed properly. The header is malformed.'
			);

		$this->authority->enrollUser( $this->request, $this->result );
	}

	public function testHeaderExperimentNameIsInvalid(): void {
		$this->request->expects( $this->once() )
			->method( 'getRawEveryoneExperimentsEnrollments' )
			->willReturn( 'foo=bar;valid_experiment=quux;' );

		$this->result->expects( $this->never() )
			->method( 'addExperiment' );

		$this->result->expects( $this->never() )
			->method( 'addAssignment' );

		$this->logger->expects( $this->once() )
			->method( 'error' )
			->with(
				'The X-Experiment-Enrollments header could not be parsed. The experiment name ' .
				'{experiment_name} is invalid'
			);

		$this->authority->enrollUser( $this->request, $this->result );
	}

	public function testHeaderGroupNameIsInvalid(): void {
		$this->request->expects( $this->once() )
			->method( 'getRawEveryoneExperimentsEnrollments' )
			->willReturn( 'foo_experiment=bar;valid_experiment=-invalid_group_name;' );

		$this->result->expects( $this->never() )
			->method( 'addExperiment' );

		$this->result->expects( $this->never() )
			->method( 'addAssignment' );

		$this->logger->expects( $this->once() )
			->method( 'error' )
			->with(
				'The X-Experiment-Enrollments header could not be parsed. The group name {group_name} ' .
				'for experiment {experiment_name} is invalid'
			);

		$this->authority->enrollUser( $this->request, $this->result );
	}

	public static function provideMalformedHeader(): Generator {
		yield [ 'foo=' ];

		// Assert that the result is only updated _after_ the header is parsed.
		yield [ 'foo=bar;qux=' ];
	}
}
