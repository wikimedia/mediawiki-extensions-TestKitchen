<?php

namespace MediaWiki\Extension\TestKitchen\Coordination;

/**
 * Accumulates information about active experiments and experiment enrollments during experiment
 * enrollment sampling.
 */
class EnrollmentResultBuilder {
	private array $overrides = [];
	private array $enrolled = [];
	private array $assigned = [];
	private array $subjectIDs = [];

	public function addExperiment( string $experimentName, string $subjectID ): void {
		$this->subjectIDs[ $experimentName ] = $subjectID;
	}

	public function addAssignment( string $experimentName, string $groupName, bool $isOverride = false ): void {
		$this->enrolled[ $experimentName ] = true;
		$this->assigned[ $experimentName ] = $groupName;

		if ( $isOverride ) {
			$this->overrides[ $experimentName ] = true;
		}
	}

	/**
	 * Returns information about experiments and experiment enrollments that have been added in a
	 * format that can be used by the JS Test Kitchen SDK and {@link ExperimentManager}. Note that this
	 * provides `subject_ids` values.
	 *
	 * @return array
	 */
	public function build(): array {
		return [
			'overrides' => array_keys( $this->overrides ),
			'enrolled' => array_keys( $this->enrolled ),
			'assigned' => $this->assigned,
			'subject_ids' => $this->subjectIDs
		];
	}

	/**
	 * Returns information about experiments and experiment enrollments that have been added in a
	 * format meant for logging, that is, without `subject_ids` data.
	 *
	 * @return array
	 */
	public function getEnrollmentsWithoutSubjectIds(): array {
		$enrollments = $this->build();
		unset( $enrollments['subject_ids'] );
		return array_filter( $enrollments, 'count' );
	}
}
