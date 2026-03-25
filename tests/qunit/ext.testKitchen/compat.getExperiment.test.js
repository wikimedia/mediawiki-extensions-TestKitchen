QUnit.module( 'ext.testKitchen.compat/getExperiment()', QUnit.newMwEnvironment( {
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
			overrides: []
		}
	},
	beforeEach() {
		mw.testKitchen.setConfig( {
			EveryoneExperimentEventIntakeServiceUrl: 'http://everyone.experiments',
			LoggedInExperimentEventIntakeServiceUrl: 'http://logged-in.experiments',
			InstrumentEventIntakeServiceUrl: 'http://instrument',
			experimentConfigs: {
				fruit: {
					user_identifier_type: 'mw-user',
					sample_rate: { default: 0 },
					groups: [ 'control', 'tropical' ],
					stream_name: 'product_metrics.web_base',
					schema_id: '/analytics/product_metrics/web/base/2.0.0',
					contextual_attributes: [
						'mediawiki_database',
						'page_namespace'
					]
				},
				dessert: {
					user_identifier_type: 'mw-user',
					sample_rate: { default: 0 },
					groups: [ 'control', 'ice-cream' ],
					stream_name: 'product_metrics.web_base',
					schema_id: '/analytics/product_metrics/web/base/2.0.0',
					contextual_attributes: [
						'mediawiki_database',
						'page_namespace'
					]
				},
				supper: {
					user_identifier_type: 'edge-unique',
					sample_rate: { default: 0 },
					groups: [ 'control', 'fish-pie' ],
					stream_name: 'product_metrics.web_base',
					schema_id: '/analytics/product_metrics/web/base/2.0.0',
					contextual_attributes: [
						'mediawiki_database',
						'page_namespace'
					]
				}
			},
			instrumentConfigs: {},
			streamNameToContextualAttributesMap: {
				'product_metrics.custom_stream': [ 'page_id', 'page_title' ]
			}
		} );

		this.originalMPOCookie = mw.cookie.get( 'mpo' );
		mw.cookie.set( 'mpo', null );
	},
	afterEach() {
		mw.cookie.set( 'mpo', this.originalMPOCookie );

		mw.testKitchen.resetConfig();

		require( 'ext.testKitchen/enrollmentConfig.js' ).reset();
	}
} ) );

QUnit.test( 'it handles invalid config', ( assert ) => {
	// Test cases for when $wgTestKitchenEnableExperiments is falsy
	// (wgTestKitchenUserExperiments will be undefined).

	delete mw.config.values.wgTestKitchenUserExperiments;

	const e = mw.testKitchen.compat.getExperiment( 'an_experiment_name' );

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
			mw.testKitchen.compat.getExperiment( experimentName ).getAssignedGroup(),
			expectedAssignedGroup
		);
	}
);

QUnit.test( 'it handles overridden experiment', ( assert ) => {
	mw.testKitchen.overrideExperimentGroup( 'fruit', 'gooseberry' );

	const e = mw.testKitchen.compat.getExperiment( 'fruit' );

	assert.true( e instanceof mw.testKitchen.OverriddenExperiment );
	assert.strictEqual( e.getAssignedGroup(), 'gooseberry' );
} );

QUnit.test( 'it sets event intake service URL', ( assert ) => {
	const e = mw.testKitchen.compat.getExperiment( 'fruit' );

	assert.strictEqual( e.eventIntakeServiceUrl, 'http://logged-in.experiments' );

	const e2 = mw.testKitchen.compat.getExperiment( 'supper' );

	assert.strictEqual( e2.eventIntakeServiceUrl, 'http://everyone.experiments' );
} );

QUnit.test( 'it sets stream, schema, and contextual attributes', ( assert ) => {
	const e = mw.testKitchen.compat.getExperiment( 'fruit' );

	assert.strictEqual( e.streamName, 'product_metrics.web_base' );
	assert.strictEqual( e.schemaID, '/analytics/product_metrics/web/base/2.0.0' );
	assert.deepEqual( e.contextualAttributes, [
		'mediawiki_database',
		'page_namespace'
	] );
} );

QUnit.test( 'it passes through correct contextual attributes when stream is set', ( assert ) => {
	const e = mw.testKitchen.compat.getExperiment( 'fruit' );

	assert.deepEqual(
		e.streamNameToContextualAttributesMap,
		{
			'product_metrics.custom_stream': [ 'page_id', 'page_title' ]
		}
	);

	e.setStream( 'product_metrics.custom_stream' );

	assert.deepEqual(
		e.contextualAttributes,
		[ 'page_id', 'page_title' ],
		'uses contextual attributes from the stream map when stream is set'
	);
} );
