<?php
declare(strict_types=1);

namespace Frenet\Tests\Quote;

use Frenet\Quote\Service;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ServiceTest extends TestCase {
	#[DataProvider('provideBuildsTheRateTitleCases')]
	public function testBuildsTheRateTitle(string $template, int $delivery_time, int $additional_days, string $expected): void {
		self::assertSame($expected, (new Service('03298', 'PAC', 25.9, $delivery_time))->getTitle($template, $additional_days));
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

	public function testEscapesTheNameAndTheMessageOfTheFrenetApiForTheHtmlOfTheTitle(): void {
		$service = new Service('JET', 'J&T Standard', 25.9, 2, 'J&T', 'Prazo <b>estendido</b> "x"');

		self::assertSame('J&amp;T Standard - 2 dias (Prazo &lt;b&gt;estendido&lt;/b&gt; &quot;x&quot;)', $service->getTitle('%s - %d dias', 0));
	}

	public function testAddsTheMessageOfTheFrenetApiInParentheses(): void {
		$service = new Service('03298', 'PAC', 25.9, 7, 'Correios', 'Área de risco');

		self::assertSame('PAC - Em média 7 dia(s) (Área de risco)', $service->getTitle('%s - Em média %d dia(s)', 0));
		self::assertSame('PAC (Área de risco)', $service->getTitle('', 0));
		self::assertSame('Área de risco', $service->getMessage());
	}
}
