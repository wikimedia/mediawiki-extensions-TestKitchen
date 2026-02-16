QUnit.module( 'ext.testKitchen/Experiment', QUnit.newMwEnvironment( {
	beforeEach: function () {
		const { Experiment } = mw.testKitchen;

		const experimentConfigs = {
			my_awesome_stream: {
				contextual_attributes: [
					'performer_id',
					'performer_name'
				]
			}
		};

		// Stubs
		// =====

		this.expectedEvent = {
			$schema: '/analytics/product_metrics/web/base/2.0.0',
			dt: new Date().toISOString()
		};

		const eventFactory = {
			newEvent() {}
		};

		this.newEventStub = this.sandbox.stub( eventFactory, 'newEvent' )
			.returns( this.expectedEvent );

		const eventSender = {
			sendEvent() {}
		};

		this.sendEventStub = this.sandbox.stub( eventSender, 'sendEvent' );

		// Code Under Test
		// ===============

		// Note well that Experiment#constructor() is package-private. Calling it outside Test
		// Kitchen is not supported.

		this.everyoneExperiment = new Experiment(
			eventFactory,
			eventSender,
			'http://foo.bar/baz?qux=quux',
			experimentConfigs,
			{
				enrolled: 'hello_world',
				assigned: 'A',
				subject_id: 'awaiting',
				sampling_unit: 'edge-unique',
				coordinator: 'default',
				stream_name: 'product_metrics.web_base',
				schema_id: '/analytics/product_metrics/web/base/2.0.0',
				contextual_attributes: [
					'performer_pageview_id',
					'mediawiki_database'
				]
			}
		);

		this.loggedInExperiment = new Experiment(
			eventFactory,
			eventSender,
			'http://foo.bar/baz?qux=quux',
			experimentConfigs,
			{
				enrolled: 'my-awesome-experiment',
				assigned: 'B',
				subject_id: '0x0ff1ce',
				sampling_unit: 'mw-user',
				coordinator: 'default',
				stream_name: 'product_metrics.web_base',
				schema_id: '/analytics/product_metrics/web/base/2.0.0',
				contextual_attributes: [
					'performer_pageview_id',
					'mediawiki_database'
				]
			}
		);
	}
} ) );

QUnit.test.each(
	'isAssignedGroup()',
	{
		A: [ 'A', true ],
		B: [ 'B', false ],
		'Multiple, including A': [ [ 'B', 'A' ], true ],
		'Multiple, excluding A': [ [ 'B', 'C' ], false ]
	},
	function ( assert, [ groups, expected ] ) {
		assert.strictEqual( this.everyoneExperiment.isAssignedGroup( ...groups ), expected );
	}
);

QUnit.test.each(
	'send()',
	[
		[
			'everyoneExperiment',
			{
				enrolled: 'hello_world',
				assigned: 'A',
				subject_id: 'awaiting',
				sampling_unit: 'edge-unique',
				coordinator: 'default'
			}
		],
		[
			'loggedInExperiment',
			{
				enrolled: 'my-awesome-experiment',
				assigned: 'B',
				subject_id: '0x0ff1ce',
				sampling_unit: 'mw-user',
				coordinator: 'default'
			}
		]
	],
	function ( assert, [ propertyName, expectedExperiment ] ) {
		this[ propertyName ].send( 'Hello, World!' );

		assert.strictEqual( this.newEventStub.callCount, 1 );
		assert.deepEqual( this.newEventStub.firstCall.args, [
			'product_metrics.web_base',
			'/analytics/product_metrics/web/base/2.0.0',
			[
				'performer_pageview_id',
				'mediawiki_database'
			],
			'Hello, World!',
			{
				experiment: expectedExperiment
			}
		] );

		assert.strictEqual( this.sendEventStub.callCount, 1 );
	}
);

QUnit.test( 'send() - can\'t override experiment', function ( assert ) {
	this.everyoneExperiment.send( 'Hello, World!', {
		experiment: {
			foo: 'bar',
			baz: 'qux'
		}
	} );

	assert.strictEqual( this.newEventStub.callCount, 1 );
	assert.deepEqual( this.newEventStub.firstCall.args, [
		'product_metrics.web_base',
		'/analytics/product_metrics/web/base/2.0.0',
		[
			'performer_pageview_id',
			'mediawiki_database'
		],
		'Hello, World!',
		{
			experiment: {
				enrolled: 'hello_world',
				assigned: 'A',
				subject_id: 'awaiting',
				sampling_unit: 'edge-unique',
				coordinator: 'default'
			}
		}
	] );

	assert.strictEqual( this.sendEventStub.callCount, 1 );
} );

QUnit.test( 'send() - overriding stream and schema', function ( assert ) {
	this.everyoneExperiment.setStream( 'my_awesome_stream' )
		.setSchema( '/my/awesome/schema/0.0.1' )
		.send( 'Hello, World!' );

	assert.strictEqual( this.newEventStub.callCount, 1 );
	assert.deepEqual( this.newEventStub.firstCall.args, [
		'my_awesome_stream',
		'/my/awesome/schema/0.0.1',
		[
			'performer_id',
			'performer_name'
		],
		'Hello, World!',
		{
			experiment: {
				enrolled: 'hello_world',
				assigned: 'A',
				subject_id: 'awaiting',
				sampling_unit: 'edge-unique',
				coordinator: 'default'
			}
		}
	] );
} );

QUnit.test( 'setStream() - warns when stream isn\'t registered', function ( assert ) {
	this.sandbox.stub( console, 'warn' );

	this.everyoneExperiment.setStream( 'my_other_awesome_stream' );

	// eslint-disable-next-line no-console
	assert.strictEqual( console.warn.callCount, 1 );

	assert.deepEqual( this.everyoneExperiment.contextualAttributes, [] );
} );

QUnit.test.each(
	'sendExposure()',
	[
		[
			'everyoneExperiment',
			{
				enrolled: 'hello_world',
				assigned: 'A',
				subject_id: 'awaiting',
				sampling_unit: 'edge-unique',
				coordinator: 'default'
			}
		],
		[
			'loggedInExperiment',
			{
				enrolled: 'my-awesome-experiment',
				assigned: 'B',
				subject_id: '0x0ff1ce',
				sampling_unit: 'mw-user',
				coordinator: 'default'
			}
		]
	],
	function ( assert, [ propertyName, expectedExperiment ] ) {
		this[ propertyName ].sendExposure();

		assert.strictEqual( this.newEventStub.callCount, 1 );
		assert.deepEqual( this.newEventStub.firstCall.args, [
			'product_metrics.web_base',
			'/analytics/product_metrics/web/base/2.0.0',
			[
				'performer_pageview_id',
				'mediawiki_database'
			],
			'experiment_exposure',
			{
				experiment: expectedExperiment
			}
		] );
	}
);

// ---

QUnit.module( 'ext.testKitchen/UnenrolledExperiment' );

QUnit.test( 'setStream() - doesn\'t trigger an error', ( assert ) => {
	const e = new mw.testKitchen.UnenrolledExperiment( 'hello_world' );

	assert.strictEqual( e.setStream( 'my_awesome_stream' ), e );
} );

QUnit.test( 'setSchema() - doesn\'t trigger an error', ( assert ) => {
	const e = new mw.testKitchen.UnenrolledExperiment( 'hello_world' );

	assert.strictEqual( e.setSchema( 'my_awesome_stream' ), e );
} );

// ---

QUnit.module( 'ext.testKitchen/OverriddenExperiment', QUnit.newMwEnvironment( {
	beforeEach: function () {
		this.experiment = new mw.testKitchen.OverriddenExperiment( 'hello_world', 'foo ' );
	}
} ) );

QUnit.test( 'send()', function ( assert ) {
	const action = 'Hello, World!';
	const interactionData = {
		experiment: {
			foo: 'bar',
			baz: 'qux'
		}
	};

	const logStub = this.sandbox.stub( console, 'log' );

	this.experiment.send( action, interactionData );

	assert.strictEqual( logStub.callCount, 1 );
	assert.deepEqual( logStub.firstCall.args, [
		'hello_world: The enrollment for this experiment has been overridden. The following event will not be sent:\n',
		action,
		JSON.stringify( interactionData, null, 2 )
	] );
} );

QUnit.test( 'setStream() - doesn\'t trigger an error', function ( assert ) {
	assert.strictEqual( this.experiment.setStream( 'my_awesome_stream' ), this.experiment );
} );

QUnit.test( 'setSchema() - doesn\'t trigger an error', function ( assert ) {
	assert.strictEqual( this.experiment.setSchema( 'my_awesome_stream' ), this.experiment );
} );

QUnit.test( 'sendExposure()', function ( assert ) {
	assert.expect( 0 );

	this.sandbox.mock( console )
		.expects( 'log' )
		.once()
		.withExactArgs(
			'hello_world: The enrollment for this experiment has been overridden. The following event will not be sent:\n',
			'experiment_exposure',
			undefined
		);

	const e = new mw.testKitchen.OverriddenExperiment(
		'hello_world',
		'foo'
	);

	e.sendExposure( 'experiment_exposure' );
} );
