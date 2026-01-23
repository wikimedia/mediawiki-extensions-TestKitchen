'use strict';

const { Experiment, UnenrolledExperiment, OverriddenExperiment } = require( './Experiment.js' );

const COOKIE_NAME = 'mpo';
/**
 * @type {Object}
 * @property {string} EveryoneExperimentEventIntakeServiceUrl
 * @property {string} LoggedInExperimentEventIntakeServiceUrl
 * @property {string} InstrumentEventIntakeServiceUrl
 * @property {Object|false} streamConfigs
 * @property {Object} instrumentConfigs
 * @ignore
 */
const config = require( './config.json' );

const { newMetricsClient, DefaultEventSubmitter } = require( 'ext.eventLogging.metricsPlatform' );

/**
 * @param {Object} streamConfigs
 * @param {string} intakeServiceUrl
 * @return {Object}
 * @ignore
 */
function newMetricsClientInternal( streamConfigs, intakeServiceUrl ) {
	return newMetricsClient( streamConfigs, new DefaultEventSubmitter( intakeServiceUrl ) );
}

const everyoneExperimentMetricsClient = newMetricsClientInternal(
	config.streamConfigs,
	config.EveryoneExperimentEventIntakeServiceUrl
);

const loggedInExperimentMetricsClient = newMetricsClientInternal(
	config.streamConfigs,
	config.LoggedInExperimentEventIntakeServiceUrl
);

/**
 * Gets an {@link mw.testKitchen.Experiment} instance that encapsulates the result of enrolling the current
 * user into the experiment. You can use that instance to get which group the user was assigned
 * when they were enrolled into the experiment and send experiment-related analytics events.
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
 * @param {string} experimentName The experiment name
 * @return {Experiment}
 * @memberof mw.testKitchen
 */
function getExperiment( experimentName ) {
	const userExperiments = mw.config.get( 'wgTestKitchenUserExperiments' );

	if (
		userExperiments &&
		userExperiments.active_experiments.includes( experimentName ) &&
		userExperiments.sampling_units[ experimentName ] === 'mw-user' &&
		!userExperiments.assigned[ experimentName ]
	) {
		return new UnenrolledExperiment( experimentName );
	} else {
		// For now, regarding logged-out experiments, there is no way to distinguish between
		// an experiment that is not active, doesn't exist or the current user is not enrolled in
		if ( !userExperiments || !userExperiments.assigned[ experimentName ] ) {
			return new UnenrolledExperiment( experimentName );
		}
	}

	const assignedGroup = userExperiments.assigned[ experimentName ];
	const samplingUnit = userExperiments.sampling_units[ experimentName ];
	const isLoggedInExperiment = samplingUnit === 'mw-user';
	const subjectId = isLoggedInExperiment ?
		userExperiments.subject_ids[ experimentName ] :
		'awaiting';

	if ( userExperiments.overrides.includes( experimentName ) ) {
		return new OverriddenExperiment(
			experimentName,
			assignedGroup,
			samplingUnit,
			subjectId
		);
	}

	// Provide an alternate MetricsClient for logged-in experiments to override the
	// eventIntakeServiceUrl set by config (wgTestKitchenExperimentEventIntakeServiceUrl
	// = '/evt-103e/v2/events?hasty=true' on production) which drops events if everyone experiment
	// enrollments are not included. DefaultEventSubmitter sets DEFAULT_EVENT_INTAKE_URL to the
	// eventgate-analytics-external cluster. See https://phabricator.wikimedia.org/T395779.
	const metricsClient = isLoggedInExperiment ?
		loggedInExperimentMetricsClient :
		everyoneExperimentMetricsClient;

	return new Experiment(
		metricsClient,
		experimentName,
		assignedGroup,
		subjectId,
		samplingUnit
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

const instrumentMetricsClient = newMetricsClientInternal(
	config.instrumentConfigs,
	config.InstrumentEventIntakeServiceUrl
);

/**
 * Creates a new {@link Instrument} instance using config fetched from Test Kitchen.
 *
 * @param {string} instrumentName
 * @return {Instrument}
 * @memberof mw.testKitchen
 */
function getInstrument( instrumentName ) {
	return instrumentMetricsClient.newInstrument( instrumentName );
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
	mw.testKitchen = Object.assign( mw.testKitchen, {
		Experiment,
		UnenrolledExperiment,
		OverriddenExperiment
	} );
}

require( './Experiments.js' );
