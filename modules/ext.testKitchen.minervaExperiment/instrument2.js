/**
 * A simple experiment-specific instrument that sends an event if the current user is
 * enrolled in the "minerva-experiment-aaa" experiment and assigned to a group.
 *
 * See https://phabricator.wikimedia.org/T418614 for more context.
 */

mw.loader.using( 'ext.testKitchen' ).then( () => {
	const EXPERIMENT_NAME = 'minerva-experiment-aaa';
	const ACTION = 'module_loaded';
	const ACTION_CONTEXT = 'treatment-b';
	const ACTION_SOURCE = 'instrument2.js';

	mw.testKitchen.getExperiment( EXPERIMENT_NAME )
		.send(
			ACTION,
			{
				action_context: ACTION_CONTEXT,
				action_source: ACTION_SOURCE
			}
		);
} );
