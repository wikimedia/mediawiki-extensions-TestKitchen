<?php

namespace MediaWiki\Extension\TestKitchen;

use MediaWiki\Api\Hook\APIAfterExecuteHook;
use MediaWiki\Auth\Hook\AuthPreserveQueryParamsHook;
use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\TestKitchen\Coordination\EnrollmentCssClassSerializer;
use MediaWiki\Extension\TestKitchen\Coordination\EnrollmentHeaderSerializer;
use MediaWiki\Extension\TestKitchen\Sdk\ExperimentManager;
use MediaWiki\Hook\ApiBeforeMainHook;
use MediaWiki\Hook\BeforeInitializeHook;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Request\WebRequest;
use MediaWiki\User\User;
use Wikimedia\Assert\Assert;

class Hooks implements
	AuthPreserveQueryParamsHook,
	BeforeInitializeHook,
	BeforePageDisplayHook,
	ApiBeforeMainHook,
	APIAfterExecuteHook
{
	public const CONSTRUCTOR_OPTIONS = [
		'TestKitchenEnableExperiments',
	];

	public function __construct(
		private readonly Config $config,
		private readonly ExperimentManager $experimentManager
	) {
		Assert::parameter(
			$config->has( 'TestKitchenEnableExperiments' ),
			'$config',
			'Required config "TestKitchenEnableExperiments" missing.'
		);
		Assert::parameter(
			$config->has( 'TestKitchenAuthPreserveQueryParamsExperiments' ),
			'$config',
			'Required config "TestKitchenAuthPreserveQueryParamsExperiments" missing.'
		);
	}

	public function onAuthPreserveQueryParams( array &$params, array $options ) {
		$request = RequestContext::getMain()->getRequest();
		$mpo = $request->getRawVal( 'mpo' );
		if ( $mpo ) {
			$params['mpo'] = $mpo;
			return;
		}
		$experiments = $this->config->get( 'TestKitchenAuthPreserveQueryParamsExperiments' );
		$mpoParams = [];
		foreach ( $experiments as $experimentName ) {
			$experiment = $this->experimentManager->getExperiment( $experimentName );
			$assignedGroup = $experiment?->getAssignedGroup();
			if ( $assignedGroup ) {
				$mpoParams[] = "$experimentName:$assignedGroup";
			}
		}
		if ( $mpoParams ) {
			$params['mpo'] = implode( ';', $mpoParams );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function onBeforeInitialize( $title, $unused, $output, $user, $request, $mediaWikiEntryPoint ) {
		if ( !$this->config->get( 'TestKitchenEnableExperiments' ) ) {
			return;
		}

		$this->doEnrollUser( $user, $request );
	}

	/**
	 * @inheritDoc
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		if ( !$this->config->get( 'TestKitchenEnableExperiments' ) ) {
			return;
		}

		$enrollments = $this->experimentManager->getEnrollments()
			->build();

		// Initialize the JS Test Kitchen SDK
		//
		// Note well that the JS Test Kitchen SDK will always be added to the output.
		// This allows developers to implement and deploy their experiments before they are activated in Test
		// Kitchen without error. It also allows us to handle transient network failures or
		// Test Kitchen API errors gracefully.
		$out->addJsConfigVars( 'wgTestKitchenUserExperiments', $enrollments );
		$out->addModules( 'ext.testKitchen' );

		// T393101: Add CSS classes representing experiment enrollment and assignment automatically so that
		// experiment implementers don't have to do this themselves.
		$out->addBodyClasses( EnrollmentCssClassSerializer::serialize( $enrollments ) );

		// T404262: Add field for A/B test (and control) findability in Logstash
		LoggerFactory::getContext()->add( [ 'context.ab_tests' => $enrollments ] );
	}

	/**
	 * @inheritDoc
	 */
	public function onApiBeforeMain( &$main ) {
		if ( !$this->config->get( 'TestKitchenEnableExperiments' ) ) {
			return;
		}

		$this->doEnrollUser( $main->getUser(), $main->getRequest() );
	}

	/**
	 * @inheritDoc
	 */
	public function onAPIAfterExecute( $module ) {
		if ( !$this->config->get( 'TestKitchenEnableExperiments' ) ) {
			return;
		}

		$enrollments = $this->experimentManager->getEnrollments()
			->build();

		$header = EnrollmentHeaderSerializer::serialize( $enrollments );

		if ( $header ) {
			$module->getRequest()
				->response()
				->header( $header );
		}
	}

	/**
	 * Enrolls the current user in logged-in experiments, gathers enrollments from the upstream everyone experiment
	 * enrollment authority, and handles overridden experiment enrollments.
	 *
	 * @param User $user
	 * @param WebRequest $request
	 */
	private function doEnrollUser( User $user, WebRequest $request ): void {
		$this->experimentManager->setRequest( $request );
		$this->experimentManager->updateUser( $user );
	}
}
