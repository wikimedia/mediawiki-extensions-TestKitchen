<?php

namespace MediaWiki\Extension\TestKitchen\Sdk;

interface InstrumentInterface {

	/**
	 * Sends an interaction event associated with this instrument
	 *
	 * InteractionData can be null in which case the experiment object will
	 * send an event with simply the experiment configuration and action.
	 *
	 * @param string $action
	 * @param array|null $interactionData
	 */
	public function send( string $action, ?array $interactionData = [] ): void;

	/**
	 * Sets the ID of the schema used to validate analytics events sent with
	 *
	 * This method is chainable.
	 *
	 * @param string $schemaID
	 * @return InstrumentInterface
	 */
	public function setSchema( string $schemaID ): InstrumentInterface;
}
