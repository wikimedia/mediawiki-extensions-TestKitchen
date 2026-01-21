<?php

namespace MediaWiki\Extension\TestKitchen\Sdk;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\IContextSource;
use MediaWiki\MainConfigNames;
use Wikimedia\Timestamp\ConvertibleTimestamp;
use Wikimedia\Timestamp\TimestampFormat;

/**
 * This class is used to create events for sending to the Event Platform.
 *
 * The `ContextualAttributesFactory` class is used to retrieve the values of contextual attributes from the application.
 * Note well that `ContextualAttributesFactory` is only used when the first event is created, i.e. if no events are
 * created during the request, then no contextual attribute values are retrieved.
 *
 * This class is a combination of the `\MediaWiki\Extension\EventLogging\MetricsPlatform\Integration` and
 * `\Wikimedia\MetricsPlatform\MetricsClient` classes, both of which were written and maintained by the authors of this
 * extension.
 *
 * @internal
 */
class EventFactory {
	public const CONSTRUCTOR_OPTIONS = [
		MainConfigNames::ServerName,
	];

	private const REQUIRED_CONTEXTUAL_ATTRIBUTES = [
		'agent_client_platform',
		'agent_client_platform_family',
	];

	private string $domain;
	private ?array $contextualAttributes = null;

	public function __construct(
		private readonly ContextualAttributesFactory $contextualAttributesFactory,
		private readonly IContextSource $contextSource,
		ServiceOptions $options
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );

		$this->domain = $options->get( MainConfigNames::ServerName );
	}

	/**
	 * Creates a new event to be sent.
	 *
	 * The event will contain the following fields:
	 *
	 * - `$schema`
	 * - `dt`, which will be set to the current time in ISO 8601 format (including milliseconds)
	 * - `action`
	 *
	 * If `$interactionData` is set, then it will be added to the event. If recognized contextual attributes are
	 * requested, they will be added to the event.
	 *
	 * @param string $streamName
	 * @param string $schemaID
	 * @param array $contextualAttributes
	 * @param string $action
	 * @param array|null $interactionData
	 */
	public function newEvent(
		string $streamName,
		string $schemaID,
		array $contextualAttributes,
		string $action,
		?array $interactionData = []
	): array {
		$event = [
			'action' => $action,
			...$interactionData,
			'$schema' => $schemaID,
			'meta' => [
				'domain' => $this->domain,
				'stream' => $streamName,
			],
			'dt' => $this->getTimestamp(),
		];

		return $this->addContextualAttributes( $event, $contextualAttributes );
	}

	private function addContextualAttributes( array $event, array $requestedContextualAttributes ): array {
		$requestedContextualAttributes = array_unique( array_merge(
			$requestedContextualAttributes,
			self::REQUIRED_CONTEXTUAL_ATTRIBUTES,
		) );

		if ( $this->contextualAttributes === null ) {
			$this->contextualAttributes =
				$this->contextualAttributesFactory->newContextAttributes( $this->contextSource );
		}

		foreach ( $requestedContextualAttributes as $requestedContextualAttribute ) {
			$value = $this->contextualAttributes[$requestedContextualAttribute] ?? null;

			// Contextual attributes are null by default. Only add the requested contextual attribute - incurring the
			//cost of transporting it - if it is not null.
			if ( $value === null ) {
				continue;
			}

			[ $primaryKey, $secondaryKey ] = explode( '_', $requestedContextualAttribute, 2 );

			$event[$primaryKey][$secondaryKey] = $value;
		}

		return $event;
	}

	/**
	 * Get an ISO 8601 timestamp for the current time, e.g. 2022-05-03T14:00:41.000Z.
	 */
	private function getTimestamp(): string {
		return ConvertibleTimestamp::now( TimestampFormat::ISO_8601 );
	}
}
