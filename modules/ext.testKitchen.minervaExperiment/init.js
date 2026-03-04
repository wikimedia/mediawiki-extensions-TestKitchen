/**
 * A simple experiment-specific instrument that sends an event if the current user is
 * enrolled in the "minerva-experiment-aaa" experiment and assigned to a group.
 *
 * See https://phabricator.wikimedia.org/T418614 for more context.
 */

mw.loader.using( 'ext.testKitchen' ).then( () => {
	const EXPERIMENT_NAME = 'minerva-experiment-aaa';
	const ACTION = 'page_visit';
	const ACTION_SOURCE = 'init.js';

	mw.testKitchen.getExperiment( EXPERIMENT_NAME )
		.send(
			ACTION,
			{ action_source: ACTION_SOURCE }
		);
} );
