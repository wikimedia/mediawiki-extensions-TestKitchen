<?php

use MediaWiki\Config\HashConfig;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\TestKitchen\Sdk\StandaloneEventSender;

/**
 * @covers \MediaWiki\Extension\TestKitchen\Sdk\StandaloneEventSender
 */
class StandaloneEventSenderTest extends MediaWikiIntegrationTestCase {
	use MockHttpTrait;

	public function testSend(): void {
		$event = [
			'action' => 'Hello, World!',
		];

		$eventSender = new StandaloneEventSender(
			$this->makeMockHttpRequestFactory( function ( string $url, array $options ) use ( $event, &$request ) {
				$this->assertSame( 'http://foo.bar/baz?hasty=true', $url );
				$this->assertArrayEquals(
					[
						'method' => 'POST',
						'postData' => FormatJson::encode( [ $event ] )
					],
					$options,
					false,
					true
				);

				// https://gitlab.wikimedia.org/repos/data-engineering/eventgate/-/blob/b76178540f305764ad55f862afd4538a66459e0b/routes/events.js#L67
				$request = $this->makeFakeHttpRequest(
					'1 events hastily received.',
					202
				);

				$request->expects( $this->once() )
					->method( 'setHeader' )
					->with( 'Content-Type', 'application/json' );

				$request->expects( $this->once() )
					->method( 'execute' );

				return $request;
			} ),
			new ServiceOptions(
				StandaloneEventSender::CONSTRUCTOR_OPTIONS,
				new HashConfig( [
					'TestKitchenInternalEventIntakeServiceUrl' => 'http://foo.bar/baz?hasty=true'
				] )
			)
		);

		// Code Under Test
		// ---------------

		$eventSender->sendEvent( $event );
	}
}
