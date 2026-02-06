<?php

namespace MediaWiki\Extension\TestKitchen\Tests\Unit\TestKitchen\Sdk;

use MediaWiki\Extension\EventStreamConfig\StreamConfigs as BaseStreamConfigs;
use MediaWiki\Extension\TestKitchen\ConfigsFetcher;
use MediaWiki\Extension\TestKitchen\Coordination\EnrollmentResultBuilder;
use MediaWiki\Extension\TestKitchen\Coordination\EnrollmentsProcessor;
use MediaWiki\Extension\TestKitchen\Coordination\RequestEnrollmentsProcessor;
use MediaWiki\Extension\TestKitchen\Sdk\EventFactory;
use MediaWiki\Extension\TestKitchen\Sdk\EventSender;
use MediaWiki\Extension\TestKitchen\Sdk\Experiment;
use MediaWiki\Extension\TestKitchen\Sdk\ExperimentManager;
use MediaWiki\Extension\TestKitchen\Sdk\OverriddenExperiment;
use MediaWiki\Extension\TestKitchen\Sdk\StreamConfigs;
use MediaWiki\Extension\TestKitchen\Sdk\UnenrolledExperiment;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Request\WebRequest;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Psr\Log\LoggerInterface;
use Wikimedia\Assert\ParameterAssertionException;
use Wikimedia\Stats\StatsFactory;

/**
 * @covers \MediaWiki\Extension\TestKitchen\Sdk\ExperimentManager
 */
class ExperimentManagerTest extends MediaWikiUnitTestCase {
	private LoggerInterface $logger;
	private EventSender $eventSender;
	private EventFactory $eventFactory;
	private RequestEnrollmentsProcessor $requestEnrollmentsProcessor;
	private EnrollmentsProcessor $enrollmentsProcessor;
	private CentralIdLookup $centralIdLookup;
	private ConfigsFetcher $configsFetcher;
	private StatsFactory $statsFactory;
	private StreamConfigs $staticStreamConfigs;
	private ExperimentManager $experimentManager;

	public function setUp(): void {
		parent::setUp();

		$this->logger = $this->createMock( LoggerInterface::class );
		$this->eventSender = $this->createMock( EventSender::class );
		$this->eventFactory = $this->createMock( EventFactory::class );
		$this->requestEnrollmentsProcessor = $this->createMock( RequestEnrollmentsProcessor::class );
		$this->enrollmentsProcessor = $this->createMock( EnrollmentsProcessor::class );
		$this->centralIdLookup = $this->createMock( CentralIdLookup::class );
		$this->configsFetcher = $this->createMock( ConfigsFetcher::class );
		$this->statsFactory = StatsFactory::newNull();

		$baseStreamConfigs = new BaseStreamConfigs(
			[
				'product_metrics.web_base' => [
					'producers' => [
						'metrics_platform_client' => [
							'provide_values' => [
								'agent_client_platform',
								'agent_client_platform_family',
							]
						],
					],
				],
			],
			[]
		);
		$this->staticStreamConfigs = new StreamConfigs( $baseStreamConfigs );

		$this->experimentManager = new ExperimentManager(
			$this->logger,
			$this->eventSender,
			$this->eventFactory,
			$this->statsFactory,
			$this->requestEnrollmentsProcessor,
			$this->enrollmentsProcessor,
			$this->centralIdLookup,
			$this->configsFetcher,
			$this->staticStreamConfigs
		);
	}

	public function testEnrollmentsAreEmptyByDefault(): void {
		$expected = new EnrollmentResultBuilder();

		$this->assertEquals( $expected, $this->experimentManager->getEnrollments() );
	}

	/**
	 * Tests that the request is passed to the request enrollments processor and the result is used to re-initialize
	 * the enrollments.
	 */
	public function testEnrollmentsAreSetBySetRequest(): void {
		$request = $this->createMock( WebRequest::class );
		$expected = new EnrollmentResultBuilder();

		$this->requestEnrollmentsProcessor->expects( $this->once() )
			->method( 'process' )
			->with( $request )
			->willReturn( $expected );

		$this->experimentManager->setRequest( $request );

		$this->assertEquals( $expected, $this->experimentManager->getEnrollments() );
	}

	/**
	 * Tests that experiment configs are fetched and that the identifier is passed to the enrollments processor.
	 */
	public function testEnrollmentsAreUpdatedByUpdateIdentifier(): void {
		$identifier = 1234567890;
		$experimentConfigs = [
			[
				'name' => 'foo',
				'groups' => [
					'control',
					'treatment',
				],
				'sample' => [
					'rate' => 0.5,
				]
			]
		];

		$expected = $this->experimentManager->getEnrollments();

		$this->configsFetcher->expects( $this->once() )
			->method( 'getExperimentConfigs' )
			->willReturn( $experimentConfigs );

		$this->enrollmentsProcessor->expects( $this->once() )
			->method( 'process' )
			->with(
				'mw-user',
				$identifier,
				$experimentConfigs,
				$expected
			);

		$this->experimentManager->updateIdentifier( 'mw-user', $identifier );
	}

	public function testUpdateIdentifierThrowsWhenIdentifierTypeIsInvalid(): void {
		$this->expectException( ParameterAssertionException::class );
		$this->expectExceptionMessage( 'The identifier type must be "mw-user"' );

		$this->experimentManager->updateIdentifier( 'invalid-identifier-type', '0x0ff1ce' );
	}

	public function testGetExperiment(): void {
		// Data
		// ----

		$request = new FauxRequest();
		$enrollments = new EnrollmentResultBuilder();
		$experimentConfigs = [
			[
				'name' => 'dessert',
				'groups' => [
					'control',
					'treatment',
				],
				'sample' => [
					'rate' => 1,
				],
			],
		];

		// Mocks
		// -----

		// Called by ExperimentManager::setRequest()
		$this->requestEnrollmentsProcessor->expects( $this->once() )
			->method( 'process' )
			->with( $request )
			->willReturn( $enrollments );

		// Called by ExperimentManager::updateIdentifier()
		$this->configsFetcher->expects( $this->once() )
			->method( 'getExperimentConfigs' )
			->willReturn( $experimentConfigs );

		$this->enrollmentsProcessor->expects( $this->once() )
			->method( 'process' )
			->with(
				'mw-user',
				1234567890,
				$experimentConfigs,
				$enrollments
			)
			->willReturnCallback( static function () use ( $enrollments ) {
				$enrollments->addExperiment(
					'dessert',
					'603c456f34744aac87bf1f086eb46e8f9f0ba7330f5f72c38e3f8031ccd95397',
					'mw-user'
				);
				$enrollments->addAssignment( 'dessert', 'control' );
			} );

		// Code Under Test
		// ---------------

		$this->experimentManager->setRequest( $request );

		$user = UserIdentityValue::newRegistered( 1234567890, self::class );
		$this->experimentManager->updateUser( $user, false );

		$actual = $this->experimentManager->getExperiment( 'dessert' );

		// Assertions
		// ----------

		$expected = new Experiment(
			$this->eventSender,
			$this->eventFactory,
			$this->statsFactory,
			$this->staticStreamConfigs,
			[
				'enrolled' => 'dessert',
				'assigned' => 'control',
				'subject_id' => '603c456f34744aac87bf1f086eb46e8f9f0ba7330f5f72c38e3f8031ccd95397',
				'sampling_unit' => 'mw-user',
				'coordinator' => 'default',
				'stream_name' => 'product_metrics.web_base',
				'schema_id' => '/analytics/product_metrics/web/base/2.0.0',
				'contextual_attributes' => [
					'agent_client_platform',
					'agent_client_platform_family',
				],
			]
		);

		$this->assertEquals( $expected, $actual );
	}

	public function testGetOverriddenExperiment(): void {
		// Data
		// ----

		$request = new FauxRequest();

		$enrollments = new EnrollmentResultBuilder();
		$enrollments->addExperiment( 'main-course', 'overridden', 'overridden' );
		$enrollments->addAssignment( 'main-course', 'control', true );

		// Mocks
		// -----

		// Called by ExperimentManager::setRequest()
		$this->requestEnrollmentsProcessor->expects( $this->once() )
			->method( 'process' )
			->with( $request )
			->willReturn( $enrollments );

		// Code Under Test
		// ---------------

		$this->experimentManager->setRequest( $request );

		$actual = $this->experimentManager->getExperiment( 'main-course' );

		// Assertions
		// ----------

		$expected = new OverriddenExperiment(
			$this->eventSender,
			$this->eventFactory,
			$this->statsFactory,
			$this->staticStreamConfigs,
			$this->logger,
			[
				'enrolled' => 'main-course',
				'assigned' => 'control',
				'subject_id' => 'overridden',
				'sampling_unit' => 'overridden',
				'coordinator' => 'forced',
				'stream_name' => 'product_metrics.web_base',
				'schema_id' => '/analytics/product_metrics/web/base/2.0.0',
				'contextual_attributes' => [
					'agent_client_platform',
					'agent_client_platform_family',
				],
			]
		);

		$this->assertEquals( $expected, $actual );

		$this->assertEquals( 'control', $actual->getAssignedGroup() );
		$this->assertTrue( $actual->isAssignedGroup( 'control' ) );
	}

	public function testGetExperimentLogsInformationalMessageActiveExperiment(): void {
		// Data
		// ----

		$request = new FauxRequest();

		$enrollments = new EnrollmentResultBuilder();
		$enrollments->addExperiment(
			'active-but-not-enrolled',
			'1234567890abcdef',
			'mw-user'
		);

		// Mocks
		// -----

		// Called by ExperimentManager::setRequest()
		$this->requestEnrollmentsProcessor->expects( $this->once() )
			->method( 'process' )
			->with( $request )
			->willReturn( $enrollments );

		// Called by ExperimentManager::getExperiment()
		$this->logger->expects( $this->once() )
			->method( 'info' )
			->with(
				'The current user is not enrolled in the active-but-not-enrolled experiment'
			);

		// Assertions
		// ----------

		$this->experimentManager->setRequest( $request );

		$actual = $this->experimentManager->getExperiment( 'active-but-not-enrolled' );

		$expected = new UnenrolledExperiment(
			$this->eventSender,
			$this->eventFactory,
			$this->statsFactory,
			$this->staticStreamConfigs
		);

		$this->assertEquals( $expected, $actual );

		$this->assertNull( $actual->getAssignedGroup() );
		$this->assertFalse( $actual->isAssignedGroup( 'control' ) );
	}

	public function testSendLogsInformationalMessageOverriddenExperiment(): void {
		// Data
		// ----

		$request = new FauxRequest();

		$enrollments = new EnrollmentResultBuilder();
		$enrollments->addExperiment( 'main-course', 'overridden', 'overridden' );
		$enrollments->addAssignment( 'main-course', 'control', true );

		// Mocks
		// -----

		// Called by ExperimentManager::setRequest()
		$this->requestEnrollmentsProcessor->expects( $this->once() )
			->method( 'process' )
			->with( $request )
			->willReturn( $enrollments );

		// Called by Experiment::send()
		$this->logger->expects( $this->once() )
			->method( 'info' )
			->with(
				'main-course: The enrolment for this experiment has been overridden. ' .
				'The following event will not be sent'
			);

		// Code Under Test
		// ---------------

		$this->experimentManager->setRequest( $request );

		$actualExperiment = $this->experimentManager->getExperiment( 'main-course' );
		$actualExperiment->send( 'some-action' );
	}
}
