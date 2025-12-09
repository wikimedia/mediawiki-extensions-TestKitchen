<?php

namespace MediaWiki\Extension\TestKitchen\Tests\Unit\TestKitchen\Coordination;

use Generator;
use MediaWiki\Extension\TestKitchen\Coordination\EnrollmentHeaderSerializer;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\TestKitchen\Coordination\EnrollmentHeaderSerializer
 */
class EnrollmentHeaderSerializerTest extends MediaWikiUnitTestCase {

	public function provideSerialize(): Generator {
		yield [ [], '' ];

		yield [
			[
				'assigned' => [],
			],
			'',
		];

		yield [
			[
				'assigned' => [
					'hello' => 'world',
					'foo' => 'bar',
					'baz' => 'qux',
				]
			],
			'X-Experiment-Enrollments: hello=world;foo=bar;baz=qux;',
		];
	}

	/**
	 * @dataProvider provideSerialize
	 */
	public function testSerialize( $enrollments, $expected ): void {
		$this->assertSame( $expected, EnrollmentHeaderSerializer::serialize( $enrollments ) );
	}
}
