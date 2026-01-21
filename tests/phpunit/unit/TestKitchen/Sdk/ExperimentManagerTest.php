<?php

namespace MediaWiki\Extension\TestKitchen\Tests\Unit\TestKitchen\Sdk;

use MediaWiki\Extension\EventStreamConfig\StreamConfigs as BaseStreamConfigs;
use MediaWiki\Extension\TestKitchen\Coordination\EnrollmentResultBuilder;
use MediaWiki\Extension\TestKitchen\Sdk\EventFactory;
use MediaWiki\Extension\TestKitchen\Sdk\EventSender;
use MediaWiki\Extension\TestKitchen\Sdk\Experiment;
use MediaWiki\Extension\TestKitchen\Sdk\ExperimentManager;
use MediaWiki\Extension\TestKitchen\Sdk\OverriddenExperiment;
use MediaWiki\Extension\TestKitchen\Sdk\StreamConfigs;
use MediaWiki\Extension\TestKitchen\Sdk\UnenrolledExperiment;
use MediaWikiUnitTestCase;
use Psr\Log\LoggerInterface;
use Wikimedia\Stats\StatsFactory;

/**
 * @covers \MediaWiki\Extension\TestKitchen\Sdk\ExperimentManager
 */
class ExperimentManagerTest extends MediaWikiUnitTestCase {
	private LoggerInterface $logger;
	private EventSender $eventSender;
	private EventFactory $eventFactory;
	private StatsFactory $statsFactory;
	private StreamConfigs $staticStreamConfigs;
	private ExperimentManager $experimentManager;

	public function setUp(): void {
		parent::setUp();

		$this->logger = $this->createMock( LoggerInterface::class );
		$this->eventSender = $this->createMock( EventSender::class );
		$this->eventFactory = $this->createMock( EventFactory::class );
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
			$this->staticStreamConfigs
		);

		$enrollmentResult = new EnrollmentResultBuilder();

		$enrollmentResult->addExperiment( 'main-course', 'overridden', 'overridden' );
		$enrollmentResult->addAssignment( 'main-course', 'control', true );

		$enrollmentResult->addExperiment(
			'dessert',
			'603c456f34744aac87bf1f086eb46e8f9f0ba7330f5f72c38e3f8031ccd95397',
			'mw-user'
		);
		$enrollmentResult->addAssignment(
			'dessert',
			'control'
		);

		$enrollmentResult->addExperiment(
			'active-but-not-enrolled',
			'603c456f34744aac87bf1f086eb46e8f9f0ba7330f5f72c38e3f8031ccd95397',
			'mw-user'
		);

		$this->experimentManager->initialize( $enrollmentResult->build() );
	}

	public function testGetExperiment(): void {
		$expectedExperiment = new Experiment(
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
		$actualExperiment = $this->experimentManager->getExperiment( 'dessert' );

		$this->assertEquals( $expectedExperiment, $actualExperiment );
	}

	public function testGetOverriddenExperiment(): void {
		$expectedExperiment = new OverriddenExperiment(
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
		$actualExperiment = $this->experimentManager->getExperiment( 'main-course' );

		$this->assertEquals( $expectedExperiment, $actualExperiment );

		$this->assertEquals( 'control', $expectedExperiment->getAssignedGroup() );
		$this->assertTrue( $expectedExperiment->isAssignedGroup( 'control' ) );
	}

	public function testGetExperimentLogsInformationalMessageActiveExperiment(): void {
		$this->logger->expects( $this->once() )
			->method( 'info' )
			->with(
				'The current user is not enrolled in the active-but-not-enrolled experiment'
			);

		$expectedExperiment = new UnenrolledExperiment(
			$this->eventSender,
			$this->eventFactory,
			$this->statsFactory,
			$this->staticStreamConfigs
		);
		$actualExperiment = $this->experimentManager->getExperiment( 'active-but-not-enrolled' );

		$this->assertEquals( $expectedExperiment, $actualExperiment );

		$this->assertNull( $expectedExperiment->getAssignedGroup() );
		$this->assertFalse( $expectedExperiment->isAssignedGroup( 'control' ) );
	}

	public function testSendLogsInformationalMessageOverriddenExperiment(): void {
		$this->logger->expects( $this->once() )
			->method( 'info' )
			->with(
				'main-course: The enrolment for this experiment has been overridden. ' .
				'The following event will not be sent'
			);

		$actualExperiment = $this->experimentManager->getExperiment( 'main-course' );
		$actualExperiment->send( 'some-action' );
	}
}
