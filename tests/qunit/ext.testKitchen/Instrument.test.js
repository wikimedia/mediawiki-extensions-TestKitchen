QUnit.module( 'ext.testKitchen/Instrument', QUnit.newMwEnvironment( {
	beforeEach: function () {
		const { Instrument } = mw.testKitchen;

		// Stubs
		// =====

		const eventFactory = {
			newEvent() {}
		};

		this.newEventStub = this.sandbox.stub( eventFactory, 'newEvent' );

		const eventSender = {
			sendEvent() {}
		};

		this.sendEventStub = this.sandbox.stub( eventSender, 'sendEvent' );

		// Code Under Test
		// ===============

		this.instrument = new Instrument(
			eventFactory,
			eventSender,
			'https://foo.bar/baz?qux=quux',
			{
				sample: {
					unit: 'session',
					rate: 1.0
				},
				stream_name: 'my-awesome-stream',
				schema_id: '/my/awesome/schema/1.0.0',
				contextual_attributes: [
					'namespace_name',
					'performer_pageview_id'
				]
			}
		);
	}
} ) );

QUnit.test( 'send()', function ( assert ) {
	const expectedEvent = {
		$schema: '/my/awesome/schema/1.0.0',
		meta: {
			domain: 'my-awesome-server-name',
			stream: 'my-awesome-stream'
		},
		dt: new Date().toISOString()
	};

	this.newEventStub.returns( expectedEvent );

	this.instrument.send( 'my-awesome-action', {
		foo: 'bar'
	} );

	assert.strictEqual( this.newEventStub.callCount, 1 );
	assert.deepEqual( this.newEventStub.firstCall.args, [
		'my-awesome-stream',
		'/my/awesome/schema/1.0.0',
		[
			'namespace_name',
			'performer_pageview_id'
		],
		'my-awesome-action',
		{
			foo: 'bar',
			funnel_event_sequence_position: 1
		}
	] );

	assert.strictEqual( this.sendEventStub.callCount, 1 );
	assert.deepEqual( this.sendEventStub.firstCall.args, [
		expectedEvent,
		'https://foo.bar/baz?qux=quux'
	] );
} );

QUnit.test( 'send() - increments FESP', function ( assert ) {
	this.instrument.send( 'my-awesome-action' );
	this.instrument.send( 'my-awesome-action' );

	assert.strictEqual( this.newEventStub.callCount, 2 );
	assert.strictEqual( this.newEventStub.firstCall.args[ 4 ].funnel_event_sequence_position, 1 );
	assert.strictEqual( this.newEventStub.secondCall.args[ 4 ].funnel_event_sequence_position, 2 );
} );

QUnit.test( 'send() - can\'t override FESP', function ( assert ) {
	this.instrument.send( 'my-awesome-action', {
		funnel_event_sequence_position: 10
	} );

	assert.strictEqual( this.newEventStub.firstCall.args[ 4 ].funnel_event_sequence_position, 1 );
} );

QUnit.test( 'setSchema()', function ( assert ) {
	const actualInstrument = this.instrument.setSchema( '/my/other/awesome/schema/1.0.0' );

	assert.strictEqual( actualInstrument, this.instrument );

	this.instrument.send( 'my-awesome-action' );

	assert.strictEqual( this.newEventStub.firstCall.args[ 1 ], '/my/other/awesome/schema/1.0.0' );
} );
