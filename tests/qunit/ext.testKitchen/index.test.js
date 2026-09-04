QUnit.module( 'ext.testKitchen', QUnit.newMwEnvironment( {
	beforeEach: function () {
		this.originalMPOCookie = mw.cookie.get( 'mpo' );

		mw.cookie.set( 'mpo', null );
	},
	afterEach: function () {
		mw.cookie.set( 'mpo', this.originalMPOCookie );
	}
} ) );

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
