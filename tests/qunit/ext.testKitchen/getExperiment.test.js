QUnit.module( 'ext.testKitchen/getExperiment()', QUnit.newMwEnvironment( {
	config: {
		wgTestKitchenUserExperiments: {
			enrolled: [
				'fruit',
				'dessert',
				'supper'
			],
			assigned: {
				fruit: 'tropical',
				dessert: 'ice-cream',
				supper: 'fish-pie'
			},
			subject_ids: {
				fruit: '2def9a8f9d8c4f0296268a1c3d2e7fba90298e704070d946536166c832d05652',
				dessert: '788a1970cc9b665222de25cc1a79da7ee1fcaf69b674caba188233ad995ba3d4',
				supper: 'awaiting'
			},
			sampling_units: {
				fruit: 'mw-user',
				dessert: 'mw-user',
				supper: 'edge-unique'
			},
			active_experiments: [
				'fruit',
				'dessert',
				'lunch',
				'supper'
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

QUnit.test( 'it handles invalid config', ( assert ) => {
	// Test cases for when $wgTestKitchenEnableExperiments is falsy
	// (wgTestKitchenUserExperiments will be undefined).

	delete mw.config.values.wgTestKitchenUserExperiments;

	const e = mw.testKitchen.getExperiment( 'an_experiment_name' );

	assert.true( e instanceof mw.testKitchen.UnenrolledExperiment );
	assert.strictEqual( e.getAssignedGroup(), null );
} );

QUnit.test.each(
	'it',
	{
		'handles unknown experiment': [ 'elevenses', null ],
		'handles active experiment with no enrollment': [ 'lunch', null ],
		'handles active experiment with enrollment': [ 'fruit', 'tropical' ]
	},
	( assert, [ experimentName, expectedAssignedGroup ] ) => {
		assert.strictEqual(
			mw.testKitchen.getExperiment( experimentName ).getAssignedGroup(),
			expectedAssignedGroup
		);
	}
);

QUnit.test( 'it handles overridden experiment', ( assert ) => {
	mw.config.set( 'wgTestKitchenUserExperiments', {
		enrolled: [
			'fruit'
		],
		assigned: {
			fruit: 'gooseberry'
		},
		subject_ids: {
			fruit: 'overridden'
		},
		sampling_units: {
			fruit: 'overridden'
		},
		active_experiments: [
			'fruit'
		],
		overrides: [ 'fruit' ]
	} );

	const e = mw.testKitchen.getExperiment( 'fruit' );

	assert.true( e instanceof mw.testKitchen.OverriddenExperiment );
	assert.strictEqual( e.getAssignedGroup(), 'gooseberry' );
} );

QUnit.test( 'it sets event intake service URL', ( assert ) => {
	const e = mw.testKitchen.getExperiment( 'fruit' );

	assert.strictEqual( e.eventIntakeServiceUrl, 'http://logged-in.experiments' );

	const e2 = mw.testKitchen.getExperiment( 'supper' );

	assert.strictEqual( e2.eventIntakeServiceUrl, 'http://everyone.experiments' );
} );

QUnit.test( 'it sets stream, schema, and contextual attributes', ( assert ) => {
	const e = mw.testKitchen.getExperiment( 'fruit' );

	assert.strictEqual( e.streamName, 'product_metrics.web_base' );
	assert.strictEqual( e.schemaID, '/analytics/product_metrics/web/base/2.0.0' );
	assert.deepEqual( e.contextualAttributes, [
		'mediawiki_database',
		'page_namespace'
	] );
} );

QUnit.test( 'it passes through experiment configs', ( assert ) => {
	const e = mw.testKitchen.getExperiment( 'fruit' );

	assert.deepEqual(
		e.experimentConfigs,
		{
			'product_metrics.web_base': {
				contextual_attributes: [
					'mediawiki_database',
					'page_namespace'
				]
			}
		}
	);
} );
