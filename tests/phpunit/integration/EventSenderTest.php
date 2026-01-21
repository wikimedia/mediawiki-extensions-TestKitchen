<?php

use MediaWiki\Extension\EventBus\EventBus;
use MediaWiki\Extension\TestKitchen\Sdk\EventSender;
use Wikimedia\TestingAccessWrapper;
use Wikimedia\Timestamp\ConvertibleTimestamp;
use Wikimedia\Timestamp\TimestampFormat;

/**
 * @covers \MediaWiki\Extension\TestKitchen\Sdk\EventSender
 */
class EventSenderTest extends MediaWikiIntegrationTestCase {
	private EventBus $eventBus;
	private EventSender $eventSender;

	public function setUp(): void {
		$this->eventBus = $this->createMock( EventBus::class );
		$this->eventSender = new EventSender( $this->eventBus );
	}

	public function testItSendsEventsImmediatelyInCliMode(): void {
		$expectedEvent = [
			'meta' => [
				'domain' => 'EventSenderTest',
				'stream' => 'foo_stream'
			],
			'$schema' => '/foo/bar/1.0.0',
			'dt' => ConvertibleTimestamp::now( TimestampFormat::ISO_8601 ),
		];

		$this->eventBus->expects( $this->once() )
			->method( 'send' )
			->with( [ $expectedEvent ] );

		$this->eventSender->sendEvent( $expectedEvent );
	}

	public function testItQueuesEventsByDefault(): void {
		$expectedEvent1 = [
			'meta' => [
				'domain' => 'EventSenderTest',
				'stream' => 'foo_stream'
			],
			'$schema' => '/foo/bar/1.0.0',
			'dt' => ConvertibleTimestamp::now( TimestampFormat::ISO_8601 ),
		];
		$expectedEvent2 = [
			'meta' => [
				'domain' => 'EventSenderTest',
				'stream' => 'foo_stream'
			],
			'$schema' => '/foo/bar/1.0.0',
			'dt' => ConvertibleTimestamp::now( TimestampFormat::ISO_8601 ),
		];

		$this->eventBus->expects( $this->once() )
			->method( 'send' )
			->with( [ $expectedEvent1, $expectedEvent2 ] );

		TestingAccessWrapper::newFromObject( $this->eventSender )->isCliMode = false;

		// Per the DeferredUpdates::tryOpportunisticExecute() DocBlock, "In CLI mode, updates run earlier and more
		// often." Prevent that from happening until the end of the test.

		// @phan-suppress-next-line PhanUnusedVariable
		$cleanup = DeferredUpdates::preventOpportunisticUpdates();

		$this->eventSender->sendEvent( $expectedEvent1 );
		$this->eventSender->sendEvent( $expectedEvent2 );

		$this->runDeferredUpdates();
	}
}
