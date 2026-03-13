/* eslint-env qunit */

const { OverriddenExperiment, UnenrolledExperiment } = require( './Experiment.js' );

let globalEventCount;

/**
 * @implements {mw.testKitchen.StubbedExperimentInterface}
 */
class StubExperiment extends OverriddenExperiment {
	constructor( name, assigned ) {
		super( name, assigned );

		/** @type {mw.testKitchen.TestEvent[]} */
		this.events = [];

		this.eventCount = 0;
	}

	send( action, interactionData, contextualAttributes ) {
		this.events.push( {
			action,
			interactionData: interactionData || {},
			contextualAttributes: contextualAttributes || []
		} );

		++this.eventCount;
		++globalEventCount;
	}
}

/**
 * Stubs {@link mw.testKitchen.getExperiment}, allowing developers to test their experiments.
 *
 * @example
 * const tk = mw.testKitchen.useFakeExperiments();
 * const e = tk.stubExperiment( 'my-awesome-experiment', 'treatment' );
 *
 * // Run Code Under Test
 *
 * assert.strictEqual( e.eventCount, 1 );
 * assert.strictEqual( e.events[ 0 ].action, 'my-awesome-action' );
 *
 * assert.strictEqual( tk.globalEventCount, 1 );
 *
 * @memberof mw.testKitchen
 *
 * @return {mw.testKitchen.FakeExperimentsHelper}
 */
function useFakeExperiments() {
	// Reset the global event count.
	globalEventCount = 0;

	/** @type {Map<string,StubExperiment>} */
	const experiments = new Map();

	// Stub mw.testKitchen.getExperiment() to return stubbed experiments. Stubbed experiments are
	// created using stubExperiment() below.
	const oldGetExperiment = mw.testKitchen.getExperiment;

	mw.testKitchen.getExperiment = ( experimentName ) => {
		if ( !experiments.has( experimentName ) ) {
			return new UnenrolledExperiment();
		}

		return experiments.get( experimentName );
	};

	const result = {
		restore: () => {
			mw.testKitchen.getExperiment = oldGetExperiment;
		},

		stubExperiment: ( experimentName, assigned ) => {
			const e = new StubExperiment( experimentName, assigned );

			experiments.set( experimentName, e );

			return e;
		}
	};

	Object.defineProperty( result, 'globalEventCount', {
		get() {
			return globalEventCount;
		}
	} );

	return result;
}

module.exports = useFakeExperiments;
