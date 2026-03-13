const EXPOSURE_CONTEXTUAL_ATTRIBUTES = [
	'performer_is_logged_in',
	'performer_is_temp',
	'performer_is_bot',
	'mediawiki_database'
];

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
	 * @param {mw.testKitchen.EventSenderInterface} eventSender
	 * @param {string} eventIntakeServiceUrl
	 * @param {Object.<string,mw.testKitchen.PartialExperimentConfig>} experimentConfigs
	 * @param {mw.testKitchen.ExperimentConfig} config
	 */
	constructor(
		eventFactory,
		eventSender,
		eventIntakeServiceUrl,
		experimentConfigs,
		config
	) {
		this.eventFactory = eventFactory;
		this.eventSender = eventSender;
		this.eventIntakeServiceUrl = eventIntakeServiceUrl;
		this.experimentConfigs = experimentConfigs;
		this.config = config;
		this.streamName = config.stream_name;
		this.schemaID = config.schema_id;
		this.contextualAttributes = config.contextual_attributes;
	}

	getAssignedGroup() {
		return this.config.assigned;
	}

	isAssignedGroup( ...groups ) {
		return groups.includes( this.config.assigned );
	}

	send( action, interactionData, contextualAttributes ) {
		// Extract SDK-specific experiment config
		const keys = [ 'enrolled', 'assigned', 'subject_id', 'sampling_unit', 'coordinator' ];
		const experiment = {};

		for ( const key of keys ) {
			experiment[ key ] = this.config[ key ];
		}

		interactionData = Object.assign(
			{},
			interactionData,
			{ experiment }
		);

		// if present, per-event contextual attributes will be added
		let eventContextualAttributes = this.contextualAttributes;
		if ( contextualAttributes && contextualAttributes.length > 0 ) {
			eventContextualAttributes = contextualAttributes.concat( this.contextualAttributes );
		}

		const event = this.eventFactory.newEvent(
			this.streamName,
			this.schemaID,
			eventContextualAttributes,
			action,
			interactionData
		);

		this.eventSender.sendEvent( event, this.eventIntakeServiceUrl );
	}

	submitInteraction( action, interactionData ) {
		this.send( action, interactionData );
	}

	sendExposure() {
		this.send( 'experiment_exposure', {}, EXPOSURE_CONTEXTUAL_ATTRIBUTES );
	}

	setStream( streamName ) {
		this.streamName = streamName;

		// Use the set of contextual attributes for the stream. This can be removed as part of
		// T408186.
		if ( !this.experimentConfigs[ streamName ] ) {

			// eslint-disable-next-line no-console
			console.warn(
				'%s: The stream %s isn\'t registered. Have you added %s to $wgTestKitchenExperimentStreamNames?',
				this.config.enrolled,
				streamName
			);

			this.contextualAttributes = [];
		} else {
			this.contextualAttributes =
				this.experimentConfigs[ streamName ].contextual_attributes;
		}

		return this;
	}

	setSchema( schemaID ) {
		this.schemaID = schemaID;

		return this;
	}
}

mw.log.deprecate(
	Experiment.prototype,
	'setStream',
	Experiment.prototype.setStream,
	'Experiment#setStream is deprecated and will be removed.',
	'mw.testKitchen.Experiment#setStream()'
);

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
	send( action, interactionData, contextualAttributes ) {}

	// eslint-disable-next-line no-unused-vars
	submitInteraction( action, interactionData ) {}

	sendExposure() {}

	// eslint-disable-next-line no-unused-vars
	setStream( streamName ) {
		return this;
	}

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
	 * @param {string} name
	 * @param {string} assigned
	 */
	constructor( name, assigned ) {
		this.name = name;
		this.assigned = assigned;
	}

	getAssignedGroup() {
		return this.assigned;
	}

	isAssignedGroup( ...groups ) {
		return groups.includes( this.assigned );
	}

	// eslint-disable-next-line no-unused-vars
	send( action, interactionData, contextualAttributes ) {
		const message =
			`${ this.name }: The enrollment for this experiment has been overridden. ` +
			'The following event will not be sent:\n';

		// eslint-disable-next-line no-console
		console.log(
			message,
			action,
			JSON.stringify( interactionData, null, 2 )
		);
	}

	submitInteraction( action, interactionData ) {
		this.send( action, interactionData );
	}

	sendExposure() {
		this.send( 'experiment_exposure' );
	}

	// eslint-disable-next-line no-unused-vars
	setSchema( schemaID ) {
		return this;
	}

	// eslint-disable-next-line no-unused-vars
	setStream( streamName ) {
		return this;
	}
}

module.exports = {
	Experiment,
	UnenrolledExperiment,
	OverriddenExperiment
};
