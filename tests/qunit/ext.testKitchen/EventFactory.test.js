const { EventFactory } = mw.testKitchen;

QUnit.module( 'ext.testKitchen/EventFactory', QUnit.newMwEnvironment( {
	config: {
		wgServerName: 'my-awesome-server-name'
	},
	beforeEach: function () {
		this.contextualAttributes = {};

		const contextualAttributesFactory = {};
		contextualAttributesFactory.newContextualAttributes = () => this.contextualAttributes;

		const now = Date.now();

		this.clock = this.sandbox.useFakeTimers( {
			now
		} );

		this.expectedDt = new Date( now ).toISOString();

		// Note well that EventFactory#constructor() is package-private. Calling it outside Test
		// Kitchen is not supported.

		this.eventFactory = new EventFactory( contextualAttributesFactory );
	}
} ) );

QUnit.test( 'newEvent()', function ( assert ) {
	const expected = {
		action: 'my-awesome-action',
		foo: 'bar',
		$schema: '/my/awesome/schema/1.0.0',
		meta: {
			domain: 'my-awesome-server-name',
			stream: 'my-awesome-stream'
		},
		dt: this.expectedDt
	};

	assert.deepEqual(
		this.eventFactory.newEvent(
			'my-awesome-stream',
			'/my/awesome/schema/1.0.0',
			[],
			'my-awesome-action',
			{
				foo: 'bar'
			}
		),
		expected
	);
} );

QUnit.test( 'newEvent() - key Event Platform fields aren\'t overridden', function ( assert ) {
	const expected = {
		action: 'my-awesome-action',
		foo: 'bar',
		$schema: '/my/awesome/schema/1.0.0',
		meta: {
			domain: 'my-awesome-server-name',
			stream: 'my-awesome-stream'
		},
		dt: this.expectedDt
	};

	assert.deepEqual(
		this.eventFactory.newEvent(
			'my-awesome-stream',
			'/my/awesome/schema/1.0.0',
			[],
			'my-awesome-action',
			{
				foo: 'bar',
				meta: 'meta',
				dt: new Date().toISOString()
			}
		),
		expected
	);
} );

QUnit.test( 'newEvent() - action isn\'t overridden', function ( assert ) {
	const event = this.eventFactory.newEvent(
		'my-awesome-stream',
		'/my/awesome/schema/1.0.0',
		[],
		'my-awesome-action',
		{
			action: 'my-awesome-action-2'
		}
	);

	assert.strictEqual( event.action, 'my-awesome-action' );
} );

QUnit.test( 'newEvent() - mixes in available contextual attributes', function ( assert ) {
	this.contextualAttributes = {
		agent: {
			client_platform: 'my-awesome-client-platform',
			client_platform_family: 'my-awesome-desktop'
		},
		page: {
			id: 1234567890
		},
		performer: {
			name: 'my-awesome-performer-name'
		}
	};

	const event = this.eventFactory.newEvent(
		'my-awesome-stream',
		'/my/awesome/schema/1.0.0',
		[
			'page_id',
			'performer_name',
			'performer_pageview_id'
		],
		'my-awesome-action',
		{}
	);

	assert.deepEqual(
		event.agent,
		{
			client_platform: 'my-awesome-client-platform',
			client_platform_family: 'my-awesome-desktop'
		},
		'agent_client_platform and agent_client_platform_family are added by default'
	);

	assert.deepEqual(
		event.page,
		{
			id: 1234567890
		}
	);

	assert.deepEqual(
		event.performer,
		{
			name: 'my-awesome-performer-name'
		}
	);
} );
