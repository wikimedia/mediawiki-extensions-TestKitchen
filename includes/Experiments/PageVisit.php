<?php

namespace MediaWiki\Extension\TestKitchen\Experiments;

use MediaWiki\Extension\TestKitchen\Sdk\ExperimentManager;
use MediaWiki\Hook\BeforePageDisplayHook;

/**
 * A simple experiment-specific instrument that sends a "page-visited" event if the current user is
 * enrolled in the "synth-aa-test-mw-php-tk" experiment.
 *
 * See https://phabricator.wikimedia.org/T414530 for more context
 */
class PageVisit implements BeforePageDisplayHook {

	/** @var string */
	private const EXPERIMENT_NAME = 'synth-aa-test-mw-php-tk';
	private ?ExperimentManager $experimentManager;

	/**
	 * @param ExperimentManager|null $experimentManager
	 */
	public function __construct( ?ExperimentManager $experimentManager = null ) {
		$this->experimentManager = $experimentManager;
	}

	/**
	 * @inheritDoc
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		// Is Test Kitchen loaded?
		if ( !$this->experimentManager ) {
			return;
		}

		$experiment = $this->experimentManager->getExperiment( self::EXPERIMENT_NAME );
		$experiment->send(
			'page-visited-using-test-kitchen',
			[
				'instrument_name' => 'PageVisitTestKitchen'
			]
		);
	}
}
