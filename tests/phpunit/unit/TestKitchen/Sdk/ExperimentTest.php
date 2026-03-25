<?php

namespace MediaWiki\Extension\TestKitchen\Tests\Unit\TestKitchen\Sdk;

use MediaWiki\Extension\EventStreamConfig\StreamConfigs as BaseStreamConfigs;
use MediaWiki\Extension\TestKitchen\Sdk\EventFactory;
use MediaWiki\Extension\TestKitchen\Sdk\EventSender;
use MediaWiki\Extension\TestKitchen\Sdk\Experiment;
use MediaWiki\Extension\TestKitchen\Sdk\ExposureLogTracker;
use MediaWiki\Extension\TestKitchen\Sdk\StreamConfigs;
use MediaWikiUnitTestCase;
use Wikimedia\Stats\StatsFactory;
use Wikimedia\Stats\UnitTestingHelper;
use Wikimedia\Timestamp\ConvertibleTimestamp;
use Wikimedia\Timestamp\TimestampFormat;

/**
 * @covers \MediaWiki\Extension\TestKitchen\Sdk\Experiment
 */
class ExperimentTest extends MediaWikiUnitTestCase {

	/** @var array */
	private $experimentConfig = [
		'enrolled' => "test_experiment",
		'assigned' => "treatment",
		'subject_id' => "asdfqwerty",
		'sampling_unit' => "mw-user",
		'other_assigned' => [ "another_experiment", "yet_another_experiment" ],
		'coordinator' => "default",
		'stream_name' => 'product_metrics.web_base',
		'schema_id' => '/analytics/product_metrics/web/base/2.0.0',
		'contextual_attributes' => [
			'agent_client_platform',
			'agent_client_platform_family',
		],
	];

	/** @var array */
	private $differentContextualAtributes = [
		'performer_id',
		'performer_edit_count',
	];

	/** @var Experiment */
	private $experiment;

	/** @var string */
	private $action = 'test_action';

	/** @var array */
	private $interactionData = [
		'action_source' => 'test_action_source',
		'action_context' => 'test_action_context',
	];

	private array $keys = [
		'enrolled',
		'assigned',
		'subject_id',
		'sampling_unit',
		'coordinator',
		'stream_name',
		'schema_id',
		'contextual_attributes'
	];

	private EventSender $eventSender;
	private EventFactory $eventFactory;
	private StatsFactory $statsFactory;
	private StreamConfigs $streamConfigs;
	private UnitTestingHelper $statsHelper;
	private ExposureLogTracker $exposureLogTracker;

	public function setUp(): void {
		parent::setUp();
		$this->eventSender = $this->createMock( EventSender::class );
		$this->eventFactory = $this->createMock( EventFactory::class );

		$this->statsHelper = StatsFactory::newUnitTestingHelper();
		$this->statsFactory = $this->statsHelper->getStatsFactory();
		$this->exposureLogTracker = $this->createMock( ExposureLogTracker::class );

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
				'product_metrics.custom_stream' => [
					'producers' => [
						'metrics_platform_client' => [
							'provide_values' => [
								'performer_id',
								'performer_edit_count',
							]
						],
					],
				],
			],
			[]
		);
		$this->streamConfigs = new StreamConfigs( $baseStreamConfigs );

		$this->experiment = new Experiment(
			$this->eventSender,
			$this->eventFactory,
			$this->statsFactory,
			$this->streamConfigs,
			$this->exposureLogTracker,
			$this->experimentConfig
		);
	}

	public function testGetAssignedGroupWithExperimentConfig() {
		$group = $this->experiment->getAssignedGroup();
		$this->assertEquals( 'treatment', $group );
	}

	public function testGetAssignedGroupWithNoExperimentConfig() {
		$experiment = new Experiment(
			$this->eventSender,
			$this->eventFactory,
			$this->statsFactory,
			$this->streamConfigs,
			$this->exposureLogTracker,
			[]
		);
		$group = $experiment->getAssignedGroup();
		$this->assertNull( $group );
	}

	public function testIsAssignedGroupInGroup() {
		$this->assertTrue( $this->experiment->isAssignedGroup( 'treatment', 'group_a', 'group_b' ) );
	}

	public function testIsAssignedGroupNotInGroup() {
		$this->assertFalse( $this->experiment->isAssignedGroup( 'group_a', 'group_b', 'group_c' ) );
	}

	public function testSendArgumentsDefault() {
		$expectedExperimentConfig = array_intersect_key(
			$this->experimentConfig,
			array_fill_keys( $this->keys, true )
		);

		$expectedEvent = [
			'$schema' => '/analytics/product_metrics/web/base/2.0.0',
			'dt' => ConvertibleTimestamp::now( TimestampFormat::ISO_8601 ),
		];

		$this->eventFactory->expects( $this->once() )
			->method( 'newEvent' )
			->with(
				'product_metrics.web_base',
				'/analytics/product_metrics/web/base/2.0.0',
				[
					'agent_client_platform',
					'agent_client_platform_family',
				],
				$this->action,
				array_merge(
					$this->interactionData,
					[ 'experiment' => $expectedExperimentConfig ]
				)
			)
			->willReturn( $expectedEvent );

		$this->eventSender
			->expects( $this->once() )
			->method( 'sendEvent' )
			->with( $expectedEvent );

		$this->experiment->send( $this->action, $this->interactionData );

		$this->assertSame(
			[ 'mediawiki.TestKitchen.experiment_events_sent_total:1|c|#experiment:test_experiment' ],
			$this->statsHelper->consumeAllFormatted()
		);
	}

	public function testSendArgumentsNoInteractionData() {
		$expectedExperimentConfig = array_intersect_key(
			$this->experimentConfig,
			array_fill_keys( $this->keys, true )
		);

		$expectedEvent = [
			'$schema' => '/analytics/product_metrics/web/base/2.0.0',
			'dt' => ConvertibleTimestamp::now( TS_ISO_8601 ),
		];

		$this->eventFactory->expects( $this->once() )
			->method( 'newEvent' )
			->with(
				'product_metrics.web_base',
				'/analytics/product_metrics/web/base/2.0.0',
				[
					'agent_client_platform',
					'agent_client_platform_family',
				],
				$this->action,
				[ 'experiment' => $expectedExperimentConfig ]
			)
			->willReturn( $expectedEvent );

		$this->eventSender
			->expects( $this->once() )
			->method( 'sendEvent' )
			->with( $expectedEvent );

		$this->experiment->send( $this->action );

		$this->assertSame(
			[ 'mediawiki.TestKitchen.experiment_events_sent_total:1|c|#experiment:test_experiment' ],
			$this->statsHelper->consumeAllFormatted()
		);
	}

	public function testSendArgumentsInteractionDataWithPerEventContextualAttributes() {
		$expectedExperimentConfig = array_intersect_key(
			$this->experimentConfig,
			array_fill_keys( $this->keys, true )
		);

		$expectedEvent = [
			'$schema' => '/analytics/product_metrics/web/base/2.0.0',
			'dt' => ConvertibleTimestamp::now( TS_ISO_8601 ),
		];

		$this->eventFactory->expects( $this->once() )
			->method( 'newEvent' )
			->with(
				'product_metrics.web_base',
				'/analytics/product_metrics/web/base/2.0.0',
				[
					'agent_client_platform',
					'agent_client_platform_family',
					'performer_is_bot',
					'performer_id'
				],
				$this->action,
				array_merge(
					$this->interactionData,
					[ 'experiment' => $expectedExperimentConfig ]
				)
			)
			->willReturn( $expectedEvent );

		$this->eventSender
			->expects( $this->once() )
			->method( 'sendEvent' )
			->with( $expectedEvent );

		$this->experiment->send( $this->action, $this->interactionData, [ 'performer_is_bot', 'performer_id' ] );

		$this->assertSame(
			[ 'mediawiki.TestKitchen.experiment_events_sent_total:1|c|#experiment:test_experiment' ],
			$this->statsHelper->consumeAllFormatted()
		);
	}

	public function testSendArgumentsNoInteractionDataWithPerEventContextualAttributes() {
		$expectedExperimentConfig = array_intersect_key(
			$this->experimentConfig,
			array_fill_keys( $this->keys, true )
		);

		$expectedEvent = [
			'$schema' => '/analytics/product_metrics/web/base/2.0.0',
			'dt' => ConvertibleTimestamp::now( TS_ISO_8601 ),
		];

		$this->eventFactory->expects( $this->once() )
			->method( 'newEvent' )
			->with(
				'product_metrics.web_base',
				'/analytics/product_metrics/web/base/2.0.0',
				[
					'agent_client_platform',
					'agent_client_platform_family',
					'performer_is_bot',
					'performer_id'
				],
				$this->action,
				[ 'experiment' => $expectedExperimentConfig ]
			)
			->willReturn( $expectedEvent );

		$this->eventSender
			->expects( $this->once() )
			->method( 'sendEvent' )
			->with( $expectedEvent );

		$this->experiment->send( $this->action, contextualAttributes: [ 'performer_is_bot', 'performer_id' ] );

		$this->assertSame(
			[ 'mediawiki.TestKitchen.experiment_events_sent_total:1|c|#experiment:test_experiment' ],
			$this->statsHelper->consumeAllFormatted()
		);
	}

	public function testSendArgumentsWithEmptyExperimentConfig() {
		$experiment = new Experiment(
			$this->eventSender,
			$this->eventFactory,
			$this->statsFactory,
			$this->streamConfigs,
			$this->exposureLogTracker,
			[]
		);

		$this->eventFactory
			->expects( $this->never() )
			->method( 'newEvent' );

		$this->eventSender
			->expects( $this->never() )
			->method( 'sendEvent' );

		$experiment->send( $this->action, $this->interactionData );

		$this->assertSame( null, $experiment->getAssignedGroup() );
		$this->assertSame( false, $experiment->isAssignedGroup( 'control', 'treatment' ) );

		$this->assertSame(
			[],
			$this->statsHelper->consumeAllFormatted()
		);
	}

	public function testSetStream(): void {
		$newStream = 'product_metrics.custom_stream';

		$return = $this->experiment->setStream( $newStream );

		$this->assertSame( $this->experiment, $return );
		$this->assertSame(
			$newStream,
			$this->experiment->getExperimentConfig()['stream_name']
		);
	}

	public function testSetSchema(): void {
		$newSchema = '/analytics/product_metrics/web/custom/1.0.0';

		$return = $this->experiment->setSchema( $newSchema );

		$this->assertSame( $this->experiment, $return );
		$this->assertSame(
			$newSchema,
			$this->experiment->getExperimentConfig()['schema_id']
		);
	}

	public function testSetStreamContextualAttributesAndSend(): void {
		$newStream = 'product_metrics.custom_stream';
		$return = $this->experiment->setStream( $newStream, $this->differentContextualAtributes );

		$expectedExperimentConfig = array_intersect_key(
			$this->experiment->getExperimentConfig(),
			array_fill_keys( $this->keys, true )
		);
		$expectedExperimentConfig['contextual_attributes'] = $this->differentContextualAtributes;

		$expectedEvent = [
			'$schema' => $this->experimentConfig['schema_id'],
			'dt' => ConvertibleTimestamp::now( TimestampFormat::ISO_8601 ),
		];

		$this->eventFactory->expects( $this->once() )
			->method( 'newEvent' )
			->with(
				$newStream,
				$this->experimentConfig['schema_id'],
				$this->differentContextualAtributes,
				$this->action,
				array_merge( $this->interactionData, [ 'experiment' => $expectedExperimentConfig ] )
			)
			->willReturn( $expectedEvent );

		$this->eventSender->expects( $this->once() )
			->method( 'sendEvent' )
			->with( $expectedEvent );

		$this->assertSame( $this->experiment, $return );

		$this->assertSame(
			$newStream,
			$this->experiment->getExperimentConfig()['stream_name'] ?? null,
			'setStream() should update experimentConfig stream_name'
		);

		$this->assertSame(
			$this->differentContextualAtributes,
			$this->experiment->getExperimentConfig()['contextual_attributes'] ?? null,
			'setStream() should update experimentConfig contextual_attributes'
		);

		$this->experiment->send( $this->action, $this->interactionData );

		$this->assertSame(
			[ 'mediawiki.TestKitchen.experiment_events_sent_total:1|c|#experiment:test_experiment' ],
			$this->statsHelper->consumeAllFormatted()
		);
	}

	public function testSetSchemaAndSend(): void {
		$newSchema = '/analytics/product_metrics/web/custom/1.0.0';

		$expectedEvent = [
			'$schema' => $newSchema,
			'dt' => ConvertibleTimestamp::now( TimestampFormat::ISO_8601 ),
		];

		$return = $this->experiment->setSchema( $newSchema );
		$this->assertSame( $this->experiment, $return );

		$this->assertSame(
			$newSchema,
			$this->experiment->getExperimentConfig()['schema_id'] ?? null,
			'setSchema() should update experimentConfig schema_id'
		);

		$expectedExperimentConfig = array_intersect_key(
			$this->experiment->getExperimentConfig(),
			array_fill_keys( $this->keys, true )
		);

		$this->eventFactory->expects( $this->once() )
			->method( 'newEvent' )
			->with(
				$this->experimentConfig['stream_name'],
				$newSchema,
				$this->experimentConfig['contextual_attributes'],
				$this->action,
				array_merge( $this->interactionData, [ 'experiment' => $expectedExperimentConfig ] )
			)
			->willReturn( $expectedEvent );

		$this->eventSender->expects( $this->once() )
			->method( 'sendEvent' )
			->with( $expectedEvent );

		$this->experiment->send( $this->action, $this->interactionData );

		$this->assertSame(
			[ 'mediawiki.TestKitchen.experiment_events_sent_total:1|c|#experiment:test_experiment' ],
			$this->statsHelper->consumeAllFormatted()
		);
	}

	public function testSendExposure(): void {
		$expectedExperimentConfig = array_intersect_key(
			$this->experimentConfig,
			array_fill_keys( $this->keys, true )
		);

		$expectedEvent = [
			'$schema' => '/analytics/product_metrics/web/base/2.0.0',
			'dt' => ConvertibleTimestamp::now( TimestampFormat::ISO_8601 ),
			'action' => 'experiment_exposure',
			'experiment' => $expectedExperimentConfig
		];

		$expectedExposureKey = 'tk.exposure.' .
			$this->experimentConfig['enrolled'] . ':' .
			$this->experimentConfig['assigned'];

		$this->exposureLogTracker->expects( $this->once() )
			->method( 'makeKey' )
			->with(
				$this->experimentConfig['enrolled'],
				$this->experimentConfig['assigned']
			)
			->willReturn( $expectedExposureKey );

		$this->exposureLogTracker->expects( $this->once() )
			->method( 'checkShouldSend' )
			->with( $expectedExposureKey )
			->willReturn( true );

		$this->eventFactory->expects( $this->once() )
			->method( 'newEvent' )
			->with(
				'product_metrics.web_base',
				'/analytics/product_metrics/web/base/2.0.0',
				array_unique(
					array_merge(
						$this->experimentConfig[ 'contextual_attributes' ],
						[ 'performer_is_logged_in', 'performer_is_temp', 'performer_is_bot', 'mediawiki_database' ]
					)
				),
				'experiment_exposure',
				[ 'experiment' => $expectedExperimentConfig ]
			)
			->willReturn( $expectedEvent );

		$expectedEvent['action'] = 'experiment_exposure';

		$this->eventSender->expects( $this->once() )
			->method( 'sendEvent' )
			->with( $expectedEvent );

		$this->exposureLogTracker->expects( $this->once() )
			->method( 'addLog' )
			->with( $expectedExposureKey );

		$this->experiment->sendExposure();

		$this->assertSame(
			[ 'mediawiki.TestKitchen.experiment_events_sent_total:1|c|#experiment:test_experiment' ],
			$this->statsHelper->consumeAllFormatted()
		);
	}

	public function testSendExposureWithInvalidExperimentConfig(): void {
		$experiment = new Experiment(
			$this->eventSender,
			$this->eventFactory,
			$this->statsFactory,
			$this->streamConfigs,
			$this->exposureLogTracker,
			[]
		);

		$this->eventFactory->expects( $this->never() )
			->method( 'newEvent' );

		$experiment->sendExposure();
	}
}
