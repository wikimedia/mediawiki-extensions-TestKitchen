QUnit.module( 'ext.testKitchen/Experiment', QUnit.newMwEnvironment( {
	beforeEach: function () {
		const { Experiment } = mw.testKitchen;

		// Stubs
		// =====

		this.expectedEvent = {
			$schema: '/analytics/product_metrics/web/base/2.2.0',
			dt: new Date().toISOString()
		};

		const eventFactory = {
			newEvent() {
			}
		};

		this.newEventStub = this.sandbox.stub( eventFactory, 'newEvent' )
			.returns( this.expectedEvent );

		const eventSender = {
			sendEvent() {
			}
		};

		this.sendEventStub = this.sandbox.stub( eventSender, 'sendEvent' );

		// Code Under Test
		// ===============

		// Note well that Experiment#constructor() is package-private. Calling it outside Test
		// Kitchen is not supported.

		this.exposureLogTracker = {
			exposuresThisPage: new Set(),
			makeKey: this.sandbox.stub().returns( 'key' ),
			trySend: this.sandbox.stub().callsFake( ( key, sendFn ) => {
				sendFn();
			} )
		};

		/**
		 * @param {mw.testKitchen.ExperimentConfig} experimentConfig
		 * @return {mw.testKitchen.Experiment}
		 */
		this.newExperiment = function ( experimentConfig ) {
			return new Experiment(
				eventFactory,
				eventSender,
				'http://foo.bar/baz?qux=quux',
				this.exposureLogTracker,
				experimentConfig
			);
		};

		this.everyoneExperiment = this.newExperiment( {
			enrolled: 'hello_world',
			assigned: 'A',
			subject_id: 'awaiting',
			sampling_unit: 'edge-unique',
			phase_index: 0,
			stream_name: 'product_metrics.web_base',
			schema_id: '/analytics/product_metrics/web/base/2.2.0',
			contextual_attributes: [
				'performer_pageview_id',
				'mediawiki_database'
			],
			exposure_version: 'v1',
			other_assigned: {
				foo: 'bar'
			}
		} );

		this.loggedInExperiment = this.newExperiment( {
			enrolled: 'my-awesome-experiment',
			assigned: 'B',
			subject_id: '0x0ff1ce',
			sampling_unit: 'mw-user',
			phase_index: 1,
			stream_name: 'product_metrics.web_base',
			schema_id: '/analytics/product_metrics/web/base/2.2.0',
			contextual_attributes: [
				'performer_pageview_id',
				'mediawiki_database'
			],
			exposure_version: 'v1',
			other_assigned: {
				bar: 'baz'
			}
		} );
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
	'send(action)',
	[
		[
			'everyoneExperiment',
			{
				enrolled: 'hello_world',
				assigned: 'A',
				subject_id: 'awaiting',
				sampling_unit: 'edge-unique',
				phase_index: 0,
				other_assigned: {
					foo: 'bar'
				},
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
				phase_index: 1,
				other_assigned: {
					bar: 'baz'
				},
				coordinator: 'default'
			}
		]
	],
	function ( assert, [ propertyName, expectedExperiment ] ) {
		this[ propertyName ].send( 'Hello, World!' );

		assert.strictEqual( this.newEventStub.callCount, 1 );
		assert.deepEqual( this.newEventStub.firstCall.args, [
			'product_metrics.web_base',
			'/analytics/product_metrics/web/base/2.2.0',
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

QUnit.test.each(
	'send(action, interactionData)',
	[
		[
			'everyoneExperiment',
			{
				enrolled: 'hello_world',
				assigned: 'A',
				subject_id: 'awaiting',
				sampling_unit: 'edge-unique',
				phase_index: 0,
				other_assigned: {
					foo: 'bar'
				},
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
				phase_index: 1,
				other_assigned: {
					bar: 'baz'
				},
				coordinator: 'default'
			}
		]
	],
	function ( assert, [ propertyName, expectedExperiment ] ) {
		this[ propertyName ].send( 'Hello, World!', { action_source: 'the-source' } );

		assert.strictEqual( this.newEventStub.callCount, 1 );
		assert.deepEqual( this.newEventStub.firstCall.args, [
			'product_metrics.web_base',
			'/analytics/product_metrics/web/base/2.2.0',
			[
				'performer_pageview_id',
				'mediawiki_database'
			],
			'Hello, World!',
			{
				action_source: 'the-source',
				experiment: expectedExperiment
			}
		] );

		assert.strictEqual( this.sendEventStub.callCount, 1 );
	}
);

QUnit.test.each(
	'send(action, {}, contextualAttributes)',
	[
		[
			'everyoneExperiment',
			{
				enrolled: 'hello_world',
				assigned: 'A',
				subject_id: 'awaiting',
				sampling_unit: 'edge-unique',
				phase_index: 0,
				other_assigned: {
					foo: 'bar'
				},
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
				phase_index: 1,
				other_assigned: {
					bar: 'baz'
				},
				coordinator: 'default'
			}
		]
	],
	function ( assert, [ propertyName, expectedExperiment ] ) {
		this[ propertyName ].send( 'Hello, World!', {}, [ 'performer_is_bot' ] );

		assert.strictEqual( this.newEventStub.callCount, 1 );
		assert.deepEqual( this.newEventStub.firstCall.args, [
			'product_metrics.web_base',
			'/analytics/product_metrics/web/base/2.2.0',
			[
				'performer_pageview_id',
				'mediawiki_database',
				'performer_is_bot'
			],
			'Hello, World!',
			{
				experiment: expectedExperiment
			}
		] );

		assert.strictEqual( this.sendEventStub.callCount, 1 );
	}
);

QUnit.test.each(
	'send(action, interactionData, contextualAttributes)',
	[
		[
			'everyoneExperiment',
			{
				enrolled: 'hello_world',
				assigned: 'A',
				subject_id: 'awaiting',
				sampling_unit: 'edge-unique',
				phase_index: 0,
				other_assigned: {
					foo: 'bar'
				},
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
				phase_index: 1,
				other_assigned: {
					bar: 'baz'
				},
				coordinator: 'default'
			}
		]
	],
	function ( assert, [ propertyName, expectedExperiment ] ) {
		this[ propertyName ].send( 'Hello, World!',
			{
				action_source: 'the_source',
				action_context: 'the_context'
			},
			[ 'performer_is_bot', 'performer_is_logged_in' ] );

		assert.strictEqual( this.newEventStub.callCount, 1 );
		assert.deepEqual( this.newEventStub.firstCall.args, [
			'product_metrics.web_base',
			'/analytics/product_metrics/web/base/2.2.0',
			[
				'performer_pageview_id',
				'mediawiki_database',
				'performer_is_bot',
				'performer_is_logged_in'
			],
			'Hello, World!',
			{
				action_source: 'the_source',
				action_context: 'the_context',
				experiment: expectedExperiment
			}
		] );

		assert.strictEqual( this.sendEventStub.callCount, 1 );
	}
);

QUnit.test( 'send() - handles undefined/null contextual_attributes', function ( assert ) {
	const experiment = this.newExperiment( {
		enrolled: 'hello_world',
		assigned: 'A',
		subject_id: 'awaiting',
		sampling_unit: 'edge-unique',
		phase_index: 0,
		stream_name: 'product_metrics.web_base',
		schema_id: '/analytics/product_metrics/web/base/2.2.0'
	} );

	experiment.send(
		'my-awesome-action',
		{
			foo: 'bar'
		},
		[
			'page_namespace_name',
			'performer_session_id'
		]
	);

	assert.strictEqual( this.newEventStub.callCount, 1 );
	assert.deepEqual( this.newEventStub.firstCall.args, [
		'product_metrics.web_base',
		'/analytics/product_metrics/web/base/2.2.0',
		[
			'page_namespace_name',
			'performer_session_id'
		],
		'my-awesome-action',
		{
			foo: 'bar',
			experiment: {
				enrolled: 'hello_world',
				assigned: 'A',
				subject_id: 'awaiting',
				sampling_unit: 'edge-unique',
				phase_index: 0,
				coordinator: 'default'
			}
		}
	] );

	assert.strictEqual( this.sendEventStub.callCount, 1 );
} );

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
		'/analytics/product_metrics/web/base/2.2.0',
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
				coordinator: 'default',
				phase_index: 0,
				other_assigned: {
					foo: 'bar'
				}
			}
		}
	] );
	assert.strictEqual( this.sendEventStub.callCount, 1 );
} );

QUnit.test( 'send() - overriding schema', function ( assert ) {
	this.everyoneExperiment.setSchema( '/my/awesome/schema/0.0.1' )
		.send( 'Hello, World!' );

	assert.strictEqual( this.newEventStub.callCount, 1 );
	assert.deepEqual( this.newEventStub.firstCall.args, [
		'product_metrics.web_base',
		'/my/awesome/schema/0.0.1',
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
				phase_index: 0,
				other_assigned: {
					foo: 'bar'
				},
				coordinator: 'default'
			}
		}
	] );
} );

QUnit.test.each(
	'send() - doesn\'t set other_assigned if it\'s empty',
	[
		undefined,
		null,
		{}
	],
	function ( assert, otherAssigned ) {
		const e = this.newExperiment( {
			enrolled: 'my-awesome-experiment',
			assigned: 'B',
			subject_id: '0x0ff1ce',
			sampling_unit: 'mw-user',
			other_assigned: otherAssigned
		} );

		e.send( 'Hello, World!' );

		assert.strictEqual( this.newEventStub.callCount, 1 );
		assert.strictEqual(
			this.newEventStub.firstCall.args[ 4 ].experiment.other_assigned,
			undefined
		);
	}
);

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
				phase_index: 0,
				other_assigned: {
					foo: 'bar'
				},
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
				phase_index: 1,
				other_assigned: {
					bar: 'baz'
				},
				coordinator: 'default'
			}
		]
	],
	function ( assert, [ propertyName, expectedExperiment ] ) {
		this[ propertyName ].sendExposure();

		assert.strictEqual( this.exposureLogTracker.makeKey.callCount, 1 );
		assert.strictEqual( this.exposureLogTracker.trySend.callCount, 1 );
		assert.strictEqual( this.newEventStub.callCount, 1 );
		assert.strictEqual( this.sendEventStub.callCount, 1 );

		assert.deepEqual(
			this.exposureLogTracker.makeKey.firstCall.args[ 0 ],
			{
				enrolled: expectedExperiment.enrolled,
				assigned: expectedExperiment.assigned,
				version: 'v1'
			}
		);

		assert.strictEqual(
			this.exposureLogTracker.trySend.firstCall.args[ 0 ],
			'key'
		);
		assert.strictEqual(
			typeof this.exposureLogTracker.trySend.firstCall.args[ 1 ],
			'function'
		);

		assert.deepEqual(
			this.newEventStub.firstCall.args,
			[
				'product_metrics.web_base',
				'/analytics/product_metrics/web/base/2.2.0',
				[
					'performer_pageview_id',
					'mediawiki_database',
					'performer_is_logged_in',
					'performer_is_temp',
					'performer_is_bot',
					'performer_edit_count'
				],
				'experiment_exposure',
				{
					experiment: expectedExperiment
				}
			]
		);
	}
);

QUnit.test( 'sendExposure() does not send when tracker suppresses exposure', function ( assert ) {
	this.exposureLogTracker.trySend = this.sandbox.stub();

	this.everyoneExperiment.sendExposure();

	assert.strictEqual( this.exposureLogTracker.makeKey.callCount, 1 );
	assert.strictEqual( this.exposureLogTracker.trySend.callCount, 1 );
	assert.strictEqual( this.newEventStub.callCount, 0 );
	assert.strictEqual( this.sendEventStub.callCount, 0 );
} );

QUnit.test( 'sendExposure() rethrows when sending exposure fails', function ( assert ) {
	this.sendEventStub.throws( new Error( 'boom' ) );

	assert.throws( () => {
		this.everyoneExperiment.sendExposure();
	}, /boom/ );

	assert.strictEqual( this.exposureLogTracker.makeKey.callCount, 1 );
	assert.strictEqual( this.exposureLogTracker.trySend.callCount, 1 );
} );

// ---

QUnit.module( 'ext.testKitchen/UnenrolledExperiment' );

QUnit.test( 'setSchema() - doesn\'t trigger an error', ( assert ) => {
	const e = new mw.testKitchen.UnenrolledExperiment();

	assert.strictEqual( e.setSchema( 'my_awesome_stream' ), e );
} );

// ---

QUnit.module( 'ext.testKitchen/OverriddenExperiment', QUnit.newMwEnvironment( {
	beforeEach: function () {
		const { OverriddenExperiment } = mw.testKitchen;

		// Stubs
		// =====

		this.expectedEvent = {
			$schema: '/analytics/product_metrics/web/base/2.2.0',
			dt: new Date().toISOString(),
			agent: {
				client_platform: 'mediawiki_js',
				client_platform_family: 'desktop_browser'
			}
		};

		const eventFactory = {
			newEvent() {
			},
			addContextualAttributes() {
			}
		};

		this.newEventStub = this.sandbox.stub( eventFactory, 'newEvent' )
			.returns( this.expectedEvent );
		this.addContextualAttributesStub = this.sandbox.stub( eventFactory, 'addContextualAttributes' )
			.callsFake( ( event ) => {
				event.agent = {
					client_platform: 'mediawiki_js',
					client_platform_family: 'desktop_browser'
				};
			} );

		const eventSender = {
			sendEvent() {
			}
		};

		this.sendEventStub = this.sandbox.stub( eventSender, 'sendEvent' );

		/**
		 * @param {mw.testKitchen.ExperimentConfig} experimentConfig
		 * @return {mw.testKitchen.OverriddenExperiment}
		 */
		this.newOverriddenExperiment = function ( experimentConfig ) {
			return new OverriddenExperiment(
				eventFactory,
				eventSender,
				'http://foo.bar/baz?qux=quux',
				experimentConfig
			);
		};

		this.overriddenExperiment = this.newOverriddenExperiment( {
			enrolled: 'my-overridden-experiment',
			assigned: 'control',
			subject_id: 'overridden',
			sampling_unit: 'overridden',
			phase_index: 0,
			stream_name: 'product_metrics.web_base',
			schema_id: '/analytics/product_metrics/web/base/2.2.0',
			contextual_attributes: [
				'performer_pageview_id',
				'mediawiki_database'
			],
			exposure_version: 'v1',
			other_assigned: {
				bar: 'baz'
			}
		} );
	}
} ) );

QUnit.test( 'send() - event is sent when running on beta cluster', function ( assert ) {
	mw.config.set( 'wgServer', 'https://en.wikipedia.beta.wmcloud.org' );

	const action = 'Hello, World!';
	const interactionData = {
		experiment: {
			foo: 'bar',
			baz: 'qux'
		}
	};
	const contextualAttributes = {
		agent: {
			client_platform: 'mediawiki_js',
			client_platform_family: 'desktop_browser'
		}
	};

	const logStub = this.sandbox.stub( console, 'log' );

	this.overriddenExperiment.send( action, interactionData, contextualAttributes );

	assert.strictEqual( logStub.callCount, 1 );
	assert.deepEqual( logStub.firstCall.args, [
		'my-overridden-experiment: The enrollment for this experiment has been overridden. The following event will not be sent:\n',
		action,
		JSON.stringify( interactionData, null, 2 ),
		JSON.stringify( contextualAttributes, null, 2 )
	] );

	// The event will be sent (it is running on beta cluster)
	assert.strictEqual( this.sendEventStub.callCount, 1 );
} );

QUnit.test( 'send() - event is not sent when running on production', function ( assert ) {
	mw.config.set( 'wgServer', '//en.wikipedia.org' );

	const logStub = this.sandbox.stub( console, 'log' );

	this.overriddenExperiment.send( 'some action' );

	assert.strictEqual( logStub.callCount, 1 );

	// The event will not be sent (it is running on production)
	assert.strictEqual( this.sendEventStub.callCount, 0 );
} );

QUnit.test( 'setSchema() - doesn\'t trigger an error', function ( assert ) {
	assert.strictEqual( this.overriddenExperiment.setSchema( 'my_awesome_stream' ), this.overriddenExperiment );
} );

QUnit.test( 'sendExposure()', function ( assert ) {
	assert.expect( 0 );

	this.sandbox.mock( console )
		.expects( 'log' )
		.once()
		.withExactArgs(
			'my-overridden-experiment: The enrollment for this experiment has been overridden. The following event will not be sent:\n',
			'experiment_exposure'
		);

	this.overriddenExperiment.sendExposure( 'experiment_exposure' );
} );
