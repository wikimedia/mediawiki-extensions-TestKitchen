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
		interactionData = Object.assign(
			{},
			interactionData,
			{
				instrument_name: this.name,
				funnel_event_sequence_position: this.funnelEventSequencePosition++
			}
		);

		const event = this.eventFactory.newEvent(
			this.config.stream_name,
			this.schemaID,
			this.config.contextual_attributes,
			action,
			interactionData
		);

		this.eventSender.sendEvent( event, this.eventIntakeServiceUrl );
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

	setInstrumentName( name ) {
		this.name = name;
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
	send( action, interactionData ) {
	}

	// eslint-disable-next-line no-unused-vars
	submitInteraction( action, interactionData ) {}

	// eslint-disable-next-line no-unused-vars
	setSchema( schemaID ) {
		return this;
	}

	isInSample() {
		return false;
	}

	// eslint-disable-next-line no-unused-vars
	setInstrumentName( name ) {}
}

module.exports = { Instrument, UnsampledInstrument };
