<?php

namespace MediaWiki\Extension\TestKitchen\Sdk;

use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use MaxMind\Db\Reader\InvalidDatabaseException;
use MediaWiki\Request\WebRequest;
use Psr\Log\LoggerInterface;
use Wikimedia\IPUtils;

/**
 * Resolves the client's country (ISO 3166-1 alpha-2) for the agent_geo_ip_country
 * contextual attribute.
 *
 * Resolves from the GeoIP cookie the CDN sets on normal responses, then from
 * a server-side GeoIP2 lookup on the request IP.
 */
class CountryLookup {

	public function __construct(
		private readonly LoggerInterface $logger,
		private readonly ?Reader $geoIPReader = null
	) {
	}

	/**
	 * @param WebRequest $request
	 * @return string|null Two-letter ISO 3166-1 alpha-2 code, or null if unavailable
	 */
	public function lookupCountry( WebRequest $request ): ?string {
		// First check GeoIP cookie set by the CDN on normal responses.
		// Shape is like "US:CO:Denver:...".
		// Pass an empty prefix so the wiki's cookie prefix isn't prepended.
		$cookie = $request->getCookie( 'GeoIP', '' );
		if ( is_string( $cookie ) && $cookie !== '' ) {
			$country = explode( ':', $cookie )[0];
			if ( $country !== '' ) {
				return $country;
			}
		}
		// Don't resolve IP if there's no database for the lookup.
		if ( !$this->geoIPReader ) {
			return null;
		}

		// If GeoIP N/A, check server-side GeoIP2 lookup on the request IP.
		return $this->lookUpCountryFromIP( $request->getIP() );
	}

	/**
	 * @param string $ip
	 * @return string|null Two-letter ISO 3166-1 alpha-2 code, or null if unavailable
	 */
	private function lookUpCountryFromIP( string $ip ): ?string {
		if ( !IPUtils::isValid( $ip ) ) {
			return null;
		}
		try {
			return $this->geoIPReader->country( $ip )->country->isoCode ?: null;
		} catch ( AddressNotFoundException ) {
			// IP isn't in the database - no country available.
		} catch ( InvalidDatabaseException $e ) {
			$this->logger->error( $e->getMessage() );
		}
		return null;
	}
}
