<?php

namespace MediaWiki\Extension\TestKitchen\Sdk;

use Psr\Log\LoggerInterface;
use Wikimedia\Stats\StatsFactory;

/**
 * Represents an enrollment experiment that has been overridden for the current user
 */
class OverriddenExperiment extends Experiment {

	public function __construct(
		EventSender $eventSender,
		EventFactory $eventFactory,
		StatsFactory $statsFactory,
		StreamConfigs $streamConfigs,
		private readonly LoggerInterface $logger,
		array $experimentConfig,
	) {
		parent::__construct(
			$eventSender,
			$eventFactory,
			$statsFactory,
			$streamConfigs,
			$experimentConfig
		);
	}

	/**
	 * @inheritDoc
	 */
	public function send( string $action, ?array $interactionData = null ): void {
		$experimentName = $this->experimentConfig['enrolled'];

		$this->logger->info(
			$experimentName .
			': The enrolment for this experiment has been overridden. The following event will not be sent',
			[
				'experiment' => $experimentName,
				'action' => $action,
				'interaction_data' => $interactionData,
			]
		);
	}
}
