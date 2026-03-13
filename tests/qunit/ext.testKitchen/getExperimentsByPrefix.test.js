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
			overrides: []
		}
	},
	beforeEach() {
		mw.testKitchen.setConfig( {
			EveryoneExperimentEventIntakeServiceUrl: 'http://everyone.experiments',
			LoggedInExperimentEventIntakeServiceUrl: 'http://logged-in.experiments',
			InstrumentEventIntakeServiceUrl: 'http://instrument',
			experimentConfigs: {
				'foo-1': {
					user_identifier_type: 'edge-unique',
					sample_rate: { default: 0 },
					groups: [ 'control', 'bar' ],
					stream_name: 'product_metrics.web_base',
					schema_id: '/analytics/product_metrics/web/base/2.0.0',
					contextual_attributes: [
						'mediawiki_database',
						'page_namespace'
					]
				},
				'foo-2': {
					user_identifier_type: 'edge-unique',
					sample_rate: { default: 0 },
					groups: [ 'control', 'baz' ],
					stream_name: 'product_metrics.web_base',
					schema_id: '/analytics/product_metrics/web/base/2.0.0',
					contextual_attributes: [
						'mediawiki_database',
						'page_namespace'
					]
				},
				'foo-3': {
					user_identifier_type: 'edge-unique',
					sample_rate: { default: 0 },
					groups: [ 'control', 'qux' ],
					stream_name: 'product_metrics.web_base',
					schema_id: '/analytics/product_metrics/web/base/2.0.0',
					contextual_attributes: [
						'mediawiki_database',
						'page_namespace'
					]
				},
				bar: {
					user_identifier_type: 'edge-unique',
					sample_rate: { default: 0 },
					groups: [ 'control', 'quux' ],
					stream_name: 'product_metrics.web_base',
					schema_id: '/analytics/product_metrics/web/base/2.0.0',
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
