<?php
declare(strict_types=1);

namespace Frenet\Tests\Quote;

use Frenet\Quote\Service;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ServiceTest extends TestCase {
	#[DataProvider('provideBuildsTheRateTitleCases')]
	public function testBuildsTheRateTitle(string $template, int $delivery_time, int $additional_days, string $expected): void {
		static::assertSame($expected, (new Service('03298', 'PAC', 25.9, $delivery_time))->getTitle($template, $additional_days));
	}

	/**
	 * @return iterable<string, array{0: string, 1: int, 2: int, 3: string}>
	 */
	public static function provideBuildsTheRateTitleCases(): iterable {
		yield 'name and delivery days' => ['%s - Em média %d dia(s)', 7, 0, 'PAC - Em média 7 dia(s)'];
		yield 'additional days added' => ['%s - Em média %d dia(s)', 7, 2, 'PAC - Em média 9 dia(s)'];
		yield 'additional days without the carrier delivery time' => ['%s - Em média %d dia(s)', 0, 2, 'PAC - Em média 2 dia(s)'];
		yield 'no template' => ['', 7, 2, 'PAC'];
		yield 'no delivery days' => ['%s - Em média %d dia(s)', 0, 0, 'PAC'];
		yield 'negative additional days ignored' => ['%s - Em média %d dia(s)', 0, -1, 'PAC'];
		yield 'unexpected placeholders' => ['%s %s %d %f', 7, 0, 'PAC PAC 7 %f'];
	}
}
