<?php

namespace MediaWiki\Extension\TestKitchen\Tests\Unit\Sdk;

use MediaWiki\Extension\TestKitchen\Sdk\ExposureLogTracker;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\TestKitchen\Sdk\ExposureLogTracker
 */
class ExposureLogTrackerTest extends MediaWikiUnitTestCase {

	public function testCheckShouldSendReturnsTrueByDefault(): void {
		$tracker = new ExposureLogTracker();

		$this->assertTrue(
			$tracker->checkShouldSend( 'tk_exposure.my-experiment:control' )
		);
	}

	public function testAddLogMarksExposureAsLogged(): void {
		$tracker = new ExposureLogTracker();

		$tracker->addLog( 'tk_exposure.my-experiment:control' );

		$this->assertFalse(
			$tracker->checkShouldSend( 'tk_exposure.my-experiment:control' )
		);
	}

	public function testDifferentGroupsAreTrackedSeparately(): void {
		$tracker = new ExposureLogTracker();

		$tracker->addLog( 'tk_exposure.my-experiment:control' );

		$this->assertFalse(
			$tracker->checkShouldSend( 'tk_exposure.my-experiment:control' )
		);
		$this->assertTrue(
			$tracker->checkShouldSend( 'tk_exposure.my-experiment:treatment' )
		);
	}

	public function testDifferentExperimentsAreTrackedSeparately(): void {
		$tracker = new ExposureLogTracker();

		$tracker->addLog( 'tk_exposure.experiment-a:control' );

		$this->assertFalse(
			$tracker->checkShouldSend( 'tk_exposure.experiment-a:control' )
		);
		$this->assertTrue(
			$tracker->checkShouldSend( 'tk_exposure.experiment-b:control' )
		);
	}

	public function testDuplicateAddLogDoesNotBreakLookup(): void {
		$tracker = new ExposureLogTracker();

		$tracker->addLog( 'tk_exposure.my-experiment:control' );
		$tracker->addLog( 'tk_exposure.my-experiment:control' );

		$this->assertFalse(
			$tracker->checkShouldSend( 'tk_exposure.my-experiment:control' )
		);
	}

	public function testKeysAreScopedByAssignedGroup(): void {
		$tracker = new ExposureLogTracker();

		$tracker->addLog( 'tk_exposure.my-experiment:treatment' );

		$this->assertTrue(
			$tracker->checkShouldSend( 'tk_exposure.my-experiment:control' )
		);
		$this->assertFalse(
			$tracker->checkShouldSend( 'tk_exposure.my-experiment:treatment' )
		);
	}
}
