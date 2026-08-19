function newEvent() {
	return {
		meta: {
			stream: 'product_metrics.web_base'
		},
		$schema: '/analytics/product_metrics/web/base/2.2.0',
		dt: new Date().toISOString()
	};
}

QUnit.module( 'ext.testKitchen/internalEventSender', QUnit.newMwEnvironment( {
	beforeEach() {
		this.sandbox.stub( navigator, 'sendBeacon' );

		this.internalEventSender = mw.testKitchen.internalEventSender;
	}
} ) );

QUnit.test( 'it sends events immediately', function ( assert ) {
	const event = newEvent();

	this.internalEventSender.sendEvent( event, 'http://foo' );

	assert.strictEqual( navigator.sendBeacon.callCount, 1 );
	assert.deepEqual( navigator.sendBeacon.firstCall.args, [
		'http://foo',
		JSON.stringify( [ event ] )
	] );
} );

QUnit.test( 'it sends one beacon request per event', function ( assert ) {
	const event1 = newEvent();
	const event2 = newEvent();
	const event3 = newEvent();

	this.internalEventSender.sendEvent( event1, 'http://foo' );
	this.internalEventSender.sendEvent( event2, 'http://foo' );
	this.internalEventSender.sendEvent( event3, 'http://bar' );

	assert.strictEqual( navigator.sendBeacon.callCount, 3 );
	assert.deepEqual( navigator.sendBeacon.firstCall.args, [
		'http://foo',
		JSON.stringify( [ event1 ] )
	] );
	assert.deepEqual( navigator.sendBeacon.secondCall.args, [
		'http://foo',
		JSON.stringify( [ event2 ] )
	] );
	assert.deepEqual( navigator.sendBeacon.thirdCall.args, [
		'http://bar',
		JSON.stringify( [ event3 ] )
	] );
} );

QUnit.test( 'it ignores errors thrown by Navigator#sendBeacon()', function ( assert ) {
	navigator.sendBeacon.throws( new Error( 'Blocked' ) );

	this.internalEventSender.sendEvent( newEvent(), 'http://foo' );

	assert.true( navigator.sendBeacon.calledOnce );
} );
