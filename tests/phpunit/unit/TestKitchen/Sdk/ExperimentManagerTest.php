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
use MediaWiki\Extension\TestKitchen\Sdk\ExposureLogTracker;
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
use Wikimedia\Stats\UnitTestingHelper;

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
	private UnitTestingHelper $statsHelper;
	private StatsFactory $statsFactory;
	private StreamConfigs $staticStreamConfigs;
	private ExperimentManager $experimentManager;
	private ExposureLogTracker $exposureLogTracker;

	protected function setUp(): void {
		parent::setUp();

		$this->logger = $this->createMock( LoggerInterface::class );
		$this->eventSender = $this->createMock( EventSender::class );
		$this->eventFactory = $this->createMock( EventFactory::class );
		$this->requestEnrollmentsProcessor = $this->createMock( RequestEnrollmentsProcessor::class );
		$this->enrollmentsProcessor = $this->createMock( EnrollmentsProcessor::class );
		$this->centralIdLookup = $this->createMock( CentralIdLookup::class );
		$this->configsFetcher = $this->createMock( ConfigsFetcher::class );
		$this->statsHelper = StatsFactory::newUnitTestingHelper();
		$this->statsFactory = $this->statsHelper->getStatsFactory();
		$this->exposureLogTracker = $this->createMock( ExposureLogTracker::class );

		$baseStreamConfigs = new BaseStreamConfigs(
			[
				'product_metrics.web_base' => [
					'schema_title' => 'analytics/product_metrics/web/base',
					'producers' => [
						'metrics_platform_client' => [
							'provide_values' => [
								'agent_client_platform',
								'agent_client_platform_family',
							],
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
			$this->staticStreamConfigs,
			$this->exposureLogTracker
		);
	}

	public function testEnrollmentsAreEmptyByDefault(): void {
		$this->assertEquals(
			new EnrollmentResultBuilder(),
			$this->experimentManager->getEnrollments()
		);
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
		$identifier = '1234567890';
		$experimentConfigs = [
			'foo' => $this->makeExperimentConfig( 'foo' ),
		];

		$expectedEnrollments = $this->experimentManager->getEnrollments();

		$this->configsFetcher->expects( $this->once() )
			->method( 'getExperimentConfigs' )
			->willReturn( $experimentConfigs );

		$this->enrollmentsProcessor->expects( $this->once() )
			->method( 'process' )
			->with(
				'mw-user',
				$identifier,
				$experimentConfigs,
				$expectedEnrollments
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
		$experimentName = 'dessert';

		$experimentConfigs = [
			$experimentName => $this->makeExperimentConfig(
				$experimentName,
				[
					'contextual_attributes' => [
						'page_is_redirect',
						'performer_is_logged_in',
						'performer_is_temp',
					],
				]
			),
		];

		// Mocks
		// -----

		// Called by ExperimentManager::setRequest()
		$this->requestEnrollmentsProcessor->expects( $this->once() )
			->method( 'process' )
			->with( $request )
			->willReturn( $enrollments );

		// Called by ExperimentManager::updateIdentifier()
		$this->configsFetcher->expects( $this->any() )
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
			->willReturnCallback( static function () use ( $enrollments, $experimentName ) {
				$enrollments->addExperiment(
					$experimentName,
					'603c456f34744aac87bf1f086eb46e8f9f0ba7330f5f72c38e3f8031ccd95397'
				);
				$enrollments->addAssignment( $experimentName, 'control' );
			} );

		// Code Under Test
		// ---------------

		$this->experimentManager->setRequest( $request );

		$user = UserIdentityValue::newRegistered( 1234567890, self::class );
		$this->experimentManager->updateUser( $user, false );

		$actual = $this->experimentManager->getExperiment( $experimentName );

		// Assertions
		// ----------

		$expected = new Experiment(
			$this->eventSender,
			$this->eventFactory,
			$this->statsFactory,
			$this->staticStreamConfigs,
			$this->exposureLogTracker,
			$this->makeExpectedSdkConfig(
				$experimentName,
				[
					'assigned' => 'control',
					'subject_id' => '603c456f34744aac87bf1f086eb46e8f9f0ba7330f5f72c38e3f8031ccd95397',
					'contextual_attributes' => [
						'page_is_redirect',
						'performer_is_logged_in',
						'performer_is_temp',
					],
				]
			)
		);

		$this->assertEquals( $expected, $actual );

		$this->assertSame(
			[ 'mediawiki.TestKitchen.experiment_known:1|c|#experiment:dessert' ],
			$this->statsHelper->consumeAllFormatted()
		);
	}

	public function testGetOverriddenExperiment(): void {
		// Data
		// ----

		$experimentName = 'main-course';
		$request = new FauxRequest();
		$enrollments = new EnrollmentResultBuilder();

		// Called by ExperimentManager::setRequest()
		$enrollments->addExperiment( $experimentName, 'overridden' );
		$enrollments->addAssignment( $experimentName, 'control', true );

		$experimentConfigs = [
			$experimentName => $this->makeExperimentConfig(
				$experimentName,
				[
					'contextual_attributes' => [
						'page_title',
						'performer_is_logged_in'
					],
				]
			),
		];

		// Mocks
		// -----

		$this->configsFetcher->expects( $this->any() )
			->method( 'getExperimentConfigs' )
			->willReturn( $experimentConfigs );

		$this->requestEnrollmentsProcessor->expects( $this->once() )
			->method( 'process' )
			->with( $request )
			->willReturn( $enrollments );

		// Code Under Test
		// ---------------

		$this->experimentManager->setRequest( $request );

		// Assertions
		// ----------

		$actual = $this->experimentManager->getExperiment( $experimentName );

		$expected = new OverriddenExperiment(
			$this->eventSender,
			$this->eventFactory,
			$this->statsFactory,
			$this->staticStreamConfigs,
			$this->logger,
			$this->exposureLogTracker,
			$this->makeExpectedSdkConfig(
				$experimentName,
				[
					'assigned' => 'control',
					'subject_id' => 'overridden',
					'sampling_unit' => 'overridden',
					'coordinator' => 'forced',
					'contextual_attributes' => [],
				]
			)
		);

		$this->assertEquals( $expected, $actual );

		$this->assertEquals( 'control', $actual->getAssignedGroup() );
		$this->assertTrue( $actual->isAssignedGroup( 'control' ) );
	}

	public function testGetExperimentLogsInformationalMessageActiveExperiment(): void {
		// Data
		// ----

		$request = new FauxRequest();
		$experimentName = 'active-but-not-enrolled';
		$enrollments = new EnrollmentResultBuilder();

		$experimentConfigs = [
			$experimentName => $this->makeExperimentConfig(
				$experimentName,
				[
					'contextual_attributes' => [
						'page_id',
						'performer_is_temp',
					],
				]
			),
		];

		// Mocks
		// -----

		$this->configsFetcher->expects( $this->any() )
			->method( 'getExperimentConfigs' )
			->willReturn( $experimentConfigs );

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

		$actual = $this->experimentManager->getExperiment( $experimentName );

		$expected = new UnenrolledExperiment(
			$this->eventSender,
			$this->eventFactory,
			$this->statsFactory,
			$this->staticStreamConfigs,
			$this->exposureLogTracker
		);

		$this->assertEquals( $expected, $actual );

		$this->assertNull( $actual->getAssignedGroup() );
		$this->assertFalse( $actual->isAssignedGroup( 'control' ) );

		$this->assertSame(
			[ 'mediawiki.TestKitchen.experiment_known:1|c|#experiment:active_but_not_enrolled' ],
			$this->statsHelper->consumeAllFormatted()
		);
	}

	public function testGetExperimentReturnsUnenrolledExperimentForUnknownExperiment(): void {
		$this->configsFetcher->expects( $this->once() )
			->method( 'getExperimentConfigs' )
			->willReturn( [] );

		$actual = $this->experimentManager->getExperiment( 'does-not-exist' );

		$expected = new UnenrolledExperiment(
			$this->eventSender,
			$this->eventFactory,
			$this->statsFactory,
			$this->staticStreamConfigs,
			$this->exposureLogTracker
		);

		$this->assertEquals( $expected, $actual );

		$this->assertSame(
			[ 'mediawiki.TestKitchen.experiment_unknown:1|c|#experiment:does_not_exist' ],
			$this->statsHelper->consumeAllFormatted()
		);
	}

	public function testGetExperimentReturnsUnenrolledExperimentForUnknownExperimentButUserIsEnrolled(): void {
		// Data
		// ----

		$request = new FauxRequest();
		$experimentName = 'unknown-but-enrolled';
		$experimentConfigs = [];

		$enrollments = new EnrollmentResultBuilder();
		$enrollments->addExperiment( $experimentName, 'awaiting' );
		$enrollments->addAssignment( $experimentName, 'control' );

		// Mocks
		// -----

		$this->configsFetcher->expects( $this->any() )
			->method( 'getExperimentConfigs' )
			->willReturn( $experimentConfigs );

		// Called by ExperimentManager::setRequest()
		$this->requestEnrollmentsProcessor->expects( $this->once() )
			->method( 'process' )
			->with( $request )
			->willReturn( $enrollments );

		// Assertions
		// ----------

		$this->experimentManager->setRequest( $request );

		$actual = $this->experimentManager->getExperiment( $experimentName );

		$expected = new UnenrolledExperiment(
			$this->eventSender,
			$this->eventFactory,
			$this->statsFactory,
			$this->staticStreamConfigs,
			$this->exposureLogTracker
		);

		$this->assertEquals( $expected, $actual );

		$this->assertSame(
			[ 'mediawiki.TestKitchen.experiment_unknown:1|c|#experiment:unknown_but_enrolled' ],
			$this->statsHelper->consumeAllFormatted()
		);
	}

	public function testGetExperimentReturnsUnenrolledExperimentWhenTheUserIsNotEnrolled(): void {
		// Data
		// ----

		$request = new FauxRequest();
		$experimentName = 'known-but-unenrolled';
		$experimentConfigs = [
			$experimentName => $this->makeExperimentConfig( $experimentName ),
		];

		$enrollments = new EnrollmentResultBuilder();

		// Mocks
		// -----

		$this->configsFetcher->expects( $this->any() )
			->method( 'getExperimentConfigs' )
			->willReturn( $experimentConfigs );

		// Called by ExperimentManager::setRequest()
		$this->requestEnrollmentsProcessor->expects( $this->once() )
			->method( 'process' )
			->with( $request )
			->willReturn( $enrollments );

		// Assertions
		// ----------

		$this->experimentManager->setRequest( $request );

		$actual = $this->experimentManager->getExperiment( $experimentName );

		$expected = new UnenrolledExperiment(
			$this->eventSender,
			$this->eventFactory,
			$this->statsFactory,
			$this->staticStreamConfigs,
			$this->exposureLogTracker
		);

		$this->assertEquals( $expected, $actual );

		$this->assertSame(
			[ 'mediawiki.TestKitchen.experiment_known:1|c|#experiment:known_but_unenrolled' ],
			$this->statsHelper->consumeAllFormatted()
		);
	}

	public function testGetExperimentUsesExplicitSchemaIdWhenPresent(): void {
		$experimentName = 'with-custom-schema';
		$request = new FauxRequest();
		$enrollments = new EnrollmentResultBuilder();
		$enrollments->addExperiment( $experimentName, 'subject-123' );
		$enrollments->addAssignment( $experimentName, 'treatment' );

		$experimentConfigs = [
			$experimentName => $this->makeExperimentConfig(
				$experimentName,
				[
					'schema_id' => '/analytics/product_metrics/web/custom/1.0.0',
				]
			),
		];

		$this->configsFetcher->expects( $this->any() )
			->method( 'getExperimentConfigs' )
			->willReturn( $experimentConfigs );

		$this->requestEnrollmentsProcessor->expects( $this->once() )
			->method( 'process' )
			->with( $request )
			->willReturn( $enrollments );

		$this->experimentManager->setRequest( $request );

		$actual = $this->experimentManager->getExperiment( $experimentName );

		$expected = new Experiment(
			$this->eventSender,
			$this->eventFactory,
			$this->statsFactory,
			$this->staticStreamConfigs,
			$this->exposureLogTracker,
			$this->makeExpectedSdkConfig(
				$experimentName,
				[
					'assigned' => 'treatment',
					'subject_id' => 'subject-123',
					'schema_id' => '/analytics/product_metrics/web/custom/1.0.0',
				]
			)
		);

		$this->assertEquals( $expected, $actual );
	}

	public function testSendLogsInformationalMessageOverriddenExperiment(): void {
		// Data
		// ----

		$request = new FauxRequest();
		$experimentName = 'main-course';

		$enrollments = new EnrollmentResultBuilder();
		$enrollments->addExperiment( $experimentName, 'overridden', 'overridden' );
		$enrollments->addAssignment( $experimentName, 'control', true );

		$experimentConfigs = [
			$experimentName => $this->makeExperimentConfig(
				$experimentName,
				[
					'contextual_attributes' => [
						'page_id',
						'performer_is_temp',
					],
				]
			),
		];

		// Mocks
		// -----

		$this->configsFetcher->expects( $this->any() )
			->method( 'getExperimentConfigs' )
			->willReturn( $experimentConfigs );

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

		$experiment = $this->experimentManager->getExperiment( $experimentName );
		$experiment->send( 'some-action' );
	}

	/**
	 * @param string $name
	 * @param array $overrides
	 * @return array
	 */
	private function makeExperimentConfig( string $name, array $overrides = [] ): array {
		return array_replace(
			[
				'name' => $name,
				'user_identifier_type' => 'mw-user',
				'groups' => [
					'control',
					'treatment',
				],
				'sample_rate' => [
					'default' => 1,
				],
				'stream_name' => 'product_metrics.web_base',
				'contextual_attributes' => [
					'page_id',
					'performer_is_temp',
				],
			],
			$overrides
		);
	}

	/**
	 * @param string $experimentName
	 * @param array $overrides
	 * @return array
	 */
	private function makeExpectedSdkConfig( string $experimentName, array $overrides = [] ): array {
		return array_replace(
			[
				'enrolled' => $experimentName,
				'assigned' => 'control',
				'subject_id' => 'default-subject-id',
				'sampling_unit' => 'mw-user',
				'coordinator' => 'default',
				'stream_name' => 'product_metrics.web_base',
				'schema_id' => '/analytics/product_metrics/web/base/2.0.0',
				'contextual_attributes' => [
					'page_id',
					'performer_is_temp',
				],
			],
			$overrides
		);
	}
}
