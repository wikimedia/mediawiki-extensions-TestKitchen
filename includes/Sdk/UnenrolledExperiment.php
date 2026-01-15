<?php

namespace MediaWiki\Extension\TestKitchen\Sdk;

use MediaWiki\Extension\EventLogging\EventSubmitter\EventSubmitter;
use Wikimedia\Stats\StatsFactory;

/**
 * Represents an experiment for which the current user hasn't been enrolled
 */
class UnenrolledExperiment extends Experiment {
	private const EMPTY_EXPERIMENT_CONFIG = [];

	public function __construct(
		EventSubmitter $eventSubmitter,
		EventFactory $eventFactory,
		StatsFactory $statsFactory
	) {
		parent::__construct(
			$eventSubmitter,
			$eventFactory,
			$statsFactory,
			self::EMPTY_EXPERIMENT_CONFIG
		);
	}

	/**
	 * @inheritDoc
	 */
	public function send( string $action, ?array $interactionData = null ): void {
	}
}
