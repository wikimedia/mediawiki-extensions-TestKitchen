<?php

namespace MediaWiki\Extension\TestKitchen\Coordination;

use MediaWiki\Request\WebRequest;
use Psr\Log\LoggerInterface;

class RequestEnrollmentsProcessor {
	private const EVERYONE_EXPERIMENTS_ENROLLMENTS_HEADER_NAME = 'X-Experiment-Enrollments';

	// The subject IDs for everyone experiments are not sent to the app servers but they are to EventGate, the event
	// intake service. EventGate will check that the experiment.subject_id field is "awaiting" before fetching the
	// subject ID for the experiment. See
	// https://gitlab.wikimedia.org/repos/data-engineering/eventgate-wikimedia/-/blob/11beab8f5f980726a00f2251593d3b8a74a29c15/lib/experiments.js#L70.
	private const EVERYONE_EXPERIMENT_SUBJECT_ID = 'awaiting';
	private const EVERYONE_EXPERIMENT_SAMPLING_UNIT = 'edge-unique';

	private const OVERRIDES_PARAM_NAME = 'mpo';

	private const OVERRIDDEN_EXPERIMENT_SUBJECT_ID = 'overridden';

	// The experiment.sampling_unit field can be one of "mw-user", "edge-unique", or "session" but, because overridden
	// experiments cannot send events, for clarity we can set "overridden" as the value.
	private const OVERRIDDEN_EXPERIMENT_SAMPLING_UNIT = 'overridden';

	public function __construct( private readonly LoggerInterface $logger ) {
	}

	/**
	 * Processes everyone experiment enrollments and enrollment overrides from the request.
	 *
	 * @param WebRequest $request
	 */
	public function process( WebRequest $request ): EnrollmentResultBuilder {
		$result = new EnrollmentResultBuilder();

		$this->getEveryoneExperimentsEnrollments( $request, $result );
		$this->getOverriddenEnrollments( $request, $result );

		return $result;
	}

	private function getEveryoneExperimentsEnrollments(
		WebRequest $request,
		EnrollmentResultBuilder $enrollmentResult
	) {
		$headerValue = $request->getHeader( self::EVERYONE_EXPERIMENTS_ENROLLMENTS_HEADER_NAME ) ?? '';

		if ( !$headerValue ) {
			return;
		}

		$rawEnrollments = explode( ';', rtrim( $headerValue, ';' ) );
		$enrollments = [];

		foreach ( $rawEnrollments as $rawEnrollment ) {
			$enrollment = array_filter( explode( '=', $rawEnrollment ) );

			if ( count( $enrollment ) !== 2 ) {
				$this->logger->error(
					'The X-Experiment-Enrollments header could not be parsed properly. The header is malformed.'
				);

				return;
			}

			// T394761: Experiment and group names must validate against the Varnish config schema
			// See https://gitlab.wikimedia.org/repos/sre/libvmod-wmfuniq/-/blob/3656b05f3f678ed012f45473bbf8054db95f6572/schema/abtests_schema.json
			if ( !preg_match( "/^[A-Za-z0-9][-_.A-Za-z0-9]{7,62}$/", $enrollment[0] ) ) {
				$this->logger->error(
					'The X-Experiment-Enrollments header could not be parsed. The experiment name ' .
					'{experiment_name} is invalid',
					[
						'experiment_name' => $enrollment[0],
					]
				);

				return;
			}

			if ( !preg_match( "/^[A-Za-z0-9][-_.A-Za-z0-9]{0,62}$/", $enrollment[1] ) ) {
				$this->logger->error(
					'The X-Experiment-Enrollments header could not be parsed. The group name {group_name} ' .
					'for experiment {experiment_name} is invalid',
					[
						'group_name' => $enrollment[1],
						'experiment_name' => $enrollment[0],
					]
				);

				return;
			}

			$enrollments[] = $enrollment;
		}

		foreach ( $enrollments as $enrollment ) {
			$enrollmentResult->addExperiment(
				$enrollment[0],
				self::EVERYONE_EXPERIMENT_SUBJECT_ID,
				self::EVERYONE_EXPERIMENT_SAMPLING_UNIT
			);
			$enrollmentResult->addAssignment( $enrollment[0], $enrollment[1] );
		}
	}

	private function getOverriddenEnrollments( WebRequest $request, EnrollmentResultBuilder $enrollmentResult ) {
		$queryValues = $request->getQueryValues();
		$queryValue = $queryValues[self::OVERRIDES_PARAM_NAME] ?? '';

		$cookieValue = $request->getCookie( self::OVERRIDES_PARAM_NAME, null, '' );

		$assignments = array_merge(
			$this->processRawEnrollmentOverrides( $cookieValue ),
			$this->processRawEnrollmentOverrides( $queryValue )
		);

		foreach ( $assignments as $experimentName => $groupName ) {
			$enrollmentResult->addExperiment(
				$experimentName,
				self::OVERRIDDEN_EXPERIMENT_SUBJECT_ID,
				self::OVERRIDDEN_EXPERIMENT_SAMPLING_UNIT );
			$enrollmentResult->addAssignment( $experimentName, $groupName, true );
		}
	}

	/**
	 * Process raw enrollment overrides into a map of overridden experiment name to group name.
	 *
	 * Enrollment overrides are expected to be in the form:
	 *
	 * ```
	 * $experimentName1:$groupName1;$experimentName2:$groupName2;...
	 * ```
	 *
	 * If they aren't, then an error is logged and an empty map is returned.
	 *
	 * @param string $rawEnrollmentOverrides
	 */
	private function processRawEnrollmentOverrides( string $rawEnrollmentOverrides ): array {
		if ( !$rawEnrollmentOverrides ) {
			return [];
		}

		// TODO: Should we limit the number of overrides that we accept?
		$parts = explode( ';', $rawEnrollmentOverrides );

		$result = [];

		foreach ( $parts as $override ) {
			$overrideParts = explode( ':', $override, 2 );

			if ( count( $overrideParts ) !== 2 ) {
				$this->logger->error(
					'The raw enrollment overrides could not be parsed properly. They are malformed.'
				);

				return [];
			}

			$result[$overrideParts[0]] = $overrideParts[1];
		}

		return $result;
	}
}
