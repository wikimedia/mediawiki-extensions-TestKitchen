QUnit.module( 'ext.testKitchen/enrollmentConfig/processRawValue()', QUnit.newMwEnvironment( {
	beforeEach: function () {
		this.enrollmentConfig = require( 'ext.testKitchen/enrollmentConfig.js' );
	},
	afterEach: function () {
		this.enrollmentConfig.reset();
	}
} ) );

QUnit.test.each(
	'processRawValue(): it processes a valid raw value',
	[
		[ '', {} ],
		[
			'foo:bar;',
			{
				foo: 'bar'
			}
		],
		[
			'foo:bar;baz:qux;',
			{
				foo: 'bar',
				baz: 'qux'
			}
		]
	],
	function ( assert, [ rawValue, expected ] ) {
		assert.deepEqual(
			this.enrollmentConfig.processRawValue( rawValue, ':' ),
			expected
		);
	}
);

QUnit.test.each(
	'it throws when the raw value is invalid',
	[
		[
			'foo:',
			'Unexpected end of raw value'
		],
		[
			'foo:bar',
			'Unexpected end of raw value'
		],
		[
			'foo;',
			'Unexpected \';\' while processing experiment name'
		],
		[
			'foo:bar:baz;',
			'Unexpected \':\' while processing group name'
		]
	],
	function ( assert, [ rawValue, expectedErrorMessage ] ) {
		const processRawValue = this.processRawValue;

		assert.throws(
			() => processRawValue( rawValue, ':' ),
			expectedErrorMessage
		);
	}
);

QUnit.module(
	'ext.testKitchen/enrollmentConfig/getOverriddenEnrollments()',
	QUnit.newMwEnvironment( {
		beforeEach: function () {
			this.originalMPOCookie = mw.cookie.get( 'mpo' );
			mw.cookie.set( 'mpo', null );

			this.enrollmentConfig = require( 'ext.testKitchen/enrollmentConfig.js' );
		},
		afterEach: function () {
			mw.cookie.set( 'mpo', this.originalMPOCookie );

			this.enrollmentConfig.reset();
		}
	} )
);

QUnit.test( 'it should process the cookie value', function ( assert ) {
	// Note: This doesn't end with a ';', which processRawValue is expecting
	mw.cookie.set( 'mpo', 'foo:bar;baz:qux' );

	assert.deepEqual(
		this.enrollmentConfig.getOverriddenEnrollments(),
		{
			foo: 'bar',
			baz: 'qux'
		}
	);
} );

QUnit.test( 'it should handle an empty cookie', function ( assert ) {
	assert.deepEqual(
		this.enrollmentConfig.getOverriddenEnrollments(),
		{}
	);
} );

QUnit.test( 'it should memoize the result', function ( assert ) {
	mw.cookie.set( 'mpo', 'foo:bar;baz:qux' );

	assert.strictEqual(
		this.enrollmentConfig.getOverriddenEnrollments(),
		this.enrollmentConfig.getOverriddenEnrollments()
	);
} );

QUnit.test( 'it should log an error if processing the cookie value fails', function ( assert ) {
	mw.cookie.set( 'mpo', 'garbage;' );

	this.sandbox.stub( mw.errorLogger, 'logError' );

	assert.deepEqual(
		this.enrollmentConfig.getOverriddenEnrollments(),
		{}
	);

	assert.strictEqual( mw.errorLogger.logError.callCount, 1 );

	const args = mw.errorLogger.logError.args[ 0 ];

	assert.true( args[ 0 ] instanceof Error, 'It passes through the error' );
	assert.strictEqual( args[ 1 ], 'error.test_kitchen.process_raw_override_value.cookie' );
} );

QUnit.module(
	'ext.testKitchen/enrollmentConfig/getHeaderEnrollments()',
	QUnit.newMwEnvironment( {
		beforeEach: function () {
			this.enrollmentConfig = require( 'ext.testKitchen/enrollmentConfig.js' );
		},
		afterEach: function () {
			this.enrollmentConfig.reset();
		}
	} )
);

QUnit.test( 'it should process the cookie value', function ( assert ) {
	const done = assert.async();

	this.enrollmentConfig.setRawHeaderPromise( Promise.resolve( 'foo=bar;baz=qux;' ) );

	this.enrollmentConfig.getHeaderEnrollments().then( ( enrollments ) => {
		assert.deepEqual(
			enrollments,
			{
				foo: 'bar',
				baz: 'qux'
			}
		);

		done();
	} );
} );

QUnit.test( 'it should log an error if processing the header value fails', function ( assert ) {
	const done = assert.async();

	this.enrollmentConfig.setRawHeaderPromise( Promise.resolve( 'foo=bar=baz;' ) );

	this.sandbox.stub( mw.errorLogger, 'logError' );

	this.enrollmentConfig.getHeaderEnrollments().then( ( enrollments ) => {
		assert.deepEqual( enrollments, {} );

		assert.strictEqual( mw.errorLogger.logError.callCount, 1 );

		const args = mw.errorLogger.logError.args[ 0 ];

		assert.true( args[ 0 ] instanceof Error, 'It passes through the error' );
		assert.strictEqual( args[ 1 ], 'error.test_kitchen.process_header' );

		done();
	} );
} );

QUnit.module( 'ext.testKitchen/enrollmentConfig/getAsync', QUnit.newMwEnvironment( {
	config: {
		wgTestKitchenUserExperiments: {
			enrolled: [
				'fruit',
				'dessert',
				'supper'
			],
			assigned: {
				fruit: 'tropical',
				dessert: 'ice-cream',
				supper: 'fish-pie'
			},
			subject_ids: {
				fruit: '2def9a8f9d8c4f0296268a1c3d2e7fba90298e704070d946536166c832d05652',
				dessert: '788a1970cc9b665222de25cc1a79da7ee1fcaf69b674caba188233ad995ba3d4',
				supper: 'awaiting'
			},
			overrides: []
		}
	},
	beforeEach: function () {
		this.enrollmentConfig = require( 'ext.testKitchen/enrollmentConfig.js' );
	},
	afterEach: function () {
		this.enrollmentConfig.reset();
	}
} ) );

QUnit.test( 'it should return null by default', function ( assert ) {
	const done = assert.async();

	this.enrollmentConfig.getAsync( 'foo' ).then( ( e ) => {
		assert.strictEqual( e, null );

		done();
	} );
} );

QUnit.test( 'it should return an overridden enrollment', function ( assert ) {
	this.enrollmentConfig.setOverriddenEnrollmentConfigs( {
		foo: 'bar'
	} );

	const done = assert.async();

	this.enrollmentConfig.getAsync( 'foo' ).then( ( ec ) => {
		assert.deepEqual(
			ec,
			{
				enrolled: 'foo',
				assigned: 'bar',
				subject_id: 'overridden',
				is_override: true,
				other_assigned: {
					fruit: 'tropical',
					dessert: 'ice-cream',
					supper: 'fish-pie'
				}
			}
		);

		done();
	} );
} );

QUnit.test( 'it should not include the name of the experiment in other_assigned', function ( assert ) {
	this.enrollmentConfig.setOverriddenEnrollmentConfigs( {
		fruit: 'apple'
	} );

	const done = assert.async();

	this.enrollmentConfig.getAsync( 'fruit' ).then( ( ec ) => {
		assert.deepEqual(
			ec,
			{
				enrolled: 'fruit',
				assigned: 'apple',
				subject_id: 'overridden',
				is_override: true,
				other_assigned: {
					dessert: 'ice-cream',
					supper: 'fish-pie'
				}
			}
		);

		done();
	} );
} );

QUnit.test( 'it should return an enrollment from the external-facing header', function ( assert ) {
	this.enrollmentConfig.setRawHeaderPromise( Promise.resolve( 'foo=bar;' ) );

	const done = assert.async();

	this.enrollmentConfig.getAsync( 'foo' ).then( ( ec ) => {
		assert.deepEqual(
			ec,
			{
				enrolled: 'foo',
				assigned: 'bar',
				subject_id: 'awaiting',
				is_override: false,
				other_assigned: {
					fruit: 'tropical',
					dessert: 'ice-cream',
					supper: 'fish-pie'
				}
			}
		);

		done();
	} );
} );

QUnit.test( 'it should return an enrollment from the server', function ( assert ) {
	const done = assert.async();

	this.enrollmentConfig.getAsync( 'dessert' ).then( ( ec ) => {
		assert.deepEqual(
			ec,
			{
				enrolled: 'dessert',
				assigned: 'ice-cream',
				subject_id: '788a1970cc9b665222de25cc1a79da7ee1fcaf69b674caba188233ad995ba3d4',
				is_override: false,
				other_assigned: {
					fruit: 'tropical',
					supper: 'fish-pie'
				}
			}
		);

		done();
	} );
} );

QUnit.test( 'it should prioritize the external-facing enrollment', function ( assert ) {
	this.enrollmentConfig.setRawHeaderPromise( Promise.resolve( 'dessert=tiramisu;' ) );

	const done = assert.async();

	this.enrollmentConfig.getAsync( 'dessert' ).then( ( ec ) => {
		assert.deepEqual(
			ec,
			{
				enrolled: 'dessert',
				assigned: 'tiramisu',
				subject_id: 'awaiting',
				is_override: false,
				other_assigned: {
					fruit: 'tropical',
					supper: 'fish-pie'
				}
			}
		);

		done();
	} );
} );

QUnit.module( 'ext.testKitchen/enrollmentConfig/getMatchingAsync', QUnit.newMwEnvironment( {
	config: {
		wgTestKitchenUserExperiments: {
			enrolled: [
				'fruit',
				'dessert',
				'supper'
			],
			assigned: {
				fruit: 'tropical',
				dessert: 'ice-cream',
				supper: 'fish-pie'
			},
			subject_ids: {
				fruit: '2def9a8f9d8c4f0296268a1c3d2e7fba90298e704070d946536166c832d05652',
				dessert: '788a1970cc9b665222de25cc1a79da7ee1fcaf69b674caba188233ad995ba3d4',
				supper: 'awaiting'
			},
			overrides: []
		}
	},
	beforeEach: function () {
		this.enrollmentConfig = require( 'ext.testKitchen/enrollmentConfig.js' );
	},
	afterEach: function () {
		this.enrollmentConfig.reset();
	}
} ) );

QUnit.test( 'it handles matches', function ( assert ) {
	this.enrollmentConfig.setOverriddenEnrollmentConfigs( {
		'fruit-2': 'apple'
	} );
	this.enrollmentConfig.setRawHeaderPromise( Promise.resolve( 'fruit-3=orange;' ) );

	const done = assert.async();

	this.enrollmentConfig.getMatchingAsync( 'fruit' ).then( ( ecs ) => {
		assert.strictEqual( ecs.length, 3 );
		assert.deepEqual(
			ecs,
			[
				{
					enrolled: 'fruit',
					assigned: 'tropical',
					subject_id: '2def9a8f9d8c4f0296268a1c3d2e7fba90298e704070d946536166c832d05652',
					is_override: false,
					other_assigned: {
						dessert: 'ice-cream',
						'fruit-2': 'apple',
						'fruit-3': 'orange',
						supper: 'fish-pie'
					}
				},
				{
					enrolled: 'fruit-3',
					assigned: 'orange',
					subject_id: 'awaiting',
					is_override: false,
					other_assigned: {
						dessert: 'ice-cream',
						fruit: 'tropical',
						'fruit-2': 'apple',
						supper: 'fish-pie'
					}
				},
				{
					enrolled: 'fruit-2',
					assigned: 'apple',
					subject_id: 'overridden',
					is_override: true,
					other_assigned: {
						dessert: 'ice-cream',
						fruit: 'tropical',
						'fruit-3': 'orange',
						supper: 'fish-pie'
					}
				}
			]
		);

		done();
	} );
} );

QUnit.test( 'it handles an exact match', function ( assert ) {
	const done = assert.async();

	this.enrollmentConfig.getMatchingAsync( 'dessert' ).then( ( ecs ) => {
		assert.strictEqual( ecs.length, 1 );
		assert.deepEqual(
			ecs,
			[
				{
					enrolled: 'dessert',
					assigned: 'ice-cream',
					subject_id: '788a1970cc9b665222de25cc1a79da7ee1fcaf69b674caba188233ad995ba3d4',
					is_override: false,
					other_assigned: {
						fruit: 'tropical',
						supper: 'fish-pie'
					}
				}
			]
		);

		done();
	} );
} );

QUnit.test( 'it handles an unknown experiment', function ( assert ) {
	const done = assert.async();

	this.enrollmentConfig.getMatchingAsync( 'foo' ).then( ( ecs ) => {
		assert.strictEqual( ecs.length, 0 );

		done();
	} );
} );
