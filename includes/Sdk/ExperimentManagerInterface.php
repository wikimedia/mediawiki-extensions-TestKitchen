<?php

namespace MediaWiki\Extension\TestKitchen\Sdk;

interface ExperimentManagerInterface {

	/**
	 * Get the details of an experiment.
	 *
	 * This method returns an {@link ExperimentInterface} implementation that can:
	 *
	 * 1. Get information about the user's (more precisely, the subject's) enrollment in the experiment; and
	 * 2. Send analytics events relating to the experiment
	 *
	 * For example:
	 *
	 * ```
	 * $e = $experimentManager->getExperiment( 'my-awesome-experiment' );
	 * $e->send( 'page-visited' );
	 *
	 * if ( $experiment->isAssignedGroup( 'treatment' ) ) {
	 *     $out->addModule( 'my.awesome.module' );
	 * }
	 * ```
	 *
	 * @param string $experimentName
	 * @return ExperimentInterface
	 */
	public function getExperiment( string $experimentName ): ExperimentInterface;
}
