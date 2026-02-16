function newEvent() {
	return {
		meta: {
			stream: 'product_metrics.web_base'
		},
		$schema: '/analytics/product_metrics/web/base/2.0.0',
		dt: new Date().toISOString()
	};
}

QUnit.module( 'ext.testKitchen/eventSender', QUnit.newMwEnvironment( {
	beforeEach() {
		this.sandbox.stub( navigator, 'sendBeacon' );

		this.clock = this.sandbox.useFakeTimers();

		this.eventSender = mw.testKitchen.eventSender;
	},
	afterEach() {
		this.eventSender.reset();
	}
} ) );

QUnit.test( 'it queues events', function ( assert ) {
	this.eventSender.sendEvent( newEvent(), 'http://foo' );
	this.eventSender.sendEvent( newEvent(), 'http://foo' );

	assert.false( navigator.sendBeacon.called );
} );

QUnit.test( 'it sends queued events after a 5 second delay', function ( assert ) {
	const event1 = newEvent();
	const event2 = newEvent();

	this.eventSender.sendEvent( event1, 'http://foo' );
	this.eventSender.sendEvent( event2, 'http://foo' );

	this.clock.tick( 5000 );

	assert.strictEqual( navigator.sendBeacon.callCount, 1 );
	assert.deepEqual( navigator.sendBeacon.firstCall.args, [
		'http://foo',
		JSON.stringify( [ event1, event2 ] )
	] );
} );

QUnit.test( 'it sends queued events for different URLs in batches', function ( assert ) {
	const event1 = newEvent();
	const event2 = newEvent();
	const event3 = newEvent();
	const event4 = newEvent();
	const event5 = newEvent();

	this.eventSender.sendEvent( event1, 'http://foo' );
	this.eventSender.sendEvent( event2, 'http://foo' );

	this.eventSender.sendEvent( event3, 'http://bar' );
	this.eventSender.sendEvent( event4, 'http://bar' );
	this.eventSender.sendEvent( event5, 'http://bar' );

	this.clock.tick( 5000 );

	assert.strictEqual( navigator.sendBeacon.callCount, 2 );
	assert.deepEqual( navigator.sendBeacon.firstCall.args, [
		'http://foo',
		JSON.stringify( [ event1, event2 ] )
	] );
	assert.deepEqual( navigator.sendBeacon.secondCall.args, [
		'http://bar',
		JSON.stringify( [ event3, event4, event5 ] )
	] );
} );

QUnit.test( 'it resets the queue after events are sent', function ( assert ) {
	const event = newEvent();

	this.eventSender.sendEvent( event, 'http://foo' );

	this.clock.tick( 5000 );
	this.clock.tick( 5000 );

	assert.strictEqual( navigator.sendBeacon.callCount, 1 );
} );

QUnit.test( 'it sends queued events when the page begins unloading', function ( assert ) {
	const event = newEvent();

	this.eventSender.sendEvent( event, 'http://foo' );

	// Pretend the page has begun unloading
	this.eventSender.onPageHide();

	assert.strictEqual( navigator.sendBeacon.callCount, 1 );
	assert.deepEqual( navigator.sendBeacon.firstCall.args, [
		'http://foo',
		JSON.stringify( [ event ] )
	] );

	this.clock.tick( 5000 );

	assert.strictEqual(
		navigator.sendBeacon.callCount,
		1,
		'The queue was reset after the events were sent'
	);
} );

QUnit.test( 'it sends events immediately after the page begins unloading', function ( assert ) {
	const event = newEvent();

	// Pretend the page has begun unloading
	this.eventSender.onPageHide();

	this.eventSender.sendEvent( event, 'http://foo' );

	assert.strictEqual( navigator.sendBeacon.callCount, 1 );
	assert.deepEqual( navigator.sendBeacon.firstCall.args, [
		'http://foo',
		JSON.stringify( [ event ] )
	] );

	this.eventSender.sendEvent( event, 'http://bar' );

	assert.strictEqual( navigator.sendBeacon.callCount, 2 );
	assert.deepEqual( navigator.sendBeacon.secondCall.args, [
		'http://bar',
		JSON.stringify( [ event ] )
	] );
} );

QUnit.test( 'it queues events after the page begins loading', function ( assert ) {
	const event = newEvent();

	// Pretend the page has begun unloading
	this.eventSender.onPageHide();

	// Pretend the page has begun loading
	this.eventSender.onPageShow();

	this.eventSender.sendEvent( event, 'http://foo' );

	assert.false( navigator.sendBeacon.called );

	this.clock.tick( 5000 );

	assert.strictEqual( navigator.sendBeacon.callCount, 1 );
	assert.deepEqual( navigator.sendBeacon.firstCall.args, [
		'http://foo',
		JSON.stringify( [ event ] )
	] );
} );

QUnit.test( 'it sends queued events when the page is hidden', function ( assert ) {
	const event = newEvent();

	this.eventSender.sendEvent( event, 'http://foo' );

	// Pretend the page is hidden
	this.eventSender.onVisibilityChange( true );

	assert.strictEqual( navigator.sendBeacon.callCount, 1 );
	assert.deepEqual( navigator.sendBeacon.firstCall.args, [
		'http://foo',
		JSON.stringify( [ event ] )
	] );
} );

QUnit.test( 'it queues events when the page is hidden', function ( assert ) {
	const event = newEvent();

	// Pretend the page is hidden
	this.eventSender.onVisibilityChange( true );

	this.eventSender.sendEvent( event, 'http://foo' );

	assert.false( navigator.sendBeacon.called );

	this.clock.tick( 5000 );

	assert.strictEqual( navigator.sendBeacon.callCount, 1 );
	assert.deepEqual( navigator.sendBeacon.firstCall.args, [
		'http://foo',
		JSON.stringify( [ event ] )
	] );
} );

QUnit.test( 'it handles the page unloading and then being hidden', function ( assert ) {
	const event1 = newEvent();
	const event2 = newEvent();

	this.eventSender.sendEvent( event1, 'http://foo' );

	// Present the page is unloading
	this.eventSender.onPageHide();

	assert.strictEqual( navigator.sendBeacon.callCount, 1 );
	assert.deepEqual( navigator.sendBeacon.firstCall.args, [
		'http://foo',
		JSON.stringify( [ event1 ] )
	] );

	// Pretend the page is hidden
	this.eventSender.onVisibilityChange( true );

	this.eventSender.sendEvent( event2, 'http://foo' );

	assert.strictEqual( navigator.sendBeacon.callCount, 2 );
	assert.deepEqual( navigator.sendBeacon.secondCall.args, [
		'http://foo',
		JSON.stringify( [ event2 ] )
	] );
} );
