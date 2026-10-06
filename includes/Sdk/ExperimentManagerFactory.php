<?php

namespace MediaWiki\Extension\TestKitchen\Sdk;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\TestKitchen\ConfigsFetcher;
use MediaWiki\Extension\TestKitchen\Coordination\EnrollmentsProcessor;
use MediaWiki\Extension\TestKitchen\Coordination\RequestEnrollmentsProcessor;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerInterface;
use Wikimedia\Stats\StatsFactory;

readonly class ExperimentManagerFactory {
	public function __construct(
		private readonly LoggerInterface $logger,
		private readonly EventSender $eventSender,
		private readonly EventFactory $eventFactory,
		private readonly StatsFactory $statsFactory,
		private readonly EnrollmentsProcessor $enrollmentsProcessor,
		private readonly CentralIdLookup $centralIdLookup,
		private readonly ConfigsFetcher $configsFetcher,
		private readonly ExposureLogTracker $exposureLogTracker
	) {
	}

	/**
	 * Gets an {@link ExperimentManagerInterface} instance for the given user.
	 *
	 * The returned {@link ExperimentManagerInterface} instance evaluates experiment enrollment for the given user and
	 * not the current user. The instance ignores everyone experiment enrollments from Varnish but doesn't ignore
	 * enrollment overrides to ease local development and QA.
	 *
	 * @param UserIdentity $user
	 * @return ExperimentManagerInterface
	 */
	public function newForUser( UserIdentity $user ): ExperimentManagerInterface {
		// Don't process everyone experiment enrollments.
		//
		// The ExperimentManager this method returns is for a user that isn't the current user. The everyone experiment
		// enrollments from Varnish are for the current user so we don't process them.
		$requestEnrollmentsProcessor = new RequestEnrollmentsProcessor( $this->logger, false );

		$result = new ExperimentManager(
			$this->logger,
			$this->eventSender,
			$this->eventFactory,
			$this->statsFactory,
			$requestEnrollmentsProcessor,
			$this->enrollmentsProcessor,
			$this->centralIdLookup,
			$this->configsFetcher,
			$this->exposureLogTracker
		);

		$result->updateUser( $user );

		// TODO: Could the WebRequest object be set during Hooks::onBeforeInitialize?
		$result->setRequest( RequestContext::getMain()->getRequest() );

		return $result;
	}
}
