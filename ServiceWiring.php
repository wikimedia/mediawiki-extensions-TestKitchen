<?php

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\TestKitchen\ConfigsFetcher;
use MediaWiki\Extension\TestKitchen\Coordination\Coordinator;
use MediaWiki\Extension\TestKitchen\Coordination\EnrollmentAuthority;
use MediaWiki\Extension\TestKitchen\Coordination\EveryoneExperimentsEnrollmentAuthority;
use MediaWiki\Extension\TestKitchen\Coordination\LoggedInExperimentsEnrollmentAuthority;
use MediaWiki\Extension\TestKitchen\Coordination\OverridesEnrollmentAuthority;
use MediaWiki\Extension\TestKitchen\Coordination\UserSplitterInstrumentation;
use MediaWiki\Extension\TestKitchen\Sdk\ContextualAttributesFactory;
use MediaWiki\Extension\TestKitchen\Sdk\EventFactory;
use MediaWiki\Extension\TestKitchen\Sdk\ExperimentManager;
use MediaWiki\Extension\TestKitchen\Sdk\StreamConfigs;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use Psr\Log\LoggerInterface;

return [
	'TestKitchen.ConfigsFetcher' => static function ( MediaWikiServices $services ): ConfigsFetcher  {
		$options = new ServiceOptions(
			ConfigsFetcher::CONSTRUCTOR_OPTIONS,
			$services->getMainConfig()
		);

		$cache = $services->getObjectCacheFactory()->getLocalClusterInstance();
		$stash = $services->getMainObjectStash();

		return new ConfigsFetcher(
			$options,
			$cache,
			$stash,
			$services->getHttpRequestFactory(),
			$services->getService( 'TestKitchen.Logger' ),
			$services->getStatsFactory()->withComponent( 'TestKitchen' ),
			$services->getFormatterFactory()->getStatusFormatter( RequestContext::getMain() )
		);
	},
	'TestKitchen.Logger' => static function (): LoggerInterface {
		return LoggerFactory::getInstance( 'TestKitchen' );
	},
	'TestKitchen.EveryoneExperimentsEnrollmentAuthority' =>
		static function ( MediaWikiServices $services ): EveryoneExperimentsEnrollmentAuthority {
			return new EveryoneExperimentsEnrollmentAuthority(
				$services->getService( 'TestKitchen.Logger' )
			);
		},
	'TestKitchen.LoggedInExperimentsEnrollmentAuthority' =>
		static function ( MediaWikiServices $services ): LoggedInExperimentsEnrollmentAuthority {
			return new LoggedInExperimentsEnrollmentAuthority( $services->getCentralIdLookup() );
		},
	'TestKitchen.OverridesEnrollmentAuthority' =>
		static function ( MediaWikiServices $services ): OverridesEnrollmentAuthority {
			return new OverridesEnrollmentAuthority(
				$services->getService( 'TestKitchen.Logger' )
			);
		},
	'TestKitchen.EnrollmentAuthority' =>
		static function ( MediaWikiServices $services ): EnrollmentAuthority {
			return new EnrollmentAuthority(
				$services->getService( 'TestKitchen.EveryoneExperimentsEnrollmentAuthority' ),
				$services->getService( 'TestKitchen.LoggedInExperimentsEnrollmentAuthority' ),
				$services->getService( 'TestKitchen.OverridesEnrollmentAuthority' )
			);
		},
	'TestKitchen.ContextualAttributesFactory' =>
		static function ( MediaWikiServices $services ): ContextualAttributesFactory {
			return new ContextualAttributesFactory(
				$services->getMainConfig(),
				ExtensionRegistry::getInstance(),
				$services->getNamespaceInfo(),
				$services->getRestrictionStore(),
				$services->getUserOptionsLookup(),
				$services->getContentLanguage(),
				$services->getUserGroupManager(),
				$services->getLanguageConverterFactory(),
				$services->get( 'EventLogging.UserBucketService' )
			);
		},
	'TestKitchen.EventFactory' => static function ( MediaWikiServices $services ): EventFactory {
		return new EventFactory(
			$services->getService( 'TestKitchen.ContextualAttributesFactory' ),
			RequestContext::getMain()
		);
	},
	'TestKitchen.StaticStreamConfigs' => static function ( MediaWikiServices $services ): StreamConfigs {
		return new StreamConfigs( $services->get( 'EventStreamConfig.StreamConfigs' ) );
	},
	'TestKitchen.ExperimentManager' => static function ( MediaWikiServices $services ): ExperimentManager {
		return new ExperimentManager(
			$services->getService( 'TestKitchen.Logger' ),
			$services->getService( 'EventLogging.EventSubmitter' ),
			$services->getService( 'TestKitchen.EventFactory' ),
			$services->getStatsFactory(),
			$services->getService( 'TestKitchen.StaticStreamConfigs' )
		);
	},
	'TestKitchen.Coordinator' => static function ( MediaWikiServices $services ): Coordinator {
		return new Coordinator(
			$services->getMainConfig(),
			$services->getService( 'TestKitchen.ConfigsFetcher' ),
			new UserSplitterInstrumentation()
		);
	}
];
