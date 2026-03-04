<?php

namespace MediaWiki\Extension\TestKitchen\Experiments;

use MediaWiki\Extension\TestKitchen\Sdk\ExperimentManager;
use MediaWiki\Output\Hook\BeforePageDisplayHook;

/**
 * A simple experiment that checks if the user is using the MinervaNeue skin and, if so:
 *
 * 1. Loads two instruments: The init instrument and either the control, instrument1 or instrument2
 *    instrument, depending on the assigned group
 * 2. Sends an action=page_visit event if the user is enrolled in the experiment from the init instrument
 * 3. Sends an action=module_loaded event from the treatment group instrument
 *
 *
 * See https://phabricator.wikimedia.org/T418614 for more context.
 */
class MinervaExperimentAAA implements BeforePageDisplayHook {
	private const EXPERIMENT_NAME = 'minerva-experiment-aaa';
	private const CONTROL = 'control';
	private const TREATMENT_1 = 'treatment-1';
	private const TREATMENT_2 = 'treatment-2';

	private ExperimentManager $experimentManager;

	/**
	 * @param ExperimentManager $experimentManager
	 */
	public function __construct( ExperimentManager $experimentManager ) {
		$this->experimentManager = $experimentManager;
	}

	/**
	 * @inheritDoc
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		$context = $out->getContext();
		$title = $context->getTitle();

		if (
			$title && $title->getNamespace() === NS_MAIN &&
			$out->getSkin()->getSkinName() === 'minerva'
		) {
			// BEGIN MINERVA_EXPERIMENT_AAA (T418614)
			$experiment = $this->experimentManager->getExperiment( self::EXPERIMENT_NAME );
			$assignedGroup = $experiment->getAssignedGroup();

			if ( $assignedGroup !== null ) {
				$out->addModules( 'ext.testKitchen.minervaExperiment.init' );
			}
			if ( $assignedGroup === self::CONTROL ) {
				$out->addModules( 'ext.testKitchen.minervaExperiment.instrumentControl' );
			}
			if ( $assignedGroup === self::TREATMENT_1 ) {
				$out->addModules( 'ext.testKitchen.minervaExperiment.instrument1' );
			}
			if ( $assignedGroup === self::TREATMENT_2 ) {
				$out->addModules( 'ext.testKitchen.minervaExperiment.instrument2' );
			}
		}
	}
}
