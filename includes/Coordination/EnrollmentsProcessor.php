<?php

namespace MediaWiki\Extension\TestKitchen\Coordination;

class EnrollmentsProcessor {
	public function __construct(
		private readonly UserSplitterInstrumentation $userSplitterInstrumentation
	) {
	}

	public function process(
		string $identifierType,
		string $identifier,
		array $experiments,
		EnrollmentResultBuilder $result
	): void {
		foreach ( $experiments as $experiment ) {
			$experimentName = $experiment['name'];
			$subjectID = $this->userSplitterInstrumentation->getSubjectId( $identifier, $experimentName );

			$result->addExperiment( $experimentName, $subjectID, $identifierType );

			$groups = $experiment['groups'];
			$userHash = $this->userSplitterInstrumentation->getUserHash( $identifier, $experimentName );

			// Is the user in sample for the experiment?
			$isInSample = $this->userSplitterInstrumentation->isSampled(
				$experiment['sample']['rate'],
				$groups,
				$userHash
			);

			if ( $isInSample ) {
				$result->addAssignment(
					$experimentName,
					// UserSplitterInstrumentation#getBucket() returns null if $buckets ($groups here) is empty.
					// We assert that it's not empty in ConfigsFetcher#processConfigs().

					// @phan-suppress-next-line PhanTypeMismatchArgumentNullable
					$this->userSplitterInstrumentation->getBucket( $groups, $userHash )
				);
			}
		}
	}
}
