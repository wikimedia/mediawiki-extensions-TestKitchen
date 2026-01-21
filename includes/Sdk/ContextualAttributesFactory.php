<?php

namespace MediaWiki\Extension\TestKitchen\Sdk;

use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\Language\Language;
use MediaWiki\Languages\LanguageConverterFactory;
use MediaWiki\MainConfigNames;
use MediaWiki\Permissions\RestrictionStore;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\UserGroupManager;
use MobileContext;

/**
 * This class is the same as [`MediaWiki\Extension\EventLogging\MetricsPlatform\ContextAttributesFactory`][0]. That
 * class was written and maintained by the authors of this extension. Additionally, the EventLogging extension authors
 * can be found [here][1].
 *
 * [0] https://gerrit.wikimedia.org/g/mediawiki/extensions/EventLogging/+/de06180f92aa89802d50fa1b9d2165f483022ac8/includes/MetricsPlatform/ContextAttributesFactory.php
 * [1] https://gerrit.wikimedia.org/r/plugins/gitiles/mediawiki/extensions/EventLogging/+/de06180f92aa89802d50fa1b9d2165f483022ac8/extension.json#3
 *
 * @internal
 */
class ContextualAttributesFactory {
	public function __construct(
		private readonly Config $mainConfig,
		private readonly ExtensionRegistry $extensionRegistry,
		private readonly NamespaceInfo $namespaceInfo,
		private readonly RestrictionStore $restrictionStore,
		private readonly UserOptionsLookup $userOptionsLookup,
		private readonly Language $contentLanguage,
		private readonly UserGroupManager $userGroupManager,
		private readonly LanguageConverterFactory $languageConverterFactory,
		private readonly UserEditCountService $userEditCountService
	) {
	}

	/**
	 * @param IContextSource $contextSource
	 */
	public function newContextAttributes( IContextSource $contextSource ): array {
		$contextAttributes = [];
		$contextAttributes += $this->getAgentContextAttributes();
		$contextAttributes += $this->getPageContextAttributes( $contextSource );
		$contextAttributes += $this->getMediaWikiContextAttributes( $contextSource );
		$contextAttributes += $this->getPerformerContextAttributes( $contextSource );

		return $contextAttributes;
	}

	/**
	 * Gets whether the user is accessing the mobile website
	 */
	protected function shouldDisplayMobileView(): bool {
		if ( $this->extensionRegistry->isLoaded( 'MobileFrontend' ) ) {
			// @phan-suppress-next-line PhanUndeclaredClassMethod
			return MobileContext::singleton()->shouldDisplayMobileView();
		}

		return false;
	}

	private function getAgentContextAttributes(): array {
		return [
			'agent_app_install_id' => null,
			'agent_client_platform' => 'mediawiki_php',
			'agent_client_platform_family' =>
				$this->shouldDisplayMobileView() ? 'mobile_browser' : 'desktop_browser',
			'agent_ua_string' => $_SERVER['HTTP_USER_AGENT'] ?? '',
		];
	}

	private function getPageContextAttributes( IContextSource $contextSource ): array {
		$output = $contextSource->getOutput();
		$wikidataItemId = $output->getProperty( 'wikibase_item' );
		$wikidataItemId = $wikidataItemId === null ? null : (string)$wikidataItemId;

		$result = [

			// The wikidata_id (int) context attribute is deprecated in favor of wikidata_qid
			// (string). See T330459 and T332673 for detail.
			'page_wikidata_qid' => $wikidataItemId,

		];

		$title = $contextSource->getTitle();

		// IContextSource::getTitle() can return null.
		//
		// TODO: Document under what circumstances this happens.
		if ( !$title ) {
			return $result;
		}

		$namespaceId = $title->getNamespace();

		return $result + [
				'page_id' => $title->getArticleID(),
				'page_title' => $title->getDBkey(),
				'page_namespace_id' => $namespaceId,
				'page_namespace_name' => $this->namespaceInfo->getCanonicalName( $namespaceId ),
				'page_revision_id' => $title->getLatestRevID(),
				'page_content_language' => $title->getPageLanguage()->getCode(),
				'page_is_redirect' => $title->isRedirect(),
				'page_groups_allowed_to_move' => $this->restrictionStore->getRestrictions( $title, 'move' ),
				'page_groups_allowed_to_edit' => $this->restrictionStore->getRestrictions( $title, 'edit' ),
			];
	}

	private function getMediaWikiContextAttributes( IContextSource $contextSource ): array {
		$skin = $contextSource->getSkin();

		$user = $contextSource->getUser();
		$isDebugMode = $this->userOptionsLookup->getIntOption( $user, 'eventlogging-display-console' ) === 1;

		// TODO: Reevaluate whether the `mediawiki.is_production` contextual attribute is useful.
		//  We should be able to determine this from the database name of the wiki during analysis.
		$isProduction = strpos( MW_VERSION, 'wmf' ) !== false;

		return [
			'mediawiki_skin' => $skin->getSkinName(),
			'mediawiki_version' => MW_VERSION,
			'mediawiki_is_debug_mode' => $isDebugMode,
			'mediawiki_is_production' => $isProduction,
			'mediawiki_database' => $this->mainConfig->get( MainConfigNames::DBname ),
			'mediawiki_site_content_language' => $this->contentLanguage->getCode(),
		];
	}

	private function getPerformerContextAttributes( IContextSource $contextSource ): array {
		$user = $contextSource->getUser();
		$userName = $user->isAnon() ? null : $user->getName();
		$userLanguage = $contextSource->getLanguage();

		$languageConverter = $this->languageConverterFactory->getLanguageConverter( $userLanguage );
		$userLanguageVariant = $languageConverter->hasVariants() ? $languageConverter->getPreferredVariant() : null;

		$userEditCount = $user->getEditCount();
		$userEditCountBucket =
			$user->isAnon() ? null : $this->userEditCountService->getUserEditCountBucket( $userEditCount );

		$result = [
			'performer_is_logged_in' => !$user->isAnon(),
			'performer_id' => $user->getId(),
			'performer_name' => $userName,
			'performer_groups' => $this->userGroupManager->getUserEffectiveGroups( $user ),
			'performer_is_bot' => $user->isBot(),
			'performer_is_temp' => $user->isTemp(),
			'performer_language' => $userLanguage->getCode(),
			'performer_language_variant' => $userLanguageVariant,
			'performer_edit_count' => $userEditCount,
			'performer_edit_count_bucket' => $userEditCountBucket
		];

		// T408547 `$user-getRegistration()` returns `false` (which will fail when validating the event ) when
		// the user is not registered
		$registrationTimestamp = $user->getRegistration();
		if ( $registrationTimestamp ) {
			$registrationTimestamp = wfTimestamp( TS_ISO_8601, $registrationTimestamp );
			$result['performer_registration_dt'] = $registrationTimestamp;
		}

		// IContextSource::getTitle() can return null.
		//
		// TODO: Document under what circumstances this happens.
		$title = $contextSource->getTitle();

		if ( $title ) {
			$result['performer_can_probably_edit_page'] = $user->probablyCan( 'edit', $title );
		}

		return $result;
	}
}
