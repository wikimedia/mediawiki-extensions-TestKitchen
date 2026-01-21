<?php

namespace MediaWiki\Extension\TestKitchen\Tests\Unit\TestKitchen\Sdk;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\TestKitchen\Sdk\ContextualAttributesFactory;
use MediaWiki\Extension\TestKitchen\Sdk\EventFactory;
use MediaWiki\MainConfigNames;
use MediaWikiUnitTestCase;
use Wikimedia\Timestamp\ConvertibleTimestamp;
use Wikimedia\Timestamp\TimestampFormat;

/**
 * @covers \MediaWiki\Extension\TestKitchen\Sdk\EventFactory
 */
class EventFactoryTest extends MediaWikiUnitTestCase {
	private const SERVER_NAME = 'EventFactoryTest';

	private ContextualAttributesFactory $contextualAttributesFactory;
	private IContextSource $contextSource;
	private EventFactory $eventFactory;
	private string $now;

	public function setUp(): void {
		$this->contextualAttributesFactory = $this->createMock( ContextualAttributesFactory::class );
		$this->contextSource = $this->createMock( IContextSource::class );

		$options = new ServiceOptions(
			EventFactory::CONSTRUCTOR_OPTIONS,
			[
				MainConfigNames::ServerName => self::SERVER_NAME,
			]
		);

		$this->eventFactory = new EventFactory(
			$this->contextualAttributesFactory,
			$this->contextSource,
			$options,
		);

		$this->now = ConvertibleTimestamp::now( TimestampFormat::ISO_8601 );

		ConvertibleTimestamp::setFakeTime( $this->now );
	}

	public function testNewEvent(): void {
		$this->contextualAttributesFactory->expects( $this->once() )
			->method( 'newContextAttributes' )
			->with( $this->contextSource )
			->willReturn( [
				'agent_client_platform' => 'mediawiki_php',
				'agent_client_platform_family' => 'desktop_browser',
			] );

		$event = $this->eventFactory->newEvent(
			'foo_stream',
			'/foo/bar/1.0.0',
			[
				'agent_client_platform',
				'agent_client_platform_family'
			],
			'foo',
			[
				'bar' => 'baz',
			]
		);

		$this->assertArrayEquals(
			[
				'meta' => [
					'domain' => self::SERVER_NAME,
					'stream' => 'foo_stream',
				],
				'$schema' => '/foo/bar/1.0.0',
				'dt' => $this->now,
				'action' => 'foo',
				'bar' => 'baz',
				'agent' => [
					'client_platform' => 'mediawiki_php',
					'client_platform_family' => 'desktop_browser',
				],
			],
			$event
		);
	}

	public function testNewEventOnlyCallsNewAttributesOnce(): void {
		$this->contextualAttributesFactory->expects( $this->once() )
			->method( 'newContextAttributes' )
			->with( $this->contextSource );

		$this->eventFactory->newEvent(
			'foo_stream',
			'/foo/bar/1.0.0',
			[
				'agent_client_platform',
			],
			'foo'
		);
		$this->eventFactory->newEvent(
			'foo_stream',
			'/foo/bar/1.0.0',
			[
				'agent_client_platform',
			],
			'foo'
		);
	}

	/**
	 * This test asserts that `ContextualAttributesFactory#newContextAttributes()` isn't invoked
	 * by `EventFactory#__construct()`.
	 */
	public function testNewEventCallsNewAttributesLazily(): void {
		$this->contextualAttributesFactory->expects( $this->never() )
			->method( 'newContextAttributes' );
	}

	public function testNewEventIncludesRequiredContextualAttributes(): void {
		$this->contextualAttributesFactory->expects( $this->once() )
			->method( 'newContextAttributes' )
			->with( $this->contextSource )
			->willReturn( [
				'agent_client_platform' => 'mediawiki_php',
				'agent_client_platform_family' => 'desktop_browser',
			] );

		$event = $this->eventFactory->newEvent(
			'foo_stream',
			'/foo/bar/1.0.0',
			[],
			'foo',
			[
				'bar' => 'baz',
			]
		);

		$this->assertArrayEquals(
			[
				'client_platform' => 'mediawiki_php',
				'client_platform_family' => 'desktop_browser',
			],
			$event['agent']
		);
	}
}
