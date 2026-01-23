QUnit.module( 'ext.testKitchen', QUnit.newMwEnvironment( {
	beforeEach: function () {
		this.originalMPOCookie = mw.cookie.get( 'mpo' );
		this.originalMPUserExperiments = mw.config.get( 'wgTestKitchenUserExperiments' );

		mw.cookie.set( 'mpo', null );
	},
	afterEach: function () {
		mw.config.set( 'wgTestKitchenUserExperiments', this.originalMPUserExperiments );
		mw.cookie.set( 'mpo', this.originalMPOCookie );
	}
} ) );

QUnit.test( 'getExperiment() - handles invalid config', ( assert ) => {
	// Test cases for when $wgTestKitchenEnableExperiments is falsy
	// (wgTestKitchenUserExperiments will be undefined).

	const { UnenrolledExperiment } = mw.testKitchen;

	const e = mw.testKitchen.getExperiment( 'an_experiment_name' );

	assert.true( e instanceof UnenrolledExperiment );
	assert.strictEqual( e.getAssignedGroup(), null );
} );

QUnit.test.each(
	'getExperiment()',
	{
		'handles unknown experiment': [ 'elevenses', null ],
		'handles active experiment with no enrollment': [ 'lunch', null ],
		'handles active experiment with enrollment': [ 'fruit', 'tropical' ]
	},
	( assert, [ experimentName, expectedAssignedGroup ] ) => {
		mw.config.set( 'wgTestKitchenUserExperiments', {
			enrolled: [
				'fruit',
				'dessert'
			],
			assigned: {
				fruit: 'tropical',
				dessert: 'ice-cream'
			},
			subject_ids: {
				fruit: '2def9a8f9d8c4f0296268a1c3d2e7fba90298e704070d946536166c832d05652',
				dessert: '788a1970cc9b665222de25cc1a79da7ee1fcaf69b674caba188233ad995ba3d4'
			},
			sampling_units: {
				fruit: 'mw-user',
				dessert: 'mw-user'
			},
			active_experiments: [
				'fruit',
				'dessert',
				'lunch'
			],
			overrides: []
		} );

		assert.strictEqual(
			mw.testKitchen.getExperiment( experimentName ).getAssignedGroup(),
			expectedAssignedGroup
		);
	}
);

QUnit.test( 'getExperiment() - handles overridden experiment', ( assert ) => {
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

	const { OverriddenExperiment } = mw.testKitchen;

	const e = mw.testKitchen.getExperiment( 'fruit' );

	assert.true( e instanceof OverriddenExperiment );
	assert.strictEqual( e.getAssignedGroup(), 'gooseberry' );
} );

// ---

// Test cases for the overriding feature
QUnit.test( 'overrideExperimentGroup() - single call', ( assert ) => {
	mw.testKitchen.overrideExperimentGroup( 'foo', 'bar' );

	assert.strictEqual( mw.cookie.get( 'mpo' ), 'foo:bar' );
} );

QUnit.test( 'overrideExperimentGroup() - multiple calls', ( assert ) => {
	mw.testKitchen.overrideExperimentGroup( 'foo', 'bar' );
	mw.testKitchen.overrideExperimentGroup( 'qux', 'quux' );

	assert.strictEqual( mw.cookie.get( 'mpo' ), 'foo:bar;qux:quux' );
} );

QUnit.test( 'overrideExperimentGroup() - multiple identical calls', ( assert ) => {
	mw.testKitchen.overrideExperimentGroup( 'foo', 'bar' );
	mw.testKitchen.overrideExperimentGroup( 'qux', 'quux' );
	mw.testKitchen.overrideExperimentGroup( 'foo', 'bar' );
	mw.testKitchen.overrideExperimentGroup( 'qux', 'quux' );

	assert.strictEqual( mw.cookie.get( 'mpo' ), 'foo:bar;qux:quux' );
} );

QUnit.test( 'overrideExperimentGroup() - multiple calls with different $groupName', ( assert ) => {
	mw.testKitchen.overrideExperimentGroup( 'foo', 'bar' );
	mw.testKitchen.overrideExperimentGroup( 'foo', 'baz' );

	assert.strictEqual( mw.cookie.get( 'mpo' ), 'foo:baz' );
} );

QUnit.test( 'overrideExperimentGroup() - multiple calls with $groupName with hyphens', ( assert ) => {
	mw.testKitchen.overrideExperimentGroup( 'foo', 'bar-baz' );
	mw.testKitchen.overrideExperimentGroup( 'foo', 'baz' );

	assert.strictEqual( mw.cookie.get( 'mpo' ), 'foo:baz' );
} );

QUnit.test( 'clearExperimentGroup() - single override', ( assert ) => {
	mw.testKitchen.overrideExperimentGroup( 'foo', 'bar' );
	mw.testKitchen.clearExperimentOverride( 'foo' );

	assert.strictEqual( mw.cookie.get( 'mpo' ), null );
} );

QUnit.test( 'clearExperimentGroup() - multiple overrides', ( assert ) => {
	mw.testKitchen.overrideExperimentGroup( 'foo', 'bar' );
	mw.testKitchen.overrideExperimentGroup( 'baz', 'qux' );

	mw.testKitchen.clearExperimentOverride( 'baz' );

	assert.strictEqual( mw.cookie.get( 'mpo' ), 'foo:bar' );
} );

QUnit.test( 'clearExperimentGroup() - multiple overrides with experiment in the middle', ( assert ) => {
	mw.testKitchen.overrideExperimentGroup( 'foo', 'bar' );
	mw.testKitchen.overrideExperimentGroup( 'baz', 'qux' );
	mw.testKitchen.overrideExperimentGroup( 'qux', 'quux' );

	mw.testKitchen.clearExperimentOverride( 'baz' );

	assert.strictEqual( mw.cookie.get( 'mpo' ), 'foo:bar;qux:quux' );
} );

QUnit.test( 'clearExperimentGroup() - multiple overrides with $groupName with hyphens', ( assert ) => {
	mw.testKitchen.overrideExperimentGroup( 'foo-bar', 'baz-qux' );
	mw.testKitchen.overrideExperimentGroup( 'qux-quux', 'corge-grault' );

	mw.testKitchen.clearExperimentOverride( 'foo-bar' );

	assert.strictEqual( mw.cookie.get( 'mpo' ), 'qux-quux:corge-grault' );
} );

// ---

QUnit.test( 'getAssignments() - disallows modification of wgTestKitchenUserExperiments', ( assert ) => {
	const assigned = {
		fruit: 'tropical'
	};

	mw.config.set( 'wgTestKitchenUserExperiments', {
		assigned
	} );

	assert.deepEqual( mw.testKitchen.getAssignments(), assigned );

	const result = mw.testKitchen.getAssignments();
	result.foo = 'bar';
	result.bar = 'baz';

	assert.deepEqual(
		mw.testKitchen.getAssignments(),
		assigned,
		'The result of mw.testKitchen.getAssignments() is unchanged'
	);
	assert.deepEqual(
		mw.config.get( 'wgTestKitchenUserExperiments' ).assigned,
		assigned,
		'wgTestKitchenUserExperiments is unchanged'
	);
} );
