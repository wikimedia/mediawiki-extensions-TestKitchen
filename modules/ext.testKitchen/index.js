'use strict';

const { Experiment, UnenrolledExperiment, OverriddenExperiment } = require( './Experiment.js' );
const ContextualAttributesFactory = require( './ContextualAttributesFactory.js' );
const EventFactory = require( './EventFactory.js' );
const eventSender = require( './eventSender.js' );
const { Instrument, UnsampledInstrument } = require( './Instrument.js' );
const ExposureLogTracker = require( './ExposureLogTracker.js' );
const {
	overrideExperimentGroup,
	clearExperimentOverride,
	clearExperimentOverrides,
	get,
	getAsync,
	getMatching,
	getMatchingAsync,
	reset: resetEnrollmentConfigs
} = require( './enrollmentConfig.js' );

const SCHEMA_ID = '/analytics/product_metrics/web/base/2.0.0';

const UINT32_MAX = 4294967295; // (2^32) - 1

/**
 * @typedef {Object} ConfigFromServer
 * @property {string} EveryoneExperimentEventIntakeServiceUrl
 * @property {string} LoggedInExperimentEventIntakeServiceUrl
 * @property {string} InstrumentEventIntakeServiceUrl
 * @property {Object.<string,string[]>} streamNameToContextualAttributesMap
 * @property {Object.<string,mw.testKitchen.PartialExperimentConfig>} experimentConfigs
 * @property {Object.<string,mw.testKitchen.PartialInstrumentConfig>} instrumentConfigs
 *
 * @ignore
 */

/**
 * @type {ConfigFromServer}
 *
 * @ignore
 */
let config = require( './config.json' );

const contextualAttributesFactory = new ContextualAttributesFactory();
const eventFactory = new EventFactory( contextualAttributesFactory );
const exposureLogTracker = new ExposureLogTracker();

/**
 * Creates a new {@link mw.testKitchen.ExperimentInterface} instance given the experiment name and
 * an enrollment config.
 *
 * @param {mw.testKitchen.EnrollmentConfig} enrollmentConfig
 * @return {mw.testKitchen.ExperimentInterface}
 *
 * @ignore
 */
function newExperiment( enrollmentConfig ) {
	if ( !enrollmentConfig ) {
		return new UnenrolledExperiment();
	}

	const experimentName = enrollmentConfig.enrolled;

	if ( enrollmentConfig.is_override ) {
		return new OverriddenExperiment( experimentName, enrollmentConfig.assigned );
	} else if ( !config.experimentConfigs[ experimentName ] ) {
		return new UnenrolledExperiment();
	}

	const experimentConfig = config.experimentConfigs[ experimentName ];
	const isLoggedInExperiment = experimentConfig.user_identifier_type === 'mw-user';
	const eventIntakeServiceUrl = isLoggedInExperiment ?
		config.LoggedInExperimentEventIntakeServiceUrl :
		config.EveryoneExperimentEventIntakeServiceUrl;

	return new Experiment(
		eventFactory,
		eventSender,
		eventIntakeServiceUrl,
		exposureLogTracker,
		config.streamNameToContextualAttributesMap,
		{
			enrolled: experimentName,
			assigned: enrollmentConfig.assigned,
			subject_id: enrollmentConfig.subject_id,
			sampling_unit: experimentConfig.user_identifier_type,
			stream_name: experimentConfig.stream_name,
			schema_id: experimentConfig.schema_id,
			contextual_attributes: experimentConfig.contextual_attributes,
			exposure_version: experimentConfig.exposure_version,
			other_assigned: enrollmentConfig.other_assigned
		}
	);
}

/**
 * Gets the details of an experiment.
 *
 * This method is provided for backwards compatibility with existing experiments. Please use
 * {@link mw.testKitchen.getExperiment} instead.
 *
 * This method always returns an instance of {@link mw.testKitchen.ExperimentInterface} that can:
 *
 * 1. Get information about the user's (more precisely, the subject's) enrollment in the experiment
 * 2. Send analytics events relating to the experiment
 *
 * @example
 * const e = mw.testKitchen.getExperiment( 'my-awesome-experiment' );
 * const myAwesomeDialog = require( 'my.awesome.dialog' );
 *
 * [
 *   'open',
 *   'default-action',
 *   'primary-action'
 * ].forEach( ( event ) => {
 *   myAwesomeDialog.on( event, () => e.send( event ) );
 * } );
 *
 * // Was the current user assigned to the treatment group?
 * if ( e.isAssignedGroup( 'treatment' ) ) {
 *   myAwesomeDialog.primaryAction.label = 'Awesome!';
 * }
 *
 * @memberof mw.testKitchen.compat
 *
 * @param {string} experimentName The experiment name
 * @return {mw.testKitchen.ExperimentInterface}
 */
function getExperiment( experimentName ) {
	return newExperiment( get( experimentName ) );
}

/**
 * Gets the details of an experiment.
 *
 * This method returns a promise that will always resolve with an instance of
 * {@link mw.testKitchen.ExperimentInterface} that can:
 *
 * 1. Get information about the user's (more precisely, the subject's) enrollment in the experiment
 * 2. Send analytics events relating to the experiment
 *
 * @example
 * const myAwesomeDialog = require( 'my.awesome.dialog' );
 *
 * mw.testKitchen.async.getExperiment( 'my-awesome-non-cache-splitting-experiment' )
 *   .then( ( e ) => {
 *     [
 *       'open',
 *       'default-action',
 *       'primary-action'
 *     ].forEach( ( event ) => {
 *         myAwesomeDialog.on( event, () => e.send( event ) );
 *     } );
 *
 *     // Was the current user assigned to the treatment group?
 *     if ( e.isAssignedGroup( 'treatment' ) ) {
 *       myAwesomeDialog.primaryAction.label = 'Awesome!';
 *     }
 *   } );
 *
 * @method getExperiment
 * @memberof mw.testKitchen
 *
 * @param {string} experimentName The experiment name
 * @return {Promise<mw.testKitchen.ExperimentInterface>}
 */
function getExperimentAsync( experimentName ) {
	return getAsync( experimentName ).then( newExperiment );
}

/**
 * Gets the details of all experiments with names that start with the given prefix.
 *
 * This method is provided for backwards compatibility with existing experiment code. Please use
 * {@link mw.testKitchen.getExperimentsByPrefix} instead.
 *
 * This method should only be used for experiments that repeat, e.g. a data collection activity that
 * lasts three weeks and repeats every week, and therefore usage is expected to be rare. In these
 * cases, this method can be used to minimize the number of code changes in the experiment.
 *
 * Note well that the details are returned in any order.
 *
 * @see mw.testKitchen.compat.getExperiment
 *
 * @example
 * // The user is enrolled in the following experiments:
 * //
 * // - my-awesome-experiment-1
 * // - my-awesome-experiment-2
 * // - my-other-awesome-experiment
 *
 * // Gets the details of the "my-awesome-experiment-1" and "my-awesome-experiment-2" experiments
 * mw.testKitchen.getExperimentByPrefix( 'my-awesome-experiment-' );
 *
 * @memberof mw.testKitchen.compat
 *
 * @package
 *
 * @param {string} experimentNamePrefix
 * @return {mw.testKitchen.ExperimentInterface[]}
 */
function getExperimentsByPrefix( experimentNamePrefix ) {
	return getMatching( experimentNamePrefix )
		.map( newExperiment );
}

/**
 * Gets the details of all experiments with names that start with the given prefix.
 *
 * This method should only be used for experiments that repeat, e.g. a data collection activity that
 * lasts three weeks and repeats every week, and therefore usage is expected to be rare. In these
 * cases, this method can be used to minimize the number of code changes in the experiment.
 *
 * Note well that the details are returned in any order.
 *
 * @see mw.testKitchen.getExperiment
 *
 * @example
 * // The user is enrolled in the following experiments:
 * //
 * // - my-awesome-experiment-1
 * // - my-awesome-experiment-2
 * // - my-other-awesome-experiment
 *
 * // Gets the details of the "my-awesome-experiment-1" and "my-awesome-experiment-2" experiments
 * mw.testKitchen.getExperimentByPrefix( 'my-awesome-experiment-' );
 *
 * @method getExperimentsByPrefix
 * @memberof mw.testKitchen
 *
 * @package
 *
 * @param {string} experimentNamePrefix
 * @return {Promise<mw.testKitchen.ExperimentInterface[]>}
 */
function getExperimentsByPrefixAsync( experimentNamePrefix ) {
	return getMatchingAsync( experimentNamePrefix )
		.then( ( matching ) => matching.map( newExperiment ) );
}

/**
 * Gets a map of experiment to group for all experiments that the current user is enrolled into.
 *
 * This method is internal and should only be used by other Test Kitchen components.
 * Currently, this method is only used by
 * [the Client Error Logging instrument in WikimediaEvents][0].
 *
 * @internal
 *
 * [0]: https://gerrit.wikimedia.org/r/plugins/gitiles/mediawiki/extensions/WikimediaEvents/+/refs/heads/master/OWNERS.md#client-error-logging
 *
 * @return {Object}
 * @memberof mw.testKitchen
 */
function getAssignments() {
	const userExperiments = mw.config.get( 'wgTestKitchenUserExperiments' );

	return userExperiments ? Object.assign( {}, userExperiments.assigned ) : {};
}

// ---

/**
 * This method is the same as https://gitlab.wikimedia.org/repos/data-engineering/metrics-platform/-/blob/759ce7203ad50776d1e29b1c0979ef3bb50c6a33/js/src/SamplingController.js#L22.
 * That method was written and maintained by the authors of this extension.
 *
 * @ignore
 *
 * @param {mw.testKitchen.InstrumentSamplingConfig} instrumentSamplingConfig
 */
function isInstrumentInSample( instrumentSamplingConfig ) {
	let id;
	const { performer } = contextualAttributesFactory.newContextualAttributes();

	switch ( instrumentSamplingConfig.unit ) {
		case 'pageview':
			id = performer.pageview_id;
			break;
		case 'session':
			id = performer.session_id;
			break;
		default:
			return false;
	}

	return parseInt( id.slice( 0, 8 ), 16 ) / UINT32_MAX < instrumentSamplingConfig.rate;
}

/**
 * Gets details of an instrument.
 *
 * This method always returns an instance of {@link mw.testKitchen.InstrumentInterface} that can:
 *
 * 1. Determine whether the instrument is in-sample
 * 2. Send analytics events
 *
 * @memberof mw.testKitchen
 *
 * @param {string} instrumentName
 * @return {mw.testKitchen.InstrumentInterface}
 */
function getInstrument( instrumentName ) {
	const instrumentConfig = config.instrumentConfigs[ instrumentName ];

	if (
		!instrumentConfig ||
		( instrumentConfig.sample && !isInstrumentInSample( instrumentConfig.sample ) )
	) {
		return new UnsampledInstrument();
	}

	instrumentConfig.schema_id = SCHEMA_ID;

	return new Instrument(
		eventFactory,
		eventSender,
		config.InstrumentEventIntakeServiceUrl,
		instrumentName,
		instrumentConfig
	);
}

// ---

/**
 * @namespace mw.testKitchen
 */
mw.testKitchen = {
	getExperiment: getExperimentAsync,
	getExperimentsByPrefix: getExperimentsByPrefixAsync,
	getAssignments,
	getInstrument,
	overrideExperimentGroup,
	clearExperimentOverride,
	clearExperimentOverrides
};

/**
 * @namespace mw.testKitchen.compat
 */
mw.testKitchen.compat = {
	getExperiment,
	getExperimentsByPrefix
};

/**
 * @namespace mw.tk
 * @borrows mw.testKitchen.getExperiment as getExperiment
 * @borrows mw.testKitchen.getExperimentsByPrefix as getExperimentsByPrefix
 * @borrows mw.testKitchen.getAssignments as getAssignments
 * @borrows mw.testKitchen.getInstrument as getInstrument
 * @borrows mw.testKitchen.overrideExperimentGroup as overrideExperimentGroup
 * @borrows mw.testKitchen.clearExperimentOverride as clearExperimentOverride
 * @borrows mw.testKitchen.clearExperimentOverrides as clearExperimentOverrides
 * @borrows mw.testKitchen.useFakeExperiments as useFakeExperiments
 * @borrows mw.testKitchen.useFakeInstruments as useFakeInstruments
 */
mw.tk = mw.testKitchen;

/**
 * @namespace mw.tk.compat
 * @borrows mw.testKitchen.compat.getExperiment as getExperiment
 * @borrows mw.testKitchen.compat.getExperimentsByPrefix as getExperimentsByPrefix
 */
mw.tk.compat = mw.testKitchen.compat;

// JS overriding experimentation feature
if ( window.QUnit ) {
	const originalConfig = config;
	const useFakeExperiments = require( './useFakeExperiments.js' );
	const useFakeInstruments = require( './useFakeInstruments.js' );

	mw.testKitchen = Object.assign( mw.testKitchen, {
		EventFactory,
		eventSender,
		Experiment,
		UnenrolledExperiment,
		OverriddenExperiment,
		Instrument,
		UnsampledInstrument,
		ExposureLogTracker,

		/**
		 * @param {ConfigFromServer} newConfig
		 *
		 * @ignore
		 */
		setConfig( newConfig ) {
			config = newConfig;
		},

		/**
		 * @ignore
		 */
		resetConfig() {
			config = originalConfig;
		},

		useFakeExperiments,
		useFakeInstruments,

		resetEnrollmentConfigs
	} );
}
