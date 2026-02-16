QUnit.module( 'ext.testKitchen/getInstrument()', QUnit.newMwEnvironment( {
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
			instrumentConfigs: {
				'my-awesome-instrument-without-sample': {
					stream_name: 'my-awesome-stream',
					contextual_attributes: [
						'page_id',
						'page_name'
					]
				},
				'my-awesome-instrument': {
					sample: {
						unit: 'session',
						rate: 1.0
					},
					stream_name: 'my-awesome-stream',
					contextual_attributes: [
						'page_namespace_id',
						'page_namespace_name',
						'page_revision_id'
					]
				}
			}
		} );
	},
	afterEach() {
		mw.testKitchen.resetConfig();
	}
} ) );

QUnit.test( 'it handles active instruments with no sample config', ( assert ) => {
	const i = mw.testKitchen.getInstrument( 'my-awesome-instrument-without-sample' );

	assert.true( i instanceof mw.testKitchen.Instrument, true );
} );

QUnit.test( 'it handles sampled active instruments', ( assert ) => {
	const i = mw.testKitchen.getInstrument( 'my-awesome-instrument' );

	assert.true( i instanceof mw.testKitchen.Instrument, true );
} );

QUnit.test( 'it handles unknown instruments', ( assert ) => {
	const i = mw.testKitchen.getInstrument( 'foo' );

	assert.true( i instanceof mw.testKitchen.UnsampledInstrument );
} );
