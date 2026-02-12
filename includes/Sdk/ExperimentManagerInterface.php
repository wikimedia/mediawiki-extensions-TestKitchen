<?php

namespace MediaWiki\Extension\TestKitchen\Sdk;

interface ExperimentManagerInterface {
	/**
	 * Get the current user's experiment object.
	 *
	 * @param string $experimentName
	 * @return ExperimentInterface
	 */
	public function getExperiment( string $experimentName ): ExperimentInterface;

}
