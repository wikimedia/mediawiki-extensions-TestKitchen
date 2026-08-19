const EXPOSURE_CONTEXTUAL_ATTRIBUTES = [
	'performer_is_logged_in',
	'performer_is_temp',
	'performer_is_bot',
	'performer_edit_count',
	'mediawiki_database'
];

// T423574: If cookies are disabled, then act as if the user isn't enrolled in any
// experiment.
//
// eslint-disable-next-line compat/compat
const COOKIES_DISABLED = navigator.cookieEnabled !== undefined ? !navigator.cookieEnabled : false;

const COORDINATOR_DEFAULT = 'default';
const COORDINATOR_FORCED = 'forced';

/**
 * @class
 * @implements {mw.testKitchen.ExperimentInterface}
 *
 * @memberof mw.testKitchen
 *
 * @hideconstructor
 */
class Experiment {

	/**
	 * @param {mw.testKitchen.EventFactory} eventFactory
	 * @param {mw.testKitchen.InternalEventSender} internalEventSender
	 * @param {string} eventIntakeServiceUrl
	 * @param {mw.testKitchen.ExposureLogTracker} exposureLogTracker
	 * @param {mw.testKitchen.ExperimentConfig} config
	 */
	constructor(
		eventFactory,
		internalEventSender,
		eventIntakeServiceUrl,
		exposureLogTracker,
		config
	) {
		this.eventFactory = eventFactory;
		this.internalEventSender = internalEventSender;
		this.eventIntakeServiceUrl = eventIntakeServiceUrl;
		this.config = config;
		this.streamName = config.stream_name;
		this.schemaID = config.schema_id;
		this.tracker = exposureLogTracker;
		this.exposureVersion = config.exposure_version;
		this.contextualAttributes = config.contextual_attributes;
	}

	getAssignedGroup() {
		return this.config.assigned;
	}

	isAssignedGroup( ...groups ) {
		return groups.includes( this.getAssignedGroup() );
	}

	use( instrumentation ) {
		instrumentation( this );

		return this;
	}

	send( action, interactionData, contextualAttributes ) {
		if ( COOKIES_DISABLED ) {
			return;
		}

		interactionData = prepareInteractionData( this.config, interactionData, COORDINATOR_DEFAULT );
		const eventContextualAttributes = prepareContextualAttributes(
			this.contextualAttributes,
			contextualAttributes
		);

		const event = this.eventFactory.newEvent(
			this.streamName,
			this.schemaID,
			eventContextualAttributes,
			action,
			interactionData
		);

		this.internalEventSender.sendEvent( event, this.eventIntakeServiceUrl );
	}

	submitInteraction( action, interactionData, contextualAttributes ) {
		this.send( action, interactionData, contextualAttributes );
	}

	/**
	 * Logs an exposure event with 2-tier deduplication.
	 */
	sendExposure() {
		const group = this.getAssignedGroup();

		// Experiment Key - ensures automatic reset if config changes
		const key = this.tracker.makeKey( {
			enrolled: this.config.enrolled,
			assigned: group,
			version: this.exposureVersion
		} );

		// trySend marks that an experiment exposure event is about to be sent.
		// If the instrumentation throws an error, then it tidies up.
		this.tracker.trySend( key, () => {
			this.send( 'experiment_exposure', {}, EXPOSURE_CONTEXTUAL_ATTRIBUTES );
		} );
	}

	/**
	 * @deprecated
	 */
	setSchema( schemaID ) {
		this.schemaID = schemaID;

		return this;
	}
}

/**
 * @class
 * @implements {mw.testKitchen.ExperimentInterface}
 *
 * @ignore
 */
class UnenrolledExperiment {
	getAssignedGroup() {
		return null;
	}

	// eslint-disable-next-line no-unused-vars
	isAssignedGroup( ...groups ) {}

	// eslint-disable-next-line no-unused-vars
	use( instrumentation ) {
		return this;
	}

	// eslint-disable-next-line no-unused-vars
	send( action, interactionData, contextualAttributes ) {}

	// eslint-disable-next-line no-unused-vars
	submitInteraction( action, interactionData, contextualAttributes ) {}

	sendExposure() {}

	/**
	 * @deprecated
	 */
	// eslint-disable-next-line no-unused-vars
	setSchema( schemaID ) {
		return this;
	}
}

/**
 * @class
 * @implements {mw.testKitchen.ExperimentInterface}
 *
 * @ignore
 */
class OverriddenExperiment {

	/**
	 * @param {mw.testKitchen.EventFactory} eventFactory
	 * @param {mw.testKitchen.InternalEventSender} internalEventSender
	 * @param {string} eventIntakeServiceUrl
	 * @param {mw.testKitchen.ExperimentConfig} config
	 */
	constructor( eventFactory, internalEventSender, eventIntakeServiceUrl, config ) {
		this.eventFactory = eventFactory;
		this.internalEventSender = internalEventSender;
		this.eventIntakeServiceUrl = eventIntakeServiceUrl;
		this.streamName = config.stream_name;
		this.schemaID = config.schema_id;
		this.config = config;
		this.contextualAttributes = config.contextual_attributes;
	}

	getAssignedGroup() {
		return this.config.assigned;
	}

	isAssignedGroup( ...groups ) {
		return groups.includes( this.getAssignedGroup() );
	}

	use( instrumentation ) {
		instrumentation( this );

		return this;
	}

	// eslint-disable-next-line no-unused-vars
	send( action, interactionData, contextualAttributes ) {
		const message =
			`${ this.config.enrolled }: The enrollment for this experiment has been overridden. ` +
			'The following event will not be sent:\n';

		const args = [ message, action ];
		if ( interactionData ) {
			args.push( JSON.stringify( interactionData, null, 2 ) );
		}
		if ( contextualAttributes ) {
			const perEventContextualAttributes = {};
			this.eventFactory.addContextualAttributes( perEventContextualAttributes, contextualAttributes );
			args.push( JSON.stringify( perEventContextualAttributes, null, 2 ) );
		}
		// eslint-disable-next-line no-console
		console.log.apply( console, args );

		// An overridden experiment sends events when running in the beta cluster (and to the beta cluster stream)
		if ( mw.config.get( 'wgServer' ).includes( '.beta.wmcloud.org' ) ) {
			interactionData = prepareInteractionData( this.config, interactionData, COORDINATOR_FORCED );
			const eventContextualAttributes = prepareContextualAttributes(
				this.contextualAttributes,
				contextualAttributes
			);

			const event = this.eventFactory.newEvent(
				this.streamName,
				this.schemaID,
				eventContextualAttributes,
				action,
				interactionData
			);

			this.internalEventSender.sendEvent( event, this.eventIntakeServiceUrl );
		}
	}

	submitInteraction( action, interactionData, contextualAttributes ) {
		this.send( action, interactionData, contextualAttributes );
	}

	sendExposure() {
		this.send( 'experiment_exposure' );
	}

	/**
	 * @deprecated
	 */
	// eslint-disable-next-line no-unused-vars
	setSchema( schemaID ) {
		return this;
	}
}

/**
 * @private
 * @ignore
 *
 * @param {mw.testKitchen.ExperimentConfig} experimentConfig
 * @param {Object} interactionData
 * @param {string} coordinator
 * @returns {Object}
 */
function prepareInteractionData( experimentConfig, interactionData, coordinator ) {
	// Extract SDK-specific experiment config
	const keys = [ 'enrolled', 'assigned', 'subject_id', 'sampling_unit', 'phase_index' ];
	const experiment = {};

	for ( const key of keys ) {
		experiment[ key ] = experimentConfig[ key ];
	}

	if ( experimentConfig.version !== undefined ) {
		experiment.version = experimentConfig.version;
	}

	experiment.coordinator = coordinator;

	// T421152: Include enrollment information about other experiments that the user is enrolled
	// in.
	const otherAssigned = experimentConfig.other_assigned;

	if ( otherAssigned && Object.keys( otherAssigned ).length > 0 ) {
		experiment.other_assigned = otherAssigned;
	}

	return Object.assign(
		{},
		interactionData,
		{ experiment }
	);
}

/**
 * @private
 * @ignore
 *
 * Concat per event contextual attributes to the ones defined in the experiment configuration and return the result
 * @param {string[]} configuredContextualAttributes
 * @param {string[]} perEventContextualAttributes
 * @returns {string[]}
 */
function prepareContextualAttributes( configuredContextualAttributes, perEventContextualAttributes ) {
	let contextualAttributes = configuredContextualAttributes || [];
	if ( perEventContextualAttributes && perEventContextualAttributes.length > 0 ) {
		contextualAttributes = [ ...new Set(
			contextualAttributes.concat( perEventContextualAttributes )
		) ];
	}

	return contextualAttributes;
}

module.exports = {
	Experiment,
	UnenrolledExperiment,
	OverriddenExperiment
};
