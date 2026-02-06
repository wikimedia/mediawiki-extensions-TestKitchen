<?php

namespace MediaWiki\Extension\TestKitchen\Sdk;

use MediaWiki\User\UserIdentity;
use Wikimedia\Assert\ParameterAssertionException;

/**
 * Developers should use the Test Kitchen Experiment Coordinator to trigger experiment enrollment.
 *
 * Since the Test Kitchen SDK is initialized early in the MediaWiki app lifecycle, it might be initialized with the
 * incorrect identifier for certain identifier types. For example, if the user is creating a new account and their
 * central account hasn't been created yet, the SDK will be initialized with an invalid `mw-user` identifier and it
 * should be updated in the `LocalUserCreated` hook.
 */
interface ExperimentCoordinatorInterface {
	public const IDENTIFIER_TYPE_MW_USER = 'mw-user';

	/**
	 * Updates the `mw-user` identifier and triggers enrollment for experiments that use it.
	 *
	 * When enrollment is triggered, the experiment coordinator will enroll the user in all active experiments that use
	 * the identifier that was updated. If any of the enrollments for those experiments have changed and the
	 * `BeforePageDisplay` hook hasn't run, then the following will also be updated:
	 *
	 * * The SDKs (see {@link ExperimentManager::getExperiment()} and `mw.testKitchen.getExperiment()`)
	 * * The CSS classes added to the `<body>` element (see {@link EnrollmentCssClassSerializer})
	 * * The global logging context
	 *
	 * Note well that Test Kitchen is still considered to be the coordinator for those experiments. You should expect
	 * * the `experiment.coordinator` field to be `"default"` for analytics events sent for those experiments.
	 *
	 * @param UserIdentity $user
	 * @param bool $lookupCentralID Whether to look up the central ID for the user
	 */
	public function updateUser( UserIdentity $user, bool $lookupCentralID = true ): void;

	/**
	 * Updates the identifier and triggers enrollment for experiments that use it.
	 *
	 * When enrollment is triggered, the experiment coordinator will enroll the user in all active experiments that use
	 * the identifier that was updated. If any of the enrollments for those experiments have changed and the
	 * `BeforePageDisplay` hook hasn't run, then the following will also be updated:
	 *
	 * * The SDKs (see {@link ExperimentManager::getExperiment()} and `mw.testKitchen.getExperiment()`)
	 * * The CSS classes added to the `<body>` element (see {@link EnrollmentCssClassSerializer})
	 * * The global logging context
	 *
	 * Note well that Test Kitchen is still considered to be the coordinator for those experiments. You should expect
	 * the `experiment.coordinator` field to be `"default"` for analytics events sent for those experiments.
	 *
	 * @param string $identifierType
	 * @param string $identifier
	 * @throws ParameterAssertionException If the identifier type isn't {@link IDENTIFIER_TYPE_MW_USER}
	 */
	public function updateIdentifier( string $identifierType, string $identifier ): void;
}
