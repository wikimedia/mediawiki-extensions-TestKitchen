<?php

namespace MediaWiki\Extension\TestKitchen\Coordination;

class EnrollmentHeaderSerializer {
	public static function serialize( array $enrollments ): string {
		if ( !$enrollments || !$enrollments['assigned'] ) {
			return '';
		}

		$result = 'X-Experiment-Enrollments: ';

		foreach ( $enrollments['assigned'] as $experimentName => $groupName ) {
			$result .= "$experimentName=$groupName;";
		}

		return $result;
	}
}
