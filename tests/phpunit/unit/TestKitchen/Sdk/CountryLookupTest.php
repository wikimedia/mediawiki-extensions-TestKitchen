<?php

namespace MediaWiki\Extension\TestKitchen\Tests\Unit\TestKitchen\Sdk;

use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use GeoIp2\Model\Country;
use MediaWiki\Extension\TestKitchen\Sdk\CountryLookup;
use MediaWiki\Request\FauxRequest;
use MediaWikiUnitTestCase;
use Psr\Log\NullLogger;

/**
 * @covers \MediaWiki\Extension\TestKitchen\Sdk\CountryLookup
 */
class CountryLookupTest extends MediaWikiUnitTestCase {

	private function newLookup( ?Reader $reader ): CountryLookup {
		return new CountryLookup( new NullLogger(), $reader );
	}

	/**
	 * The country comes from the GeoIP cookie the CDN sets, using only its first
	 * colon-delimited field.
	 */
	public function testCountryFromCookie(): void {
		$request = new FauxRequest();
		// Shaped like the CDN cookie "US:CO:Denver:...". Empty prefix matches the read.
		$request->setCookie( 'GeoIP', 'FR:A1:Paris:48.8566:2.3522:v4', '' );

		$this->assertSame( 'FR', $this->newLookup( null )->lookupCountry( $request ) );
	}

	/**
	 * With no GeoIP cookie, fall back to a server-side GeoIP2 lookup on the request IP.
	 */
	public function testCountryFromGeoIPReader(): void {
		$request = new FauxRequest();
		$request->setIP( '8.8.8.8' );

		$reader = $this->createMock( Reader::class );
		$reader->method( 'country' )
			->willReturn( new Country( [ 'country' => [ 'iso_code' => 'DE' ] ] ) );

		$this->assertSame( 'DE', $this->newLookup( $reader )->lookupCountry( $request ) );
	}

	/**
	 * The cookie wins over the GeoIP2 lookup when both could provide a country.
	 */
	public function testCookieTakesPrecedenceOverReader(): void {
		$request = new FauxRequest();
		$request->setIP( '8.8.8.8' );
		$request->setCookie( 'GeoIP', 'FR:A1:Paris', '' );

		$reader = $this->createMock( Reader::class );
		$reader->expects( $this->never() )->method( 'country' );

		$this->assertSame( 'FR', $this->newLookup( $reader )->lookupCountry( $request ) );
	}

	/**
	 * No cookie and no reader (e.g. the database is unconfigured) yields null so the
	 * attribute is omitted.
	 */
	public function testNullWhenNoCookieAndNoReader(): void {
		$request = new FauxRequest();
		// Set the IP so getIP() doesn't reach the service container (forbidden in unit
		// tests). With a null reader the lookup returns null without using it anyway.
		$request->setIP( '8.8.8.8' );

		$this->assertNull( $this->newLookup( null )->lookupCountry( $request ) );
	}

	/**
	 * When the IP is not in the database, the attribute is null.
	 */
	public function testNullWhenReaderFindsNothing(): void {
		$request = new FauxRequest();
		$request->setIP( '8.8.8.8' );

		$reader = $this->createMock( Reader::class );
		$reader->method( 'country' )
			->willThrowException( new AddressNotFoundException( 'not found' ) );

		$this->assertNull( $this->newLookup( $reader )->lookupCountry( $request ) );
	}
}
