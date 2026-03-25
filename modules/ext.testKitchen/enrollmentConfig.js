const OVERRIDE_PARAM_NAME = 'mpo';

const SEPARATOR_OVERRIDES = ':';
const SEPARATOR_HEADER = '=';

const SUBJECT_ID_OVERRIDDEN = 'overridden';
const SUBJECT_ID_AWAITING = 'awaiting';

// This MUST be kept in sync with the Varnish configuration that adds the Server-Timing header to
// the response. See
// https://gerrit.wikimedia.org/g/operations/puppet/+/33c2c7f16099cd1aa8e30915fa1d80af391e4324/modules/varnish/templates/wikimedia-frontend.vcl.erb#1209
const HEADER_NAME = 'WMF-Uniq';

function setCookieAndReload( value ) {
	mw.cookie.set( OVERRIDE_PARAM_NAME, value );

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
	const rawOverrides = mw.cookie.get( OVERRIDE_PARAM_NAME, null, '' );
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
	const rawOverrides = mw.cookie.get( OVERRIDE_PARAM_NAME, null, '' );

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
 * @param {string} rawValue
 * @param {string} separator
 * @return {Object<string,string>}
 *
 * @ignore
 */
function processRawValue( rawValue, separator ) {
	const result = {};

	let chr;
	let acc = '';
	let state = 0;
	let experimentName = '';

	for ( let i = 0; i < rawValue.length; ++i ) {
		chr = rawValue[ i ];

		if ( chr === separator ) {
			if ( state !== 0 ) {
				throw new Error( `Unexpected "${ separator }" while processing experiment name` );
			}

			experimentName = acc;

			acc = '';
			state = 1;
		} else if ( chr === ';' ) {
			if ( state !== 1 ) {
				throw new Error( 'Unexpected ";" character while processing experiment group' );
			}

			result[ experimentName ] = acc;

			acc = '';
			state = 0;
			experimentName = '';
		} else {
			acc += chr;
		}
	}

	if ( state !== 0 || acc !== '' ) {
		throw new Error( 'Unexpected end of raw value' );
	}

	return result;
}

// ---

/**
 * @type {Object<string,string>|null}
 *
 * @ignore
 */
let overriddenEnrollmentConfigs = null;

/**
 * @param {Object<string,string>} acc
 * @param {string} rawValue
 * @param {string} type
 *
 * @ignore
 */
function processRawOverrideValue( acc, rawValue, type ) {
	if ( !rawValue ) {
		return;
	}

	if ( !rawValue.endsWith( ';' ) ) {
		rawValue += ';';
	}

	try {
		Object.assign( acc, processRawValue( rawValue, SEPARATOR_OVERRIDES ) );
	} catch ( e ) {
		mw.errorLogger.logError(
			e,
			`error.test_kitchen.process_raw_override_value.${ type }`
		);
	}
}

/**
 * This function is memoized and returns the same value for the lifetime of the module.
 *
 * @return {Object<string, string>}
 *
 * @ignore
 */
function getOverriddenEnrollments() {
	if ( overriddenEnrollmentConfigs ) {
		return overriddenEnrollmentConfigs;
	}

	overriddenEnrollmentConfigs = {};

	processRawOverrideValue(
		overriddenEnrollmentConfigs,
		mw.cookie.get( OVERRIDE_PARAM_NAME, null, '' ),
		'cookie'
	);

	// Process the querystring second so that it takes priority.
	processRawOverrideValue(
		overriddenEnrollmentConfigs,
		new URLSearchParams( window.location.search ).get( OVERRIDE_PARAM_NAME ),
		'query'
	);

	return overriddenEnrollmentConfigs;
}

// ---

/**
 * @type {Promise<string>|null}
 *
 * @ignore
 */
let rawHeaderPromise = null;

/**
 * Gets the raw value of the external-facing experiment enrollments header.
 *
 * This function is memoized and returns the same value for the lifetime of the module.
 *
 * @return {Promise<string>}
 *
 * @ignore
 */
function getRawHeader() {
	if ( rawHeaderPromise ) {
		return rawHeaderPromise;
	}

	rawHeaderPromise = new Promise( ( resolve ) => {
		const observer = new PerformanceObserver(
			( list ) => {

				/** @type {PerformanceResourceTiming[]} */
				const entries = list.getEntries();
				let result = '';

				entries.forEach( ( entry ) => {
					entry.serverTiming.forEach( ( serverTimingEntry ) => {
						if ( serverTimingEntry.name === HEADER_NAME ) {
							result = serverTimingEntry.description;
						}
					} );
				} );

				observer.disconnect();

				resolve( result );
			} );

		observer.observe( {
			type: 'navigation',
			buffered: true
		} );
	} );

	return rawHeaderPromise;
}

/**
 * @return {Promise<Object<string,string>>}
 *
 * @ignore
 */
function getHeaderEnrollments() {
	return getRawHeader().then(
		( rawHeader ) => {
			try {
				return processRawValue( rawHeader, SEPARATOR_HEADER );
			} catch ( e ) {
				mw.errorLogger.logError( e, 'error.test_kitchen.process_header' );

				return {};
			}
		}
	);
}

// ---

/**
 * @param {Object} obj
 * @param {string} prop
 * @return {boolean}
 *
 * @ignore
 */
function has( obj, prop ) {
	return Object.prototype.hasOwnProperty.call( obj, prop );
}

/**
 * @param {string} experimentName
 * @param {Object<string,string>} [fromHeader]
 * @return {mw.testKitchen.EnrollmentConfig|null}
 *
 * @ignore
 */
function getInternal( experimentName, fromHeader ) {
	const fromOverrides = getOverriddenEnrollments();
	const fromServer = mw.config.get( 'wgTestKitchenUserExperiments' );

	const otherAssigned = Object.assign(
		{},
		fromServer && fromServer.assigned || {}, // fromServer could be undefined or null
		fromHeader,
		fromOverrides
	);
	delete otherAssigned[ experimentName ];

	// 1. Has the experiment enrollment been overridden?
	if ( has( fromOverrides, experimentName ) ) {
		return {
			enrolled: experimentName,
			assigned: fromOverrides[ experimentName ],
			subject_id: SUBJECT_ID_OVERRIDDEN,
			is_override: true,
			other_assigned: otherAssigned
		};
	}

	// 2. Does the external-facing header contain enrollment information for the experiment?
	if (
		fromHeader &&
		has( fromHeader, experimentName )
	) {
		return {
			enrolled: experimentName,
			assigned: fromHeader[ experimentName ],
			subject_id: SUBJECT_ID_AWAITING,
			is_override: false,
			other_assigned: otherAssigned
		};
	}

	// 3. Does the enrollment information from the server contain enrollment information for the
	//   experiment?
	if (
		fromServer &&
		fromServer.assigned &&
		has( fromServer.assigned, experimentName )
	) {
		return {
			enrolled: experimentName,
			assigned: fromServer.assigned[ experimentName ],
			subject_id: fromServer.subject_ids[ experimentName ],
			is_override: false,
			other_assigned: otherAssigned
		};
	}

	return null;
}

/**
 * @param {string} experimentName
 * @return {mw.testKitchen.EnrollmentConfig|null}
 *
 * @ignore
 */
function get( experimentName ) {
	return getInternal( experimentName );
}

/**
 * @param {string} experimentName
 * @return {Promise<mw.testKitchen.EnrollmentConfig|null>}
 *
 * @ignore
 */
function getAsync( experimentName ) {
	return getHeaderEnrollments().then(
		( fromHeader ) => getInternal( experimentName, fromHeader )
	);
}

/**
 * @param {Object<string,string>} obj
 * @param {string} prefix
 * @return {string[]}
 *
 * @ignore
 */
function getMatchingKeys( obj, prefix ) {
	return Object.keys( obj )
		.filter( ( key ) => key.startsWith( prefix ) );
}

/**
 * @param {string} experimentNamePrefix
 * @param {Object<string,string>} [fromHeader]
 * @return {mw.testKitchen.EnrollmentConfig[]}
 *
 * @ignore
 */
function getMatchingInternal( experimentNamePrefix, fromHeader ) {
	const fromOverrides = getOverriddenEnrollments();
	const fromServer = mw.config.get( 'wgTestKitchenUserExperiments' );

	const allAssigned = Object.assign(
		{},
		fromServer && fromServer.assigned || {}, // fromServer could be undefined or null
		fromHeader,
		fromOverrides
	);

	const acc = {};

	// 1. Does the enrollment information from the server contain enrollment information for an
	//    experiment matching the prefix?
	if ( fromServer && fromServer.assigned ) {
		getMatchingKeys( fromServer.assigned, experimentNamePrefix )
			.map( ( experimentName ) => {
				const otherAssigned = Object.assign( {}, allAssigned );
				delete otherAssigned[ experimentName ];

				return {
					enrolled: experimentName,
					assigned: fromServer.assigned[ experimentName ],
					subject_id: fromServer.subject_ids[ experimentName ],
					is_override: false,
					other_assigned: otherAssigned
				};
			} )
			.forEach( ( ec ) => {
				acc[ ec.enrolled ] = ec;
			} );
	}

	// 2. Does the external-facing header contain enrollment information for an experiment matching
	//    the prefix?
	if ( fromHeader ) {
		getMatchingKeys( fromHeader, experimentNamePrefix )
			.map( ( experimentName ) => {
				const otherAssigned = Object.assign( {}, allAssigned );
				delete otherAssigned[ experimentName ];

				return {
					enrolled: experimentName,
					assigned: fromHeader[ experimentName ],
					subject_id: SUBJECT_ID_AWAITING,
					is_override: false,
					other_assigned: otherAssigned
				};
			} )
			.forEach( ( ec ) => {
				acc[ ec.enrolled ] = ec;
			} );
	}

	// 3. Is there an overridden experiment matching the prefix?
	getMatchingKeys( fromOverrides, experimentNamePrefix )
		.map( ( experimentName ) => {
			const otherAssigned = Object.assign( {}, allAssigned );
			delete otherAssigned[ experimentName ];

			return {
				enrolled: experimentName,
				assigned: fromOverrides[ experimentName ],
				subject_id: SUBJECT_ID_OVERRIDDEN,
				is_override: true,
				other_assigned: otherAssigned
			};
		} )
		.forEach( ( ec ) => {
			acc[ ec.enrolled ] = ec;
		} );

	return Object.values( acc );
}

/**
 * @param {string} experimentNamePrefix
 * @return {mw.testKitchen.EnrollmentConfig[]}
 *
 * @ignore
 */
function getMatching( experimentNamePrefix ) {
	return getMatchingInternal( experimentNamePrefix, null );
}

/**
 * @param {string} experimentNamePrefix
 * @return {Promise<mw.testKitchen.EnrollmentConfig[]>}
 *
 * @ignore
 */
function getMatchingAsync( experimentNamePrefix ) {
	return getHeaderEnrollments().then(
		( fromHeader ) => getMatchingInternal( experimentNamePrefix, fromHeader )
	);
}

module.exports = {
	overrideExperimentGroup,
	clearExperimentOverride,
	clearExperimentOverrides,
	get,
	getAsync,
	getMatching,
	getMatchingAsync
};

if ( window.QUnit ) {
	module.exports = Object.assign( module.exports, {
		processRawValue,
		getOverriddenEnrollments,
		getHeaderEnrollments,

		/**
		 * @param {Object<string,string>} value
		 *
		 * @ignore
		 */
		setOverriddenEnrollmentConfigs( value ) {
			overriddenEnrollmentConfigs = value;
		},

		/**
		 * @param {Promise<string>} value
		 *
		 * @ignore
		 */
		setRawHeaderPromise( value ) {
			rawHeaderPromise = value;
		},

		/**
		 * @ignore
		 */
		reset() {
			overriddenEnrollmentConfigs = null;
			rawHeaderPromise = null;
		}
	} );
}
