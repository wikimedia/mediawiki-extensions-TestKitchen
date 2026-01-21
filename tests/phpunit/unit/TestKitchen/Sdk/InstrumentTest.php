<?php

namespace MediaWiki\Extension\TestKitchen\Tests\Unit\TestKitchen\Sdk;

use MediaWiki\Extension\TestKitchen\Sdk\EventFactory;
use MediaWiki\Extension\TestKitchen\Sdk\EventSender;
use MediaWiki\Extension\TestKitchen\Sdk\Instrument;
use MediaWikiUnitTestCase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \MediaWiki\Extension\TestKitchen\Sdk\Instrument
 */
class InstrumentTest extends MediaWikiUnitTestCase {

	/** @var array */
	private array $instrumentConfig = [
		'name' => 'my-instrument',
		'stream_name' => 'product_metrics.web_base',
		'schema_id' => '/analytics/product_metrics/web/base/2.0.0',
		'contextual_attributes' => [
			'performer_name',
			'page_id'
		],
	];

	/** @var Instrument */
	private $instrument;

	/** @var string */
	private string $action = 'test_action';

	/** @var array */
	private array $interactionData = [
		'action_source' => 'test_action_source',
		'action_context' => 'test_action_context',
		'instrument_name' => 'my-instrument',
		'funnel_event_sequence_position' => 1
	];

	private EventSender $eventSender;
	private EventFactory $eventFactory;

	public function setUp(): void {
		parent::setUp();
		$this->eventSender = $this->createMock( EventSender::class );
		$this->eventFactory = $this->createMock( EventFactory::class );

		$this->instrument = new Instrument(
			$this->eventSender,
			$this->eventFactory,
			$this->instrumentConfig
		);
	}

	public function testSendArgumentsDefault() {
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
					'performer_name',
					'page_id'
				],
				$this->action,
				$this->interactionData
			)
			->willReturn( $expectedEvent );

		$this->eventSender
			->expects( $this->once() )
			->method( 'sendEvent' )
			->with( $expectedEvent );

		$this->instrument->send( $this->action, $this->interactionData );
	}

	public function testSendArgumentsNoInteractionData() {
		$expectedEvent = [
			'$schema' => '/analytics/product_metrics/web/base/2.0.0',
			'dt' => ConvertibleTimestamp::now( TS_ISO_8601 )
		];

		$this->eventFactory->expects( $this->once() )
			->method( 'newEvent' )
			->with(
				'product_metrics.web_base',
				'/analytics/product_metrics/web/base/2.0.0',
				[
					'performer_name',
					'page_id'
				],
				$this->action,
				[
					'instrument_name' => 'my-instrument',
					'funnel_event_sequence_position' => 1
				]
			)
			->willReturn( $expectedEvent );

		$this->eventSender->expects( $this->once() )
			->method( 'sendEvent' )
			->with( $expectedEvent );

		$this->instrument->send( $this->action );
	}

	public function testSendArgumentsNoContextualAttributes() {
		$instrumentConfig = [
			'name' => 'my-instrument',
			'stream_name' => 'product_metrics.web_base',
			'schema_id' => '/analytics/product_metrics/web/base/2.0.0',
			'contextual_attributes' => []
		];

		$expectedEvent = [
			'$schema' => '/analytics/product_metrics/web/base/2.0.0',
			'dt' => ConvertibleTimestamp::now( TS_ISO_8601 )
		];

		$instrument = new Instrument(
			$this->eventSender,
			$this->eventFactory,
			$instrumentConfig
		);

		$this->eventFactory->expects( $this->once() )
			->method( 'newEvent' )
			->with(
				'product_metrics.web_base',
				'/analytics/product_metrics/web/base/2.0.0',
				[],
				$this->action,
				$this->interactionData
			)
			->willReturn( $expectedEvent );

		$this->eventSender->expects( $this->once() )
			->method( 'sendEvent' )
			->with( $expectedEvent );

		$instrument->send( $this->action, $this->interactionData );
	}

	public function testSetSchema(): void {
		$newSchema = '/analytics/product_metrics/web/custom/1.0.0';

		$return = $this->instrument->setSchema( $newSchema );

		$this->assertSame( $this->instrument, $return );
		$this->assertSame(
			$newSchema,
			$this->instrument->getConfig()['schema_id']
		);
	}
}
