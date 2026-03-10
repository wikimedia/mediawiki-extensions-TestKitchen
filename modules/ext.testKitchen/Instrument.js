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
	 * @param {mw.testKitchen.EventSenderInterface} eventSender
	 * @param {string} eventIntakeServiceUrl
	 * @param {string} name
	 * @param {mw.testKitchen.InstrumentConfig} config
	 */
	constructor(
		eventFactory,
		eventSender,
		eventIntakeServiceUrl,
		name,
		config
	) {
		this.eventFactory = eventFactory;
		this.eventSender = eventSender;
		this.eventIntakeServiceUrl = eventIntakeServiceUrl;
		this.name = name;
		this.config = config;
		this.schemaID = config.schema_id;
		this.funnelEventSequencePosition = 1;
	}

	send( action, interactionData ) {
		const event = this.buildEvent( action, interactionData );
		this.eventSender.sendEvent( event, this.eventIntakeServiceUrl );
	}

	sendImmediately( action, interactionData ) {
		const event = this.buildEvent( action, interactionData );

		// T417143 Send events directly to the new path.
		try {
			navigator.sendBeacon( this.eventIntakeServiceUrl, JSON.stringify( event ) );
		} catch ( e ) {
			// Ignoring errors similar to doSendEvents() in eventSender.js.
		}
	}

	submitInteraction( action, interactionData ) {
		this.send( action, interactionData );
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
	 * @return {Object}
	 */
	buildEvent( action, interactionData ) {
		interactionData = Object.assign(
			{},
			interactionData,
			{
				instrument_name: this.name,
				funnel_event_sequence_position: this.funnelEventSequencePosition++
			}
		);
		return this.eventFactory.newEvent(
			this.config.stream_name,
			this.schemaID,
			this.config.contextual_attributes,
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
	send( action, interactionData ) {}

	// eslint-disable-next-line no-unused-vars
	sendImmediately( action, interactionData ) {}

	// eslint-disable-next-line no-unused-vars
	submitInteraction( action, interactionData ) {}

	// eslint-disable-next-line no-unused-vars
	setSchema( schemaID ) {
		return this;
	}

	isInSample() {
		return false;
	}
}

module.exports = { Instrument, UnsampledInstrument };
