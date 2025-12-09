/**
 * A simple experiment-specific instrument that sends a "testKitchen-loaded" event if the current user is
 * enrolled in the "test-kitchen-mw-module-loaded-v2" experiment and assigned to the treatment group.
 *
 * See https://phabricator.wikimedia.org/T403507 for more context.
 */

const experiment = mw.testKitchen.getExperiment( 'test-kitchen-mw-module-loaded-v2' );

if ( experiment.isAssignedGroup( 'control', 'treatment' ) ) {
	experiment.send(
		'testKitchen-loaded',
		{
			instrument_name: 'TestKitchenMediaWikiModuleLoaded'
		}
	);
}
