const REQUIRED_CONTEXTUAL_ATTRIBUTES = [
	'agent_client_platform',
	'agent_client_platform_family'
];

/**
 * @classdesc This class is used to create events for sending to the Event Platform.
 *
 *  {@link mw.testKitchen.ContextualAttributesFactory} is used to retrieve the values of contextual
 *  attributes from the application. Note well that `ContextualAttributesFactory` is only used when
 *  the first event is created, i.e. if no events are created during the request, then no contextual
 *  attribute values are retrieved.
 *
 *  This class is a combination of methods in the `MetricsClient` class in
 *  [repos/data-engineering/metrics-platform](https://gitlab.wikimedia.org/repos/data-engineering/metrics-platform),
 *  both of which were written and maintained by the authors of this extension.
 *
 * @class
 * @memberof mw.testKitchen
 *
 * @package
 */
class EventFactory {

	/**
	 * @param {mw.testKitchen.ContextualAttributesFactory} contextualAttributesFactory
	 */
	constructor( contextualAttributesFactory ) {
		this.contextualAttributesFactory = contextualAttributesFactory;
		this.domain = mw.config.get( 'wgServerName' );
	}

	/**
	 * Creates a new event to be sent.
	 *
	 * The event will contain the following fields:
	 *
	 * - `action`
	 * - `$schema`
	 * - `meta.domain`
	 * - `meta.stream`
	 * - `dt`, which will be set to the current time in ISO 8601 format (including milliseconds)
	 *
	 * If `$interactionData` is set, then it will be added to the event. If contextual attributes
	 * are requested and they are available, then they will be added to the event.
	 *
	 * @param {string} streamName
	 * @param {string} schemaID
	 * @param {mw.testKitchen.ContextualAttribute[]} contextualAttributes
	 * @param {string} action
	 * @param {Object} interactionData
	 * @return {Object}
	 */
	newEvent(
		streamName,
		schemaID,
		contextualAttributes,
		action,
		interactionData
	) {
		const event = Object.assign(
			{},
			interactionData,
			{
				$schema: schemaID,
				meta: {
					domain: this.domain,
					stream: streamName
				},
				dt: new Date().toISOString()
			},
			{ action }
		);

		this.addContextualAttributes( event, contextualAttributes );

		return event;
	}

	/**
	 * @private
	 *
	 * @param {Object} event
	 * @param {mw.testKitchen.ContextualAttribute[]} requestedContextualAttributes
	 */
	addContextualAttributes( event, requestedContextualAttributes ) {
		requestedContextualAttributes =
			REQUIRED_CONTEXTUAL_ATTRIBUTES.concat( requestedContextualAttributes );

		const contextualAttributes = this.contextualAttributesFactory.newContextualAttributes();

		for ( let i = 0; i < requestedContextualAttributes.length; i++ ) {
			copyAttributeByName( contextualAttributes, event, requestedContextualAttributes[ i ] );
		}
	}
}

/**
 * This method is the same as https://gitlab.wikimedia.org/repos/data-engineering/metrics-platform/-/blob/759ce7203ad50776d1e29b1c0979ef3bb50c6a33/js/src/Context.js#L152.
 * That method was written and maintained by the authors of this extension.
 *
 * @ignore
 *
 * @param {Object} from
 * @param {Object} to
 * @param {string} name
 */
function copyAttributeByName( from, to, name ) {
	const index = name.indexOf( '_' );
	const primaryKey = name.slice( 0, index );
	const secondaryKey = name.slice( index + 1 );

	const value = from[ primaryKey ] ? from[ primaryKey ][ secondaryKey ] : null;

	if ( value === undefined || value === null ) {
		return;
	}

	to[ primaryKey ] = to[ primaryKey ] || {};
	to[ primaryKey ][ secondaryKey ] = value;
}

module.exports = EventFactory;
