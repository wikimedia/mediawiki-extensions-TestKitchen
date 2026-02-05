<?php

namespace MediaWiki\Extension\TestKitchen\Tests\Unit\TestKitchen\Sdk;

use MediaWiki\Extension\EventLogging\EventSubmitter\EventSubmitter;
use MediaWiki\Extension\TestKitchen\Sdk\EventFactory;
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

	private EventSubmitter $eventSubmitter;
	private EventFactory $eventFactory;

	public function setUp(): void {
		parent::setUp();
		$this->eventSubmitter = $this->createMock( EventSubmitter::class );
		$this->eventFactory = $this->createMock( EventFactory::class );

		$this->instrument = new Instrument(
			$this->eventSubmitter,
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
				'/analytics/product_metrics/web/base/2.0.0',
				[
					'performer_name',
					'page_id'
				],
				$this->action,
				$this->interactionData
			)
			->willReturn( $expectedEvent );

		$this->eventSubmitter
			->expects( $this->once() )
			->method( 'submit' )
			->with( 'product_metrics.web_base', $expectedEvent );

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

		$this->eventSubmitter
			->expects( $this->once() )
			->method( 'submit' )
			->with( 'product_metrics.web_base', $expectedEvent );

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
			$this->eventSubmitter,
			$this->eventFactory,
			$instrumentConfig
		);

		$this->eventFactory->expects( $this->once() )
			->method( 'newEvent' )
			->with(
				'/analytics/product_metrics/web/base/2.0.0',
				[],
				$this->action,
				$this->interactionData
			)
			->willReturn( $expectedEvent );

		$this->eventSubmitter
			->expects( $this->once() )
			->method( 'submit' )
			->with( 'product_metrics.web_base', $expectedEvent );

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
