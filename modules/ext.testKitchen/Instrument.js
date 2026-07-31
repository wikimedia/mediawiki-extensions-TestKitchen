/**
 * @class
 * @implements {mw.testKitchen.InstrumentInterface}
 *
 * @memberof mw.testKitchen
 *
 * @hideconstructor
 */
class Instrument {
	/**
	 * @param {mw.testKitchen.EventFactory} eventFactory
	 * @param {mw.testKitchen.InternalEventSender} internalEventSender
	 * @param {string} eventIntakeServiceUrl
	 * @param {string} name
	 * @param {mw.testKitchen.InstrumentConfig} config
	 */
	constructor(
		eventFactory,
		internalEventSender,
		eventIntakeServiceUrl,
		name,
		config
	) {
		this.eventFactory = eventFactory;
		this.internalEventSender = internalEventSender;
		this.eventIntakeServiceUrl = eventIntakeServiceUrl;
		this.name = name;
		this.config = config;
		this.schemaID = config.schema_id;
		this.funnelEventSequencePosition = 1;
	}

	use( instrumentation ) {
		instrumentation( this );

		return this;
	}

	send( action, interactionData, contextualAttributes ) {
		const event = this.buildEvent( action, interactionData, contextualAttributes );
		this.internalEventSender.sendEvent( event, this.eventIntakeServiceUrl );
	}

	submitInteraction( action, interactionData, contextualAttributes ) {
		this.send( action, interactionData, contextualAttributes );
	}

	setSchema( schemaID ) {
		this.schemaID = schemaID;

		return this;
	}

	isInSample() {
		return true;
	}

	/**
	 * Construct a standard event for all send paths.
	 *
	 * @private
	 * @param {string} action
	 * @param {Object} [interactionData]
	 * @param {string[]} [contextualAttributes]
	 * @return {Object}
	 */
	buildEvent( action, interactionData, contextualAttributes ) {
		interactionData = Object.assign(
			{},
			interactionData,
			{
				instrument_name: this.name,
				funnel_event_sequence_position: this.funnelEventSequencePosition++
			}
		);

		// If present, per-event contextual attributes will be added
		let eventContextualAttributes = this.config.contextual_attributes || [];
		if ( contextualAttributes && contextualAttributes.length > 0 ) {
			eventContextualAttributes = [ ...new Set(
				eventContextualAttributes.concat( contextualAttributes )
			) ];
		}

		return this.eventFactory.newEvent(
			this.config.stream_name,
			this.schemaID,
			eventContextualAttributes,
			action,
			interactionData
		);
	}
}

/**
 * @class
 * @implements {mw.testKitchen.InstrumentInterface}
 *
 * @ignore
 */
class UnsampledInstrument {

	// eslint-disable-next-line no-unused-vars
	use( instrumentation ) {
		return this;
	}

	// eslint-disable-next-line no-unused-vars
	send( action, interactionData, contextualAttributes ) {}

	// eslint-disable-next-line no-unused-vars
	submitInteraction( action, interactionData, contextualAttributes ) {}

	// eslint-disable-next-line no-unused-vars
	setSchema( schemaID ) {
		return this;
	}

	isInSample() {
		return false;
	}
}

module.exports = { Instrument, UnsampledInstrument };
