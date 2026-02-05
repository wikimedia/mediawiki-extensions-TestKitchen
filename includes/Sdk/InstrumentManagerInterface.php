<?php

namespace MediaWiki\Extension\TestKitchen\Sdk;

interface InstrumentManagerInterface {
	/**
	 * Get an instrument object
	 *
	 * @param string $instrumentName
	 * @return InstrumentInterface
	 */
	public function getInstrument( string $instrumentName ): InstrumentInterface;
}
