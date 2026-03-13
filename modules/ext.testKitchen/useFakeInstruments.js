/* eslint-env qunit */

const { UnsampledInstrument } = require( './Instrument.js' );

let globalEventCount;

/**
 * @implements {mw.testKitchen.StubbedInstrumentInterface}
 */
class StubInstrument {
	constructor() {
		/** @type {mw.testKitchen.TestEvent[]} */
		this.events = [];

		this.eventCount = 0;
	}

	send( action, interactionData ) {
		this.events.push( {
			action,
			interactionData: interactionData || {}
		} );

		++this.eventCount;
		++globalEventCount;
	}

	isInSample() {
		return true;
	}

	sendImmediately( action, interactionData ) {
		this.send( action, interactionData );
	}

	// eslint-disable-next-line no-unused-vars
	setSchema( schemaID ) {
		return this;
	}

	submitInteraction( action, interactionData ) {
		this.send( action, interactionData );
	}
}

/**
 * Stubs {@link mw.testKitchen.getInstrument}, allowing developers to test their instruments.
 *
 * @example
 * const tk = mw.testKitchen.useFakeInstruments();
 * const i = tk.stubInstrument( 'my-awesome-instrument' );
 *
 * // Run Code Under Test
 *
 * assert.strictEqual( i.eventCount, 1 );
 * assert.strictEqual( i.events[ 0 ].action, 'my-awesome-action' );
 *
 * assert.strictEqual( tk.globalEventCount, 1 );
 *
 * @memberof mw.testKitchen
 *
 * @return {mw.testKitchen.FakeInstrumentsHelper}
 */
function useFakeInstruments() {
	// Reset the global event count.
	globalEventCount = 0;

	/** @type {Map<string,StubInstrument>} */
	const instruments = new Map();

	// Stub mw.testKitchen.getInstrument() to return stubbed instrument. Stubbed instruments are
	// created using stubInstrument() below.
	const oldGetInstrument = mw.testKitchen.getInstrument;

	mw.testKitchen.getInstrument = ( instrumentName ) => {
		if ( !instruments.has( instrumentName ) ) {
			return new UnsampledInstrument();
		}

		return instruments.get( instrumentName );
	};

	const result = {
		restore: () => {
			mw.testKitchen.getInstrument = oldGetInstrument;
		},

		stubInstrument: ( instrumentName ) => {
			const i = new StubInstrument();

			instruments.set( instrumentName, i );

			return i;
		}
	};

	Object.defineProperty( result, 'globalEventCount', {
		get() {
			return globalEventCount;
		}
	} );

	return result;
}

module.exports = useFakeInstruments;
