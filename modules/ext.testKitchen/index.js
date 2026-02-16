'use strict';

const { Experiment, UnenrolledExperiment, OverriddenExperiment } = require( './Experiment.js' );
const ContextualAttributesFactory = require( './ContextualAttributesFactory.js' );
const EventFactory = require( './EventFactory.js' );
const eventSender = require( './eventSender.js' );
const { Instrument, UnsampledInstrument } = require( './Instrument.js' );

const COORDINATOR_DEFAULT = 'default';
const STREAM_NAME = 'product_metrics.web_base';
const SCHEMA_ID = '/analytics/product_metrics/web/base/2.0.0';

const COOKIE_NAME = 'mpo';

const UINT32_MAX = 4294967295; // (2^32) - 1

/**
 * @typedef {Object} Config
 * @property {string} EveryoneExperimentEventIntakeServiceUrl
 * @property {string} LoggedInExperimentEventIntakeServiceUrl
 * @property {string} InstrumentEventIntakeServiceUrl
 * @property {Object.<string,mw.testKitchen.PartialExperimentConfig>} experimentConfigs
 * @property {Object.<string,mw.testKitchen.InstrumentConfig>} instrumentConfigs
 *
 * @ignore
 */

/**
 * @type {Config}
 *
 * @ignore
 */
let config = require( './config.json' );

const contextualAttributesFactory = new ContextualAttributesFactory();
const eventFactory = new EventFactory( contextualAttributesFactory );

/**
 * Gets the details of an experiment.
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
 * @memberof mw.testKitchen
 *
 * @param {string} experimentName The experiment name
 * @return {mw.testKitchen.ExperimentInterface}
 */
function getExperiment( experimentName ) {
	const userExperiments = mw.config.get( 'wgTestKitchenUserExperiments' );

	if ( !userExperiments || !userExperiments.assigned[ experimentName ] ) {
		return new UnenrolledExperiment();
	}

	const assigned = userExperiments.assigned[ experimentName ];

	if ( userExperiments.overrides.includes( experimentName ) ) {
		return new OverriddenExperiment( experimentName, assigned );
	}

	const samplingUnit = userExperiments.sampling_units[ experimentName ];
	const isLoggedInExperiment = samplingUnit === 'mw-user';

	const eventIntakeServiceUrl = isLoggedInExperiment ?
		config.LoggedInExperimentEventIntakeServiceUrl :
		config.EveryoneExperimentEventIntakeServiceUrl;

	const subjectID = isLoggedInExperiment ?
		userExperiments.subject_ids[ experimentName ] :
		'awaiting';

	// Use the base set of contextual attributes from the product_metrics.web_base stream. This
	// can be removed as part of T408186.
	const contextualAttributes =
		config.experimentConfigs[ STREAM_NAME ].contextual_attributes;

	return new Experiment(
		eventFactory,
		eventSender,
		eventIntakeServiceUrl,
		config.experimentConfigs,
		{
			enrolled: experimentName,
			assigned,
			subject_id: subjectID,
			sampling_unit: samplingUnit,
			coordinator: COORDINATOR_DEFAULT,
			stream_name: STREAM_NAME,
			schema_id: SCHEMA_ID,
			contextual_attributes: contextualAttributes
		}
	);
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

function setCookieAndReload( value ) {
	mw.cookie.set( COOKIE_NAME, value );

	// Reloading the window will break the QUnit unit tests. Only do so if we're not in a QUnit
	// testing environment.
	if ( !window.QUnit ) {
		window.location.reload();
	}
}

/**
 * Overrides an experiment enrolment and reloads the page.
 *
 * @param {string} experimentName The name of the experiment
 * @param {string} groupName The assigned group that will override the assigned one
 * @memberof mw.testKitchen
 */
function overrideExperimentGroup(
	experimentName,
	groupName
) {
	const rawOverrides = mw.cookie.get( COOKIE_NAME, null, '' );
	const part = `${ experimentName }:${ groupName }`;

	if ( rawOverrides === '' ) {
		// If the cookie isn't set, then the value of the cookie is the given override.
		setCookieAndReload( part );
	} else if ( !rawOverrides.includes( `${ experimentName }:` ) ) {
		// If the cookie is set but doesn't have an override for the given experiment name/group
		// variant pair, then append the given override.
		setCookieAndReload( `${ rawOverrides };${ part }` );
	} else {
		setCookieAndReload( rawOverrides.replace(
			new RegExp( `${ experimentName }:[A-Za-z0-9][-_.A-Za-z0-9]+?(?=;|$)` ),
			part
		) );
	}
}

/**
 * Clears all enrolment overrides for the experiment and reloads the page.
 *
 * @param {string} experimentName
 * @memberof mw.testKitchen
 */
function clearExperimentOverride( experimentName ) {
	const rawOverrides = mw.cookie.get( COOKIE_NAME, null, '' );

	let newRawOverrides = rawOverrides.replace(
		new RegExp( `;?${ experimentName }:[A-Za-z0-9][-_.A-Za-z0-9]+` ),
		''
	);

	// If the new cookie starts with a ';' character, then trim it.
	newRawOverrides = newRawOverrides.replace( /^;/, '' );

	// If the new cookie is empty, then clear the cookie.
	newRawOverrides = newRawOverrides || null;

	setCookieAndReload( newRawOverrides );
}

/**
 * Clears all experiment enrolment overrides for all experiments and reloads the page.
 *
 * @memberof mw.testKitchen
 */
function clearExperimentOverrides() {
	setCookieAndReload( null );
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
		instrumentConfig
	);
}

// ---

/**
 * @namespace mw.testKitchen
 */
mw.testKitchen = {
	getExperiment,
	getAssignments,
	getInstrument,
	overrideExperimentGroup,
	clearExperimentOverride,
	clearExperimentOverrides

};

/**
 * @namespace mw.tk
 * @borrows mw.testKitchen.getExperiment as getExperiment
 * @borrows mw.testKitchen.getAssignments as getAssignments
 * @borrows mw.testKitchen.getInstrument as getInstrument
 * @borrows mw.testKitchen.overrideExperimentGroup as overrideExperimentGroup
 * @borrows mw.testKitchen.clearExperimentOverride as clearExperimentOverride
 * @borrows mw.testKitchen.clearExperimentOverrides as clearExperimentOverrides
 */
mw.tk = mw.testKitchen;

// JS overriding experimentation feature
if ( window.QUnit ) {
	const originalConfig = config;

	mw.testKitchen = Object.assign( mw.testKitchen, {
		EventFactory,
		eventSender,
		Experiment,
		UnenrolledExperiment,
		OverriddenExperiment,
		Instrument,
		UnsampledInstrument,

		/**
		 * @param {Config} newConfig
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
		}
	} );
}
