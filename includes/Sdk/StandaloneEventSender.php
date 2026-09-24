<?php

namespace MediaWiki\Extension\TestKitchen\Sdk;

use FormatJson;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\RequestContext;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Request\WebRequest;

/**
 * Sends events without using the EventBus MediaWiki extension. This event sender will be used if the EventBus MediaWiki
 * extension isn't installed.
 *
 * Events are accumulated into a single batch while the request is processed. Typically, these events are small and so
 * the batch is small and so can be sent as a single HTTP POST request as a post-send update.
 *
 * @internal
 */
class StandaloneEventSender extends BaseEventSender {
	public const array CONSTRUCTOR_OPTIONS = [
		'TestKitchenInternalEventIntakeServiceUrl',
	];

	private string $eventIntakeServiceUrl;
	private WebRequest $originalRequest;

	public function __construct(
		private readonly HttpRequestFactory $httpRequestFactory,
		ServiceOptions $options
	) {
		parent::__construct();
		$this->eventIntakeServiceUrl = $options->get( 'TestKitchenInternalEventIntakeServiceUrl' );
	}

	protected function doSendEvents( array $events ): void {
		$request = $this->httpRequestFactory->create(
			$this->eventIntakeServiceUrl,
			[
				'method' => 'POST',
				'postData' => FormatJson::encode( $events ),
			],
			__METHOD__
		);
		$request->setHeader( 'Content-Type', 'application/json' );
		$request->setOriginalRequest( $this->originalRequest ??= RequestContext::getMain()->getRequest() );
		$request->execute();
	}
}
