QUnit.module( 'ext.testKitchen/getExperimentByPrefix()', QUnit.newMwEnvironment( {
	config: {
		wgTestKitchenUserExperiments: {
			enrolled: [
				'foo-1',
				'foo-2',
				'foo-3',
				'bar'
			],
			assigned: {
				'foo-1': 'bar',
				'foo-2': 'baz',
				'foo-3': 'qux',
				bar: 'quux'
			},
			subject_ids: {
				'foo-1': 'awaiting',
				'foo-2': 'awaiting',
				'foo-3': 'awaiting',
				bar: 'awaiting'
			},
			sampling_units: {
				'foo-1': 'edge-unique',
				'foo-2': 'edge-unique',
				'foo-3': 'edge-unique',
				bar: 'edge-unique'

			},
			active_experiments: [
				'foo-1',
				'foo-2',
				'foo-3',
				'bar',
				'baz'
			],
			overrides: []
		}
	},
	beforeEach() {
		mw.testKitchen.setConfig( {
			EveryoneExperimentEventIntakeServiceUrl: 'http://everyone.experiments',
			LoggedInExperimentEventIntakeServiceUrl: 'http://logged-in.experiments',
			InstrumentEventIntakeServiceUrl: 'http://instrument',
			experimentConfigs: {
				'product_metrics.web_base': {
					contextual_attributes: [
						'mediawiki_database',
						'page_namespace'
					]
				}
			},
			instrumentConfigs: {}
		} );
	},
	afterEach() {
		mw.testKitchen.resetConfig();
	}
} ) );

QUnit.test( 'it handles an unknown experiment', ( assert ) => {
	const e = mw.testKitchen.getExperimentByPrefix( 'an_experiment_name' );

	assert.true( e instanceof mw.testKitchen.UnenrolledExperiment );
	assert.strictEqual( e.getAssignedGroup(), null );
} );

QUnit.test( 'it sorts the experiment names', ( assert ) => {
	const e = mw.testKitchen.getExperimentByPrefix( 'foo-' );

	assert.true( e instanceof mw.testKitchen.Experiment );
	assert.strictEqual( e.getAssignedGroup(), 'qux' );
} );

QUnit.test( 'it handles an exact match', ( assert ) => {
	const e = mw.testKitchen.getExperimentByPrefix( 'bar' );

	assert.true( e instanceof mw.testKitchen.Experiment );
	assert.strictEqual( e.getAssignedGroup(), 'quux' );
} );

QUnit.test( 'it handles invalid config', ( assert ) => {
	// Test cases for when $wgTestKitchenEnableExperiments is falsy
	// (wgTestKitchenUserExperiments will be undefined).

	delete mw.config.values.wgTestKitchenUserExperiments;

	const e = mw.testKitchen.getExperimentByPrefix( 'an_experiment_name' );

	assert.true( e instanceof mw.testKitchen.UnenrolledExperiment );
	assert.strictEqual( e.getAssignedGroup(), null );
} );
