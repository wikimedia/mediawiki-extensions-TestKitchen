QUnit.module( 'ext.testKitchen/getExperimentsByPrefix()', QUnit.newMwEnvironment( {
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
	const experiments = mw.testKitchen.getExperimentsByPrefix( 'an_experiment_name' );

	assert.deepEqual( experiments, [] );
} );

QUnit.test( 'it handles an exact match', ( assert ) => {
	const experiments = mw.testKitchen.getExperimentsByPrefix( 'bar' );

	assert.strictEqual( experiments.length, 1 );
	assert.strictEqual( experiments[ 0 ].getAssignedGroup(), 'quux' );
} );

QUnit.test( 'it handles matches', ( assert ) => {
	const experiments = mw.testKitchen.getExperimentsByPrefix( 'foo' );

	assert.strictEqual( experiments.length, 3 );
	assert.strictEqual( experiments[ 0 ].getAssignedGroup(), 'bar' );
	assert.strictEqual( experiments[ 1 ].getAssignedGroup(), 'baz' );
	assert.strictEqual( experiments[ 2 ].getAssignedGroup(), 'qux' );
} );

QUnit.test( 'it handles invalid config', ( assert ) => {
	// Test cases for when $wgTestKitchenEnableExperiments is falsy
	// (wgTestKitchenUserExperiments will be undefined).

	delete mw.config.values.wgTestKitchenUserExperiments;

	const experiments = mw.testKitchen.getExperimentsByPrefix( 'an_experiment_name' );

	assert.deepEqual( experiments, [] );
} );
