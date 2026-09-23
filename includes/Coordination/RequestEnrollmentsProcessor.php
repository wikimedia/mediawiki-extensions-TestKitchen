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

	private const OVERRIDES_PARAM_NAME = 'mpo';

	private const OVERRIDDEN_EXPERIMENT_SUBJECT_ID = 'overridden';

	// Overrides will be encoded in the X-Wikimedia-Debug header as follows:
	// X-Wikimedia-Debug: backend=k8s-mwdebug; experiments=experiment1:group1,experiment2:group2
	// The WikimediaDebug browser extension forces a Varnish cache miss so
	// overrides in this header will reach the app server.
	private const WIKIMEDIA_DEBUG_HEADER_NAME = 'X-Wikimedia-Debug';
	private const WIKIMEDIA_DEBUG_EXPERIMENTS_DIRECTIVE = 'experiments';

	public function __construct( private readonly LoggerInterface $logger ) {
	}

	/**
	 * Processes everyone experiment enrollments and enrollment overrides from the request.
	 *
	 * @param WebRequest $request
	 */
	public function process( WebRequest $request, EnrollmentResultBuilder $result ): EnrollmentResultBuilder {
		$this->getEveryoneExperimentsEnrollments( $request, $result );
		$this->getOverriddenEnrollments( $request, $result );
		$this->getWikimediaDebugEnrollments( $request, $result );

		return $result;
	}

	private function getEveryoneExperimentsEnrollments(
		WebRequest $request,
		EnrollmentResultBuilder $enrollmentResult
	): void {
		$headerValue = $request->getHeader( self::EVERYONE_EXPERIMENTS_ENROLLMENTS_HEADER_NAME ) ?? '';

		if ( !$headerValue ) {
			return;
		}

		$assignments = $this->parseEnrollments(
			$headerValue,
			';',
			'=',
			self::EVERYONE_EXPERIMENTS_ENROLLMENTS_HEADER_NAME
		);

		foreach ( $assignments as $experimentName => $groupName ) {
			$enrollmentResult->addExperiment(
				$experimentName,
				self::EVERYONE_EXPERIMENT_SUBJECT_ID
			);
			$enrollmentResult->addAssignment( $experimentName, $groupName );
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
				self::OVERRIDDEN_EXPERIMENT_SUBJECT_ID
			);
			$enrollmentResult->addAssignment( $experimentName, $groupName, true );
		}
	}

	private function getWikimediaDebugEnrollments(
		WebRequest $request,
		EnrollmentResultBuilder $enrollmentResult
	): void {
		$headerValue = $request->getHeader( self::WIKIMEDIA_DEBUG_HEADER_NAME ) ?? '';

		if ( !$headerValue ) {
			return;
		}

		$experimentsValue = null;
		$prefix = self::WIKIMEDIA_DEBUG_EXPERIMENTS_DIRECTIVE . '=';

		foreach ( explode( ';', $headerValue ) as $directive ) {
			if ( str_starts_with( trim( $directive ), $prefix ) ) {
				$experimentsValue = substr( trim( $directive ), strlen( $prefix ) );
				break;
			}
		}

		if ( $experimentsValue === null || $experimentsValue === '' ) {
			return;
		}

		$assignments = $this->parseEnrollments(
			$experimentsValue,
			',',
			':',
			self::WIKIMEDIA_DEBUG_HEADER_NAME
		);

		foreach ( $assignments as $experimentName => $groupName ) {
			$enrollmentResult->addExperiment( $experimentName, self::OVERRIDDEN_EXPERIMENT_SUBJECT_ID );
			$enrollmentResult->addAssignment( $experimentName, $groupName, true );
		}
	}

	/**
	 * Parse raw experiment enrollments string into a map of experiment name to group name.
	 *
	 * Experiment enrollments are expected to be in the form:
	 *
	 * ```
	 * $experimentName1=$groupName1;$experimentName2=$groupName2;...
	 * ```
	 *
	 * @param string $rawEnrollments
	 * @param string $pairSeparator Separator between pairs of experiment name and group name
	 * @param string $keyValueSeparator Separator between experiment name and group name
	 * @param string $headerName The name of the header being parsed
	 * @return array<string,string> Map of experiment name to group name, or empty map if error
	 */
	private function parseEnrollments(
		string $rawEnrollments,
		string $pairSeparator,
		string $keyValueSeparator,
		string $headerName
	): array {
		$assignments = [];

		foreach ( explode( $pairSeparator, rtrim( $rawEnrollments, $pairSeparator ) ) as $rawPair ) {
			$pair = explode( $keyValueSeparator, $rawPair, 2 );

			if ( count( $pair ) !== 2 || $pair[0] === '' || $pair[1] === '' ) {
				$this->logger->error( "The $headerName header could not be parsed. Header is malformed." );
				return [];
			}

			[ $experimentName, $groupName ] = $pair;

			// T394761: Experiment and group names must validate against the Varnish config schema
			// See https://gitlab.wikimedia.org/repos/sre/libvmod-wmfuniq/-/blob/3656b05f3f678ed012f45473bbf8054db95f6572/schema/abtests_schema.json
			if ( !preg_match( "/^[A-Za-z0-9][-_.A-Za-z0-9]{7,62}$/", $experimentName ) ) {
				$this->logger->error( "The $headerName header could not be parsed. The experiment name " .
					'{experiment_name} is invalid', [ 'experiment_name' => $experimentName ] );
				return [];
			}

			if ( !preg_match( "/^[A-Za-z0-9][-_.A-Za-z0-9]{0,62}$/", $groupName ) ) {
				$this->logger->error( "The $headerName header could not be parsed. The group name {group_name} " .
					'for experiment {experiment_name} is invalid',
					[ 'group_name' => $groupName, 'experiment_name' => $experimentName ] );
				return [];
			}

			$assignments[$experimentName] = $groupName;
		}

		return $assignments;
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
