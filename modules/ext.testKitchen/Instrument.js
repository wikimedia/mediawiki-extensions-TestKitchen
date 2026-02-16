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
	 * @param {mw.testKitchen.InstrumentConfig} config
	 */
	constructor(
		eventFactory,
		eventSender,
		eventIntakeServiceUrl,
		config
	) {
		this.eventFactory = eventFactory;
		this.eventSender = eventSender;
		this.eventIntakeServiceUrl = eventIntakeServiceUrl;
		this.config = config;
		this.schemaID = config.schema_id;
		this.funnelEventSequencePosition = 1;
	}

	send( action, interactionData ) {
		interactionData = Object.assign(
			{},
			interactionData,
			{ funnel_event_sequence_position: this.funnelEventSequencePosition++ }
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

	setSchemaID( schemaID ) {
		return this.setSchema( schemaID );
	}

	isInSample() {
		return true;
	}

	isStreamInSample() {
		return true;
	}

	isEnabled() {
		return true;
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

	// eslint-disable-next-line no-unused-vars
	setSchemaID( schemaID ) {
		return this;
	}

	isInSample() {
		return false;
	}

	isEnabled() {
		return false;
	}

	isStreamInSample() {
		return false;
	}
}

module.exports = { Instrument, UnsampledInstrument };
