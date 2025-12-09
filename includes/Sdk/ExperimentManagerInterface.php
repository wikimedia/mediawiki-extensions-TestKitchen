<?php

namespace MediaWiki\Extension\TestKitchen\Sdk;

interface ExperimentManagerInterface {
	/**
	 * Get the current user's experiment object.
	 *
	 * @param string $experimentName
	 * @return Experiment
	 */
	public function getExperiment( string $experimentName ): Experiment;

}
