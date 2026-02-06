<?php

namespace MediaWiki\Extension\TestKitchen\Tests\Integration\TestKitchen\Coordination;

use Generator;
use MediaWiki\Extension\TestKitchen\Coordination\EnrollmentResultBuilder;
use MediaWiki\Extension\TestKitchen\Coordination\RequestEnrollmentsProcessor;
use MediaWiki\Request\FauxRequest;
use MediaWikiIntegrationTestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \MediaWiki\Extension\TestKitchen\Coordination\RequestEnrollmentsProcessor
 */
class RequestEnrollmentsProcessorTest extends MediaWikiIntegrationTestCase {
	private LoggerInterface $logger;
	private RequestEnrollmentsProcessor $processor;

	public function setUp(): void {
		parent::setUp();

		$this->logger = $this->createMock( LoggerInterface::class );
		$this->processor = new RequestEnrollmentsProcessor( $this->logger );
	}

	public function testExperimentEnrollmentsHeaderIsEmpty(): void {
		$request = new FauxRequest();

		$this->logger->expects( $this->never() )
			->method( 'error' );

		$expected = new EnrollmentResultBuilder();

		$this->assertEquals( $expected, $this->processor->process( $request ) );
	}

	public function testExperimentEnrollmentsHeaderHasOneAssignment(): void {
		$request = new FauxRequest();
		$request->setHeader( 'X-Experiment-Enrollments', 'foo_experiment=bar;' );

		$this->logger->expects( $this->never() )
			->method( 'error' );

		$expected = new EnrollmentResultBuilder();
		$expected->addExperiment( 'foo_experiment', 'awaiting', 'edge-unique' );
		$expected->addAssignment( 'foo_experiment', 'bar' );

		$this->assertEquals( $expected, $this->processor->process( $request ) );
	}

	public function testExperimentEnrollmentsHeaderHasMultipleAssignments(): void {
		$request = new FauxRequest();
		$request->setHeader( 'X-Experiment-Enrollments', 'foo_experiment=bar;qux_experiment=quux;' );

		$this->logger->expects( $this->never() )
			->method( 'error' );

		$expected = new EnrollmentResultBuilder();
		$expected->addExperiment( 'foo_experiment', 'awaiting', 'edge-unique' );
		$expected->addAssignment( 'foo_experiment', 'bar' );
		$expected->addExperiment( 'qux_experiment', 'awaiting', 'edge-unique' );
		$expected->addAssignment( 'qux_experiment', 'quux' );

		$this->assertEquals( $expected, $this->processor->process( $request ) );
	}

	public static function provideMalformedHeader(): Generator {
		yield [ 'foo=' ];

		// Assert that the result is only updated _after_ the header is parsed.
		yield [ 'foo_experiment=bar;qux_experiment;' ];
	}

	/**
	 * @dataProvider provideMalformedHeader
	 */
	public function testExperimentEnrollmentsHeaderIsMalformed( $header ): void {
		$request = new FauxRequest();
		$request->setHeader( 'X-Experiment-Enrollments', $header );

		$this->logger->expects( $this->once() )
			->method( 'error' )
			->with(
				'The X-Experiment-Enrollments header could not be parsed properly. The header is malformed.'
			);

		$expected = new EnrollmentResultBuilder();

		$this->assertEquals( $expected, $this->processor->process( $request ) );
	}

	public function testExperimentEnrollmentsHeaderExperimentNameIsInvalid(): void {
		$request = new FauxRequest();
		$request->setHeader( 'X-Experiment-Enrollments', 'foo=bar;valid_experiment=quux;' );

		$this->logger->expects( $this->once() )
			->method( 'error' )
			->with(
				'The X-Experiment-Enrollments header could not be parsed. The experiment name ' .
				'{experiment_name} is invalid'
			);

		$expected = new EnrollmentResultBuilder();

		$this->assertEquals( $expected, $this->processor->process( $request ) );
	}

	public function testExperimentEnrollmentsHeaderGroupNameIsInvalid(): void {
		$request = new FauxRequest();
		$request->setHeader( 'X-Experiment-Enrollments', 'foo_experiment=bar;valid_experiment=-invalid_group_name;' );

		$this->logger->expects( $this->once() )
			->method( 'error' )
			->with(
				'The X-Experiment-Enrollments header could not be parsed. The group name {group_name} ' .
				'for experiment {experiment_name} is invalid'
			);

		$expected = new EnrollmentResultBuilder();

		$this->assertEquals( $expected, $this->processor->process( $request ) );
	}

	public function testOverrideCookieAndQueryAreEmpty(): void {
		$request = new FauxRequest();

		$expected = new EnrollmentResultBuilder();

		$this->assertEquals( $expected, $this->processor->process( $request ) );
	}

	public static function provideOverrideCookieAndQuery(): Generator {
		yield [
			'foo:bar',
			'',
			[ 'foo' => 'bar' ],
		];
		yield [
			'',
			'qux:quux',
			[ 'qux' => 'quux' ],
		];
		yield [
			'foo:bar',
			'qux:quux',
			[
				'foo' => 'bar',
				'qux' => 'quux',
			],
		];
		yield [
			'foo:bar;qux:quux',
			'',
			[
				'foo' => 'bar',
				'qux' => 'quux',
			],
		];
	}

	/**
	 * @dataProvider provideOverrideCookieAndQuery
	 */
	public function testOverrideCookieAndQuery(
		string $rawCookie,
		string $rawQuery,
		array $expectedOverrides
	): void {
		$request = new FauxRequest( [ 'mpo' => $rawQuery ] );
		$request->setCookie( 'mpo', $rawCookie );

		$expected = new EnrollmentResultBuilder();

		foreach ( $expectedOverrides as $experimentName => $groupName ) {
			$expected->addExperiment( $experimentName, 'overridden', 'overridden' );
			$expected->addAssignment( $experimentName, $groupName, true );
		}

		$this->assertEquals( $expected, $this->processor->process( $request ) );
	}

	public function testMalformedOverrideQuery(): void {
		$request = new FauxRequest( [ 'mpo' => '51qdu1' ] );

		$this->logger->expects( $this->once() )
			->method( 'error' )
			->with( 'The raw enrollment overrides could not be parsed properly. They are malformed.' );

		$expected = new EnrollmentResultBuilder();

		$this->assertEquals(
			$expected,
			$this->processor->process( $request ),
			'If the query is malformed, then it isn\'t processed'
		);
	}
}
