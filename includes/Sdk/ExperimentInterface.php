<?php

namespace MediaWiki\Extension\TestKitchen\Sdk;

interface ExperimentInterface {

	/**
	 * Gets the group the current user was assigned by the Experiment Enrollment
	 * Sampling Authority (EESA) when they were enrolled in this experiment.
	 *
	 * @return string|null
	 */
	public function getAssignedGroup(): ?string;

	/**
	 *  Gets whether the assigned group for the current user in this experiment
	 *  is one of the given groups.
	 *
	 * @param string ...$groups
	 * @return bool
	 */
	public function isAssignedGroup( string ...$groups ): bool;

	/**
	 * Sends an interaction event associated with this experiment if the EESA
	 * enrolled the current user in this experiment (for logged-in users only).
	 *
	 * In the case no interactionData is passed, the experiment object will
	 * send an event with simply the experiment configuration and action.
	 *
	 * Per-event contextual attributes can be passed as contextualAttributes.
	 * In this case, they will be added to the events along with the ones that
	 * are defined in the experiment config
	 *
	 * @param string $action
	 * @param array $interactionData
	 * @param array $contextualAttributes per event contextual attributes
	 */
	public function send( string $action,
						  array $interactionData = [],
						  array $contextualAttributes = [] ): void;

	/**
	 * Sends an exposure event
	 *
	 * `performer_is_logged_in`, `performer_is_temp`, `performer_is_bot` and `mediawiki_database` contextual attributes
	 * are needed in exposure events, so they will be added if not included already in the stream configuration of the
	 * current experiment
	 */
	public function sendExposure(): void;
}
