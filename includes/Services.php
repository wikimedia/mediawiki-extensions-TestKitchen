<?php

namespace MediaWiki\Extension\TestKitchen;

use MediaWiki\MediaWikiServices;

class Services {
	public static function getConfigsFetcher(): ConfigsFetcher {
		return MediaWikiServices::getInstance()->getService( 'TestKitchen.ConfigsFetcher' );
	}
}
