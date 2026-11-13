<?php
declare(strict_types=1);

namespace Frenet\Tests\Quote;

use Frenet\Quote\Matcher;
use Frenet\Quote\Result;
use Frenet\Quote\Service;
use Frenet\Quote\UnavailableException;
use PHPUnit\Framework\TestCase;

final class MatcherTest extends TestCase {
	public function testSumsCorreiosPerServiceAndKeepsTheOtherCarriersOfTheWholeCart(): void {
		$result = Matcher::match([
			'full' => $this->answer([$this->correios('03298', 'PAC', 99.0, 9), $this->service('JADLOG', 'Jadlog', 'Jadlog', 70.0, 4)]),
			1      => $this->answer([$this->correios('03298', 'PAC', 40.0, 7), $this->correios('03220', 'SEDEX', 60.0, 3)]),
			2      => $this->answer([$this->correios('03298', 'PAC', 35.0, 8), $this->correios('03220', 'SEDEX', 55.0, 2)])
		]);

		self::assertSame([['03298', 'PAC', 75.0, 8], ['03220', 'SEDEX', 115.0, 3], ['JADLOG', 'Jadlog', 70.0, 4]], $this->describe($result));
	}

	public function testKeepsTheLongestDeliveryTimeOfThePackages(): void {
		$result = Matcher::match([
			'full' => $this->answer([]),
			1      => $this->answer([$this->correios('03220', 'SEDEX', 60.0, 6)]),
			2      => $this->answer([$this->correios('03220', 'SEDEX', 55.0, 3)])
		]);

		self::assertSame([['03220', 'SEDEX', 115.0, 6]], $this->describe($result));
	}

	public function testLeavesOutACorreiosServiceMissingFromOnePackage(): void {
		$result = Matcher::match([
			'full' => $this->answer([]),
			1      => $this->answer([$this->correios('03298', 'PAC', 80.0, 7)]),
			2      => $this->answer([$this->correios('03298', 'PAC', 80.0, 7)]),
			3      => new Result([], [['code' => '03298', 'description' => 'PAC', 'carrier' => 'Correios', 'message' => 'Peso excedido']], '')
		]);

		self::assertSame([], $result->getServices());
		self::assertSame('Package 3: Peso excedido', $result->getErrors()[0]['message']);
	}

	public function testLeavesOutCorreiosWhenAPackageCouldNotBeQuoted(): void {
		$result = Matcher::match([
			'full' => $this->answer([$this->service('JADLOG', 'Jadlog', 'Jadlog', 70.0, 4)]),
			1      => $this->answer([$this->correios('03298', 'PAC', 80.0, 7)]),
			2      => new UnavailableException('timeout')
		]);

		self::assertSame([['JADLOG', 'Jadlog', 70.0, 4]], $this->describe($result));
	}

	public function testOffersCorreiosWhenOnlyTheWholeCartCouldNotBeQuoted(): void {
		$result = Matcher::match([
			'full' => new UnavailableException('timeout'),
			1      => $this->answer([$this->correios('03298', 'PAC', 40.0, 7)]),
			2      => $this->answer([$this->correios('03298', 'PAC', 35.0, 8)])
		]);

		self::assertSame([['03298', 'PAC', 75.0, 8]], $this->describe($result));
	}

	public function testDropsTheCorreiosErrorsOfTheWholeCartWhichIsOverTheirLimit(): void {
		$full = new Result([], [
			['code' => '03298', 'description' => 'PAC', 'carrier' => 'Correios', 'message' => 'Peso excedido'],
			['code' => 'LOGGI', 'description' => 'Loggi', 'carrier' => 'Loggi', 'message' => 'CEP fora da área']
		], 'mensagem');

		$result = Matcher::match(['full' => $full, 1 => $this->answer([]), 2 => $this->answer([])]);

		self::assertSame(['Loggi'], array_column($result->getErrors(), 'description'));
		self::assertSame('mensagem', $result->getMessage());
	}

	public function testFailsWhenNothingCouldBeQuoted(): void {
		$this->expectException(UnavailableException::class);
		$this->expectExceptionMessage('full down');

		Matcher::match(['full' => new UnavailableException('full down'), 1 => new UnavailableException('package down'), 2 => $this->answer([])]);
	}

	/**
	 * @param Service[] $services
	 * @return Result
	 */
	private function answer(array $services): Result {
		return new Result($services, [], '');
	}

	/**
	 * @param string $code
	 * @param string $description
	 * @param float $price
	 * @param int $days
	 * @return Service
	 */
	private function correios(string $code, string $description, float $price, int $days): Service {
		return $this->service($code, $description, 'Correios', $price, $days);
	}

	/**
	 * @param string $code
	 * @param string $description
	 * @param string $carrier
	 * @param float $price
	 * @param int $days
	 * @return Service
	 */
	private function service(string $code, string $description, string $carrier, float $price, int $days): Service {
		return new Service($code, $description, $price, $days, $carrier);
	}

	/**
	 * @param Result $result
	 * @return array<int, array{0: string, 1: string, 2: float, 3: int}>
	 */
	private function describe(Result $result): array {
		return array_map(static function (Service $service): array {
			return [$service->getCode(), $service->getDescription(), $service->getPrice(), $service->getDeliveryTime()];
		}, $result->getServices());
	}
}
