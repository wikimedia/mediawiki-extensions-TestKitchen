QUnit.module( 'ext.testKitchen/useFakeInstruments', QUnit.newMwEnvironment() );

QUnit.test( 'it should replace and restore mw.testKitchen.getInstruments', ( assert ) => {
	const oldGetInstrument = mw.testKitchen.getInstrument;
	const tk = mw.testKitchen.useFakeInstruments();

	assert.notStrictEqual( mw.testKitchen.getInstrument, oldGetInstrument );

	tk.restore();

	assert.strictEqual( mw.testKitchen.getInstrument, oldGetInstrument );
} );

QUnit.test( 'it should stub instruments', ( assert ) => {
	const tk = mw.testKitchen.useFakeInstruments();
	const expected = tk.stubInstrument( 'foo' );
	const actual = mw.testKitchen.getInstrument( 'foo' );

	assert.strictEqual( actual, expected );

	tk.restore();
} );

QUnit.test( 'it should track events (with defaults)', ( assert ) => {
	const tk = mw.testKitchen.useFakeInstruments();
	const i = tk.stubInstrument( 'foo' );

	mw.testKitchen.getInstrument( 'foo' )
		.send( 'action' );

	assert.strictEqual( i.eventCount, 1 );
	assert.strictEqual( i.events[ 0 ].action, 'action' );
	assert.propEqual( i.events[ 0 ].interactionData, {} );
	assert.deepEqual( i.events[ 0 ].contextualAttributes, undefined );

	tk.restore();
} );

QUnit.test( 'it should track events', ( assert ) => {
	const tk = mw.testKitchen.useFakeInstruments();
	const i = tk.stubInstrument( 'foo' );

	mw.testKitchen.getInstrument( 'foo' )
		.send(
			'action',
			{
				foo: 'bar'
			}
		);

	assert.strictEqual( i.eventCount, 1 );
	assert.strictEqual( i.events[ 0 ].action, 'action' );
	assert.propEqual(
		i.events[ 0 ].interactionData,
		{ foo: 'bar' }
	);
	assert.deepEqual( i.events[ 0 ].contextualAttributes, undefined );

	tk.restore();
} );

QUnit.test( 'it should track all events', ( assert ) => {
	const tk = mw.testKitchen.useFakeExperiments();
	const i1 = tk.stubExperiment( 'foo' );
	const i2 = tk.stubExperiment( 'bar' );

	mw.testKitchen.getExperiment( 'foo' )
		.send( 'action' );

	mw.testKitchen.getExperiment( 'bar' )
		.send( 'action' );

	assert.strictEqual( i1.eventCount, 1 );
	assert.strictEqual( i2.eventCount, 1 );

	assert.strictEqual( tk.globalEventCount, 2 );

	tk.restore();
} );

QUnit.test( 'it should handle instruments that aren\'t stubbed', ( assert ) => {
	const tk = mw.testKitchen.useFakeExperiments();
	const i = mw.testKitchen.getInstrument( 'foo' );

	assert.strictEqual( i.constructor.name, 'UnsampledInstrument' );

	tk.restore();
} );
