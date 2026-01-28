<?php

namespace MediaWiki\Extension\TestKitchen\Tests\Unit\TestKitchen\Sdk;

use MediaWiki\Extension\EventLogging\EventSubmitter\EventSubmitter;
use MediaWiki\Extension\TestKitchen\Sdk\EventFactory;
use MediaWiki\Extension\TestKitchen\Sdk\Experiment;
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

	/** @var Experiment */
	private $experiment;

	/** @var string */
	private $action = 'test_action';

	/** @var array */
	private $interactionData = [
		'action_source' => 'test_action_source',
		'action_context' => 'test_action_context',
	];

	private EventSubmitter $eventSubmitter;
	private EventFactory $eventFactory;
	private StatsFactory $statsFactory;
	private UnitTestingHelper $statsHelper;

	public function setUp(): void {
		parent::setUp();
		$this->eventSubmitter = $this->createMock( EventSubmitter::class );
		$this->eventFactory = $this->createMock( EventFactory::class );

		$this->statsHelper = StatsFactory::newUnitTestingHelper();
		$this->statsFactory = $this->statsHelper->getStatsFactory();

		$this->experiment = new Experiment(
			$this->eventSubmitter,
			$this->eventFactory,
			$this->statsFactory,
			$this->experimentConfig
		);
	}

	public function testGetAssignedGroupWithExperimentConfig() {
		$group = $this->experiment->getAssignedGroup();
		$this->assertEquals( 'treatment', $group );
	}

	public function testGetAssignedGroupWithNoExperimentConfig() {
		$experiment = new Experiment(
			$this->eventSubmitter,
			$this->eventFactory,
			$this->statsFactory,
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
		$keys = [ 'enrolled', 'assigned', 'subject_id', 'sampling_unit', 'coordinator' ];
		$expectedExperimentConfig = array_intersect_key( $this->experimentConfig, array_fill_keys( $keys, true ) );

		$expectedEvent = [
			'$schema' => '/analytics/product_metrics/web/base/2.0.0',
			'dt' => ConvertibleTimestamp::now( TimestampFormat::ISO_8601 ),
		];

		$this->eventFactory->expects( $this->once() )
			->method( 'newEvent' )
			->with(
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

		$this->eventSubmitter
			->expects( $this->once() )
			->method( 'submit' )
			->with( 'product_metrics.web_base', $expectedEvent );

		$this->experiment->send( $this->action, $this->interactionData );

		$this->assertSame(
			[ 'mediawiki.TestKitchen.experiment_events_sent_total:1|c|#experiment:test_experiment' ],
			$this->statsHelper->consumeAllFormatted()
		);
	}

	public function testSendArgumentsNoInteractionData() {
		$keys = [ 'enrolled', 'assigned', 'subject_id', 'sampling_unit', 'coordinator' ];
		$expectedExperimentConfig = array_intersect_key( $this->experimentConfig, array_fill_keys( $keys, true ) );

		$expectedEvent = [
			'$schema' => '/analytics/product_metrics/web/base/2.0.0',
			'dt' => ConvertibleTimestamp::now( TS_ISO_8601 ),
		];

		$this->eventFactory->expects( $this->once() )
			->method( 'newEvent' )
			->with(
				'/analytics/product_metrics/web/base/2.0.0',
				[
					'agent_client_platform',
					'agent_client_platform_family',
				],
				$this->action,
				[ 'experiment' => $expectedExperimentConfig ]
			)
			->willReturn( $expectedEvent );

		$this->eventSubmitter
			->expects( $this->once() )
			->method( 'submit' )
			->with( 'product_metrics.web_base', $expectedEvent );

		$this->experiment->send( $this->action );

		$this->assertSame(
			[ 'mediawiki.TestKitchen.experiment_events_sent_total:1|c|#experiment:test_experiment' ],
			$this->statsHelper->consumeAllFormatted()
		);
	}

	public function testSendArgumentsWithEmptyExperimentConfig() {
		$experiment = new Experiment(
			$this->eventSubmitter,
			$this->eventFactory,
			$this->statsFactory,
			[]
		);

		$this->eventFactory
			->expects( $this->never() )
			->method( 'newEvent' );

		$this->eventSubmitter
			->expects( $this->never() )
			->method( 'submit' );

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

	public function testSetStreamAndSend(): void {
		$newStream = 'product_metrics.custom_stream';

		$keys = [ 'enrolled', 'assigned', 'subject_id', 'sampling_unit', 'coordinator' ];
		$expectedExperimentConfig = array_intersect_key( $this->experimentConfig, array_fill_keys( $keys, true ) );

		$expectedEvent = [
			'$schema' => $this->experimentConfig['schema_id'],
			'dt' => ConvertibleTimestamp::now( TimestampFormat::ISO_8601 ),
		];

		$this->eventFactory->expects( $this->once() )
			->method( 'newEvent' )
			->with(
				$this->experimentConfig['schema_id'],
				$this->experimentConfig['contextual_attributes'],
				$this->action,
				array_merge( $this->interactionData, [ 'experiment' => $expectedExperimentConfig ] )
			)
			->willReturn( $expectedEvent );

		$this->eventSubmitter->expects( $this->once() )
			->method( 'submit' )
			->with( $newStream, $expectedEvent );

		$return = $this->experiment->setStream( $newStream );
		$this->assertSame( $this->experiment, $return );

		$this->assertSame(
			$newStream,
			$this->experiment->getExperimentConfig()['stream_name'] ?? null,
			'setStream() should update experimentConfig stream_name'
		);

		$this->experiment->send( $this->action, $this->interactionData );

		$this->assertSame(
			[ 'mediawiki.TestKitchen.experiment_events_sent_total:1|c|#experiment:test_experiment' ],
			$this->statsHelper->consumeAllFormatted()
		);
	}

	public function testSetSchemaAndSend(): void {
		$newSchema = '/analytics/product_metrics/web/custom/1.0.0';

		$keys = [ 'enrolled', 'assigned', 'subject_id', 'sampling_unit', 'coordinator' ];
		$expectedExperimentConfig = array_intersect_key( $this->experimentConfig, array_fill_keys( $keys, true ) );

		$expectedEvent = [
			'$schema' => $newSchema,
			'dt' => ConvertibleTimestamp::now( TimestampFormat::ISO_8601 ),
		];

		$this->eventFactory->expects( $this->once() )
			->method( 'newEvent' )
			->with(
				$newSchema,
				$this->experimentConfig['contextual_attributes'],
				$this->action,
				array_merge( $this->interactionData, [ 'experiment' => $expectedExperimentConfig ] )
			)
			->willReturn( $expectedEvent );

		$this->eventSubmitter->expects( $this->once() )
			->method( 'submit' )
			->with( $this->experimentConfig['stream_name'], $expectedEvent );

		$return = $this->experiment->setSchema( $newSchema );
		$this->assertSame( $this->experiment, $return );

		$this->assertSame(
			$newSchema,
			$this->experiment->getExperimentConfig()['schema_id'] ?? null,
			'setSchema() should update experimentConfig schema_id'
		);

		$this->experiment->send( $this->action, $this->interactionData );

		$this->assertSame(
			[ 'mediawiki.TestKitchen.experiment_events_sent_total:1|c|#experiment:test_experiment' ],
			$this->statsHelper->consumeAllFormatted()
		);
	}
}
