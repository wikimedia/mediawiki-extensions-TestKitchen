QUnit.module( 'ext.testKitchen/useFakeExperiments', QUnit.newMwEnvironment() );

QUnit.test( 'it should replace and restore mw.testKitchen.getExperiments', ( assert ) => {
	const oldGetExperiments = mw.testKitchen.getExperiment;
	const tk = mw.testKitchen.useFakeExperiments();

	assert.notStrictEqual( mw.testKitchen.getExperiment, oldGetExperiments );

	tk.restore();

	assert.strictEqual( mw.testKitchen.getExperiment, oldGetExperiments );
} );

QUnit.test( 'it should stub experiments', ( assert ) => {
	const tk = mw.testKitchen.useFakeExperiments();
	const expected = tk.stubExperiment( 'foo', 'bar' );
	const actual = mw.testKitchen.getExperiment( 'foo' );

	assert.strictEqual( actual, expected );
	assert.strictEqual( actual.getAssignedGroup(), 'bar' );

	tk.restore();
} );

QUnit.test( 'it should track events (with defaults)', ( assert ) => {
	const tk = mw.testKitchen.useFakeExperiments();
	const e = tk.stubExperiment( 'foo', 'bar' );

	mw.testKitchen.getExperiment( 'foo' )
		.send( 'action' );

	assert.strictEqual( e.eventCount, 1 );
	assert.strictEqual( e.events[ 0 ].action, 'action' );
	assert.propEqual( e.events[ 0 ].interactionData, {} );

	tk.restore();
} );

QUnit.test( 'it should track events', ( assert ) => {
	const tk = mw.testKitchen.useFakeExperiments();
	const e = tk.stubExperiment( 'foo', 'bar' );

	mw.testKitchen.getExperiment( 'foo' )
		.send(
			'action',
			{
				foo: 'bar'
			},
			[ 'baz' ]
		);

	assert.strictEqual( e.eventCount, 1 );
	assert.strictEqual( e.events[ 0 ].action, 'action' );
	assert.propEqual(
		e.events[ 0 ].interactionData,
		{ foo: 'bar' }
	);
	assert.deepEqual( e.events[ 0 ].contextualAttributes, [ 'baz' ] );

	tk.restore();
} );

QUnit.test( 'it should track exposure events', ( assert ) => {
	const tk = mw.testKitchen.useFakeExperiments();
	const e = tk.stubExperiment( 'foo', 'bar' );

	mw.testKitchen.getExperiment( 'foo' )
		.sendExposure();

	assert.strictEqual( e.eventCount, 1 );
	assert.strictEqual( e.events[ 0 ].action, 'experiment_exposure' );

	tk.restore();
} );

QUnit.test( 'it should track all events', ( assert ) => {
	const tk = mw.testKitchen.useFakeExperiments();
	const e1 = tk.stubExperiment( 'foo', 'bar' );
	const e2 = tk.stubExperiment( 'bar', 'baz' );

	mw.testKitchen.getExperiment( 'foo' )
		.send( 'action' );

	mw.testKitchen.getExperiment( 'bar' )
		.send( 'action' );

	assert.strictEqual( e1.eventCount, 1 );
	assert.strictEqual( e2.eventCount, 1 );

	assert.strictEqual( tk.globalEventCount, 2 );

	tk.restore();
} );

QUnit.test( 'it should handle experiments that aren\'t stubbed', ( assert ) => {
	const tk = mw.testKitchen.useFakeExperiments();
	const e = mw.testKitchen.getExperiment( 'foo' );

	assert.strictEqual( e.constructor.name, 'UnenrolledExperiment' );
	assert.strictEqual( e.getAssignedGroup(), null );

	tk.restore();
} );
