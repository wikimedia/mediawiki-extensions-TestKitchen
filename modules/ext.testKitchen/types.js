/**
 * @typedef {mw.testKitchen.PartialInstrumentConfig} mw.testKitchen.PartialExperimentConfig
 * @property {string} user_identifier_type
 * @property {string} schema_id
 * @property {string} exposure_version
 * @property {string} version
 * @property {number} phase_index The phase index of the experiment
 *
 * @package
 */

/**
 * @typedef {Object} mw.testKitchen.EnrollmentConfig
 * @property {string} enrolled
 * @property {string} assigned
 * @property {string} subject_id
 * @property {boolean} is_override
 * @property {Object<string,string>} other_assigned
 *
 * @package
 */

/**
 * @typedef {Object} mw.testKitchen.ExperimentConfig
 * @property {string} enrolled The machine-readable name of the experiment
 * @property {string} assigned The group assigned to the user
 * @property {string} subject_id The ID assigned to the user when they were enrolled in the
 *  experiment
 * @property {string} sampling_unit The sampling unit used to determine whether the user should
 *  be enrolled in the experiment
 * @property {string} stream_name The name of the stream to send experiment-related analytics
 *  events to
 * @property {string} schema_id The ID of the schema used to validate experiment-related analytics
 *  events with
 * @property {number} phase_index The phase index of the experiment
 * @property {string} version The version of the experiment
 * @property {string[]} contextual_attributes
 * @property {Object<string,string>} other_assigned Enrollment information for all other experiments
 *  that the user is enrolled in expressed as a map of experiment name (enrolled) to group
 *  (assigned). This parameter is used as the value for the `experiment.other_assigned` field in all
 *  experiment-related analytics events. It is defined [here](https://gitlab.wikimedia.org/repos/data-engineering/schemas-event-secondary/-/blame/201ad8db1c4b7a0250646bfaf04817458728d8ed/jsonschema/fragment/analytics/product_metrics/experiment/latest.yaml#L35).
 *
 * @package
 */

// ---

/**
 * @interface EventSenderInterface
 * @memberof mw.testKitchen
 */

/**
 * Sends an analytics event.
 *
 * If the event sender is an experiment, then the event is decorated with experiment-related data
 * before it is sent. The experiment-related data are specified and documented in
 * [the `fragment/analytics/product_metrics/experiment` schema fragment][0].
 *
 * If per-event contextual attributes are passed via the `contextualAttributes` param, then they are
 * added to the event before it is sent.
 *
 * [0]: https://gitlab.wikimedia.org/repos/data-engineering/schemas-event-secondary/-/blob/master/jsonschema/fragment/analytics/product_metrics/experiment/current.yaml?ref_type=heads
 *
 * @method send
 * @instance
 * @memberof mw.testKitchen.EventSenderInterface
 *
 * @param {string} action The action that the user enrolled in this experiment took, e.g.
 *  "hover", "click"
 * @param {Object} [interactionData] Additional data about the action that the user enrolled in
 *  the experiment took
 * @param {string[]} [contextualAttributes] Per-event contextual attributes
 */

// ---

/**
 * An instrumentation module that can be used in the context of both product health measurements,
 * "instruments", or experiments.
 *
 * @typedef {{( mw.testKitchen.EventSenderInterface ):void}} mw.testKitchen.GenericInstrumentation
 */

// ---

/**
 * @interface ExperimentInterface
 * @extends mw.testKitchen.EventSenderInterface
 * @memberof mw.testKitchen
 */

/**
 * Gets the group assigned to the user when they were enrolled in the experiment.
 *
 * @method getAssignedGroup
 * @instance
 * @memberof mw.testKitchen.ExperimentInterface
 *
 * @return {string|null}
 */

/**
 * Gets whether the group assigned to the current user is one of the given groups.
 *
 * @see mw.testKitchen.ExperimentInterface#getAssignedGroup
 *
 * @example
 * const e = mw.testKitchen.getExperiment( 'my-awesome-experiment' );
 *
 * // Is the current user assigned A or B for the "My Awesome Experiment" experiment?
 * if ( e.isAssignedGroup( 'A', 'B' ) {
 *   // ...
 * }
 *
 * @method isAssignedGroup
 * @instance
 * @memberof mw.testKitchen.ExperimentInterface
 *
 * @param {...string} groups
 * @return {boolean}
 */

/**
 * Uses the generic instrumentation to send analytics events for this experiment.
 *
 * This method is chainable.
 *
 * @method use
 * @instance
 * @memberof mw.testKitchen.ExperimentInterface
 *
 * @param {mw.testKitchen.GenericInstrumentation} instrumentation
 * @return {mw.testKitchen.ExperimentInterface}
 */

/**
 * Submits an event related to this experiment.
 *
 * This method makes `Experiment` compatible with [the click-through rate implementation in the
 * `ext.wikimediaEvents.testKitchen` ResourceLoader module][0] by proxying to
 * {@link mw.testKitchen.ExperimentInterface#send}. Calling this outside of Test Kitchen is not
 * supported.
 *
 * If per-event contextual attributes are passed via the `contextualAttributes` param, then they are
 * added to the event before it is sent.
 *
 * [0]: https://gerrit.wikimedia.org/r/plugins/gitiles/mediawiki/extensions/WikimediaEvents/+/master/modules/ext.wikimediaEvents.testKitchen/ClickThroughRateInstrument.js
 *
 * @see https://phabricator.wikimedia.org/T394675
 *
 * @package
 *
 * @method submitInteraction
 * @instance
 * @memberof mw.testKitchen.ExperimentInterface
 *
 * @param {string} action The action related to the submitted event
 * @param {Object} [interactionData] Additional data
 * @param {string[]} [contextualAttributes] Per-event contextual attributes
 */

/**
 * Sends an exposure event for this experiment with {@link mw.testKitchen.ExperimentInterface#send}.
 *
 * The exposure event will always have:
 *
 * 1. `action=experiment_exposure`
 * 2. The following contextual attributes:
 *    * `performer_is_logged_in`
 *    * `performer_is_temp`
 *    * `performer_is_bot`
 *    * `performer_edit_count`
 *    * `mediawiki_database`
 *
 * @method sendExposure
 * @instance
 * @memberof mw.testKitchen.ExperimentInterface
 */

/**
 * Sets the ID of the schema used to validate analytics events sent with
 * {@link mw.testKitchen.ExperimentInterface#send}.
 *
 * This method is chainable.
 *
 * @deprecated
 * @method setSchema
 * @instance
 * @memberof mw.testKitchen.ExperimentInterface
 *
 * @param {string} schemaID
 * @return {mw.testKitchen.ExperimentInterface}
 */

// ---

/**
 * @typedef {Object} mw.testKitchen.InstrumentSamplingConfig
 * @property {string} unit
 * @property {number} rate
 *
 * @package
 */

/**
 * @typedef {Object} mw.testKitchen.PartialInstrumentConfig
 * @property {mw.testKitchen.InstrumentSamplingConfig} sample
 * @property {string} stream_name The name of the stream to send experiment-related analytics
 *  events to
 * @property {string[]} contextual_attributes
 */

/**
 * @typedef {mw.testKitchen.PartialInstrumentConfig} mw.testKitchen.InstrumentConfig
 * @property {string} schema_id The ID of the schema used to validate experiment-related analytics
 *
 * @package
 */

// ---

/**
 * @interface InstrumentInterface
 * @extends mw.testKitchen.EventSenderInterface
 * @memberof mw.testKitchen
 */

/**
 * Uses the generic instrumentation to send analytics events for this instrument.
 *
 * This method is chainable.
 *
 * @method use
 * @instance
 * @memberof mw.testKitchen.InstrumentInterface
 *
 * @param {mw.testKitchen.GenericInstrumentation} instrumentation
 * @return {mw.testKitchen.InstrumentInterface}
 */

/**
 * Sends an analytics event.
 *
 * This method makes `mw.testKitchen.InstrumentInterface` compatible with
 * [the click-through rate implementation in the `ext.wikimediaEvents.testKitchen` ResourceLoader
 * module][0] by proxying to {@link mw.testKitchen.InstrumentInterface#send}. Calling this outside
 * of Test Kitchen is not supported.
 *
 * If per-event contextual attributes are passed via the `contextualAttributes` param, then they are
 * added to the event before it is sent.
 *
 * [0]: https://gerrit.wikimedia.org/r/plugins/gitiles/mediawiki/extensions/WikimediaEvents/+/master/modules/ext.wikimediaEvents.testKitchen/ClickThroughRateInstrument.js
 *
 * @see https://phabricator.wikimedia.org/T394675
 *
 * @package
 *
 * @method submitInteraction
 * @instance
 * @memberof mw.testKitchen.InstrumentInterface
 *
 * @param {string} action The action related to the submitted event
 * @param {Object} [interactionData] Additional data
 * @param {string[]} [contextualAttributes] Per-event contextual attributes
 */

/**
 * Sets the ID of the schema used to validate analytics events sent with
 * {@link mw.testKitchen.InstrumentInterface#send}.
 *
 * This method is chainable.
 *
 * @deprecated
 * @method setSchema
 * @instance
 * @memberof mw.testKitchen.InstrumentInterface
 *
 * @param {string} schemaID
 * @return {mw.testKitchen.InstrumentInterface}
 */

/**
 * Gets whether the instrument is in-sample.
 *
 * @method isInSample
 * @instance
 * @memberof mw.testKitchen.InstrumentInterface
 *
 * @return {boolean}
 */

// ---

/**
 * @typedef {Object} mw.testKitchen.TestEvent
 * @property {string} action
 * @property {Object} interactionData
 * @property {string[]} [contextualAttributes]
 */

// ---

/**
 * @interface mw.testKitchen.StubbedExperimentInterface
 * @extends mw.testKitchen.ExperimentInterface
 */

/**
 * The events that have been sent for the experiment.
 *
 * @constant {mw.testKitchen.TestEvent[]} events
 * @instance
 * @memberof mw.testKitchen.StubbedExperimentInterface
 */

/**
 * The number of events that have been sent for the experiment.
 *
 * @constant {number} eventCount
 * @instance
 * @memberof mw.testKitchen.StubbedExperimentInterface
 */

// ---

/**
 * @interface FakeExperimentsHelper
 * @memberof mw.testKitchen
 */

/**
 * Restores {@link mw.testKitchen.getExperiment} to its original state.
 *
 * This method should be called after every unit test that calls
 * {@link mw.testKitchen.useFakeExperiments}.
 *
 * @method restore
 * @instance
 * @memberof mw.testKitchen.FakeExperimentsHelper
 */

/**
 * Stubs enrollment in an experiment.
 *
 * @example
 * const tk = mw.testKitchen.useFakeExperiments();
 * const e = tk.stubExperiment( 'my-awesome-experiment', 'treatment' );
 *
 * // Run Code Under Test
 *
 * assert.strictEqual( e.eventCount, 1 );
 * assert.strictEqual( e.events[ 0 ].action, 'my-awesome-action' );
 *
 * assert.strictEqual( tk.globalEventCount, 1 );
 *
 * @method stubExperiment
 * @instance
 * @memberof mw.testKitchen.FakeExperimentsHelper
 *
 * @param {string} experimentName
 * @param {string} assigned
 * @return {mw.testKitchen.StubbedExperimentInterface}
 */

/**
 * The number of events that have been sent by all stubbed experiments.
 *
 * @constant {number} mw.testKitchen.FakeExperimentsHelper#globalEventCount
 */

// ---

/**
 * @interface mw.testKitchen.StubbedInstrumentInterface
 * @extends mw.testKitchen.InstrumentInterface
 */

/**
 * The events that have been sent for the experiment.
 *
 * @constant {mw.testKitchen.TestEvent[]} events
 * @instance
 * @memberof mw.testKitchen.StubbedInstrumentInterface
 */

/**
 * The number of events that have been sent for the experiment.
 *
 * @constant {number} eventCount
 * @instance
 * @memberof mw.testKitchen.StubbedInstrumentInterface
 */

// ---

/**
 * @interface FakeInstrumentsHelper
 * @memberof mw.testKitchen
 */

/**
 * Restores {@link mw.testKitchen.getInstrument} to its original state.
 *
 * This method should be called after every unit test that calls
 * {@link mw.testKitchen.useFakeInstruments}.
 *
 * @method restore
 * @instance
 * @memberof mw.testKitchen.FakeInstrumentsHelper
 */

/**
 * Stubs an instrument.
 *
 * @example
 * const tk = mw.testKitchen.useFakeExperiments();
 * const e = tk.stubInstrument( 'my-awesome-instrument' );
 *
 * // Run Code Under Test
 *
 * assert.strictEqual( e.eventCount, 1 );
 * assert.strictEqual( e.events[ 0 ].action, 'my-awesome-action' );
 *
 * assert.strictEqual( tk.globalEventCount, 1 );
 *
 * @method stubInstrument
 * @instance
 * @memberof mw.testKitchen.FakeInstrumentsHelper
 *
 * @param {string} instrumentName
 * @return {mw.testKitchen.StubbedInstrumentInterface}
 */

/**
 * The number of events that have been sent by all stubbed experiments.
 *
 * @constant {number} mw.testKitchen.FakeInstrumentsHelper#globalEventCount
 */
