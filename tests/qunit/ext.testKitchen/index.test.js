QUnit.module( 'ext.testKitchen', QUnit.newMwEnvironment() );

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
