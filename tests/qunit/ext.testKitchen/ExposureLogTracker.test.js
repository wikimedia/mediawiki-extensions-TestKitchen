QUnit.module( 'ext.testKitchen/ExposureLogTracker', QUnit.newMwEnvironment( {
	beforeEach: function () {
		const ExposureLogTracker = mw.testKitchen.ExposureLogTracker;
		this.tracker = new ExposureLogTracker();
		this.key = 'tk_exposure.my-experiment:control:v1';
		this.fixedNowMs = 1700000000000; // 1700000000 seconds
		this.sessionGetStub = this.sandbox.stub( mw.storage.session, 'get' );
		this.sessionSetStub = this.sandbox.stub( mw.storage.session, 'set' );
		this.sessionRemoveStub = this.sandbox.stub( mw.storage.session, 'remove' );
		this.clock = this.sandbox.useFakeTimers( this.fixedNowMs );
	}
} ) );
QUnit.test( 'makeKey builds a stable key from enrolled, assigned, and version', function ( assert ) {
	assert.strictEqual(
		this.tracker.makeKey( {
			enrolled: 'my-experiment',
			assigned: 'control',
			version: 'v1'
		} ),
		'tk_exposure.my-experiment:control:v1'
	);
} );
QUnit.test( 'makeKey falls back to v0 when version is missing', function ( assert ) {
	assert.strictEqual(
		this.tracker.makeKey( {
			enrolled: 'my-experiment',
			assigned: 'control'
		} ),
		'tk_exposure.my-experiment:control:v0'
	);
} );
QUnit.test( 'getValidSessionData returns null when no session entry exists', function ( assert ) {
	this.sessionGetStub.withArgs( this.key ).returns( null );
	assert.deepEqual(
		this.tracker.getValidSessionData( this.key, 0 ),
		{
			expires_at: 1699996400000,
			count: 0
		}
	);
	assert.strictEqual( this.sessionGetStub.callCount, 1 );
	assert.strictEqual( this.sessionRemoveStub.callCount, 0 );
} );
QUnit.test( 'getValidSessionData removes and returns null when older than global reset epoch', function ( assert ) {
	this.sessionGetStub.withArgs( this.key ).returns( JSON.stringify( {
		g: 'control',
		count: 2,
		ts: 1700000000
	} ) );
	assert.deepEqual(
		this.tracker.getValidSessionData( this.key, 1700000001 ),
		{
			expires_at: 1699996400000,
			count: 0
		}
	);
	assert.strictEqual( this.sessionGetStub.callCount, 1 );
	assert.strictEqual( this.sessionRemoveStub.callCount, 1 );
	assert.deepEqual( this.sessionRemoveStub.firstCall.args, [ this.key ] );
} );
QUnit.test( 'getValidSessionData returns parsed session data when valid', function ( assert ) {
	const entry = {
		g: 'control',
		count: 2,
		ts: 1700000000
	};
	this.sessionGetStub.withArgs( this.key ).returns( JSON.stringify( entry ) );
	assert.deepEqual(
		this.tracker.getValidSessionData( this.key, 0 ),
		entry
	);
	assert.strictEqual( this.sessionGetStub.callCount, 1 );
	assert.strictEqual( this.sessionRemoveStub.callCount, 0 );
} );
QUnit.test( 'getValidSessionData returns null on malformed JSON', function ( assert ) {
	this.sessionGetStub.withArgs( this.key ).returns( '{bad json' );
	assert.deepEqual(
		this.tracker.getValidSessionData( this.key, 0 ),
		{
			expires_at: 1699996400000,
			count: 0
		}
	);
	assert.strictEqual( this.sessionGetStub.callCount, 1 );
	assert.strictEqual( this.sessionRemoveStub.callCount, 0 );
} );
QUnit.test( 'addLog stores session entry with incremented count and short TTL', function ( assert ) {
	this.tracker.addLog( this.key, { count: 0 } );
	assert.true( this.tracker.exposuresThisPage.has( this.key ) );
	assert.strictEqual( this.sessionSetStub.callCount, 1 );
	assert.deepEqual( this.sessionSetStub.firstCall.args, [
		this.key,
		JSON.stringify( {
			expires_at: 1700000300000,
			count: 1
		} )
	] );
} );
QUnit.test( 'addLog stores session entry with long TTL after threshold', function ( assert ) {
	this.tracker.addLog( this.key, { count: 10 } );
	assert.strictEqual( this.sessionSetStub.callCount, 1 );
	assert.deepEqual( this.sessionSetStub.firstCall.args, [
		this.key,
		JSON.stringify( {
			expires_at: 1700086400000,
			count: 11
		} )
	] );
} );
QUnit.test( 'addLog uses short ttl at or below threshold', function ( assert ) {
	this.tracker.addLog( this.key, { count: 9 } );
	assert.true( this.tracker.exposuresThisPage.has( this.key ) );
	assert.strictEqual( this.sessionSetStub.callCount, 1 );
	assert.deepEqual( this.sessionSetStub.firstCall.args, [
		this.key,
		JSON.stringify( {
			expires_at: 1700000300000,
			count: 10
		} )
	] );
} );
QUnit.test( 'addLog uses long ttl above threshold', function ( assert ) {
	this.tracker.addLog( this.key, { count: 10 } );
	assert.true( this.tracker.exposuresThisPage.has( this.key ) );
	assert.strictEqual( this.sessionSetStub.callCount, 1 );
	assert.deepEqual( this.sessionSetStub.firstCall.args, [
		this.key,
		JSON.stringify( {
			expires_at: 1700086400000,
			count: 11
		} )
	] );
} );
QUnit.test( 'trySend calls sendFn and records exposure when not already logged', function ( assert ) {
	const sendFn = this.sandbox.stub();
	this.tracker.trySend( this.key, sendFn );
	assert.strictEqual( sendFn.callCount, 1 );
	assert.true( this.tracker.exposuresThisPage.has( this.key ) );
	assert.strictEqual( this.sessionSetStub.callCount, 1 );
	assert.deepEqual( this.sessionSetStub.firstCall.args, [
		this.key,
		JSON.stringify( {
			expires_at: 1700000300000,
			count: 1
		} )
	] );
} );
QUnit.test( 'trySend does not call sendFn when key already exists in page memory', function ( assert ) {
	const sendFn = this.sandbox.stub();
	this.tracker.exposuresThisPage.add( this.key );
	this.tracker.trySend( this.key, sendFn );
	assert.strictEqual( sendFn.callCount, 0 );
	assert.strictEqual( this.sessionSetStub.callCount, 0 );
} );
QUnit.test( 'trySend marks page memory and rethrows when sendFn throws', function ( assert ) {
	const sendFn = this.sandbox.stub().throws( new Error( 'boom' ) );
	assert.throws( () => {
		this.tracker.trySend( this.key, sendFn );
	}, /boom/ );
	assert.true( this.tracker.exposuresThisPage.has( this.key ) );
	assert.strictEqual( this.sessionSetStub.callCount, 0 );
} );
QUnit.test( 'trySend does not call sendFn when valid session data exists', function ( assert ) {
	this.sessionGetStub.withArgs( this.key ).returns( JSON.stringify( {
		expires_at: 1700000300000,
		count: 4
	} ) );
	const sendFn = this.sandbox.stub();
	this.tracker.trySend( this.key, sendFn );
	assert.strictEqual( sendFn.callCount, 0 );
	assert.true( this.tracker.exposuresThisPage.has( this.key ) );
	assert.strictEqual( this.sessionSetStub.callCount, 0 );
} );
