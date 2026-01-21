<?php

namespace MediaWiki\Extension\TestKitchen\Sdk;

use Wikimedia\Stats\StatsFactory;

/**
 * Represents an experiment for which the current user hasn't been enrolled
 */
class UnenrolledExperiment extends Experiment {
	private const EMPTY_EXPERIMENT_CONFIG = [];

	public function __construct(
		EventSender $eventSender,
		EventFactory $eventFactory,
		StatsFactory $statsFactory,
		StreamConfigs $streamConfigs
	) {
		parent::__construct(
			$eventSender,
			$eventFactory,
			$statsFactory,
			$streamConfigs,
			self::EMPTY_EXPERIMENT_CONFIG
		);
	}

	/**
	 * @inheritDoc
	 */
	public function send( string $action, ?array $interactionData = null ): void {
	}
}
