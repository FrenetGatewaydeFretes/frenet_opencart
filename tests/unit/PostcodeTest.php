<?php
declare(strict_types=1);

namespace Frenet\Tests;

use Frenet\Postcode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class PostcodeTest extends TestCase {
	#[TestWith(['01310100'], 'digits only')]
	#[TestWith([' 01310-100 '], 'hyphen and spaces around')]
	#[TestWith(['01.310-100'], 'dot and hyphen')]
	public function testRemovesTheMaskOfThePostcode(string $postcode): void {
		self::assertSame('01310100', Postcode::getDigits($postcode));
	}

	#[TestWith(['01310100'], 'digits only')]
	#[TestWith([' 01310-100 '], 'hyphen and spaces around')]
	#[TestWith(['01.310-100'], 'dot and hyphen')]
	#[TestWith(['01310 100'], 'space')]
	public function testFormatsThePostcode(string $postcode): void {
		self::assertSame('01310-100', Postcode::format($postcode));
	}

	#[TestWith(['0131010'], 'seven digits')]
	#[TestWith(['013101000'], 'nine digits')]
	#[TestWith(['01310-10A'], 'letter')]
	#[TestWith(['CEP 01310100'], 'label')]
	#[TestWith([''], 'empty')]
	public function testRefusesAPostcodeWithoutEightDigits(string $postcode): void {
		self::assertSame('', Postcode::getDigits($postcode));
		self::assertSame('', Postcode::getState($postcode));
		self::assertNull(Postcode::format($postcode));
	}

	#[DataProvider('provideFindsTheStateByTheCorreiosRangeCases')]
	public function testFindsTheStateByTheCorreiosRange(string $postcode, string $state): void {
		self::assertSame($state, Postcode::getState($postcode));
	}

	/**
	 * @return iterable<string, array{string, string}>
	 */
	public static function provideFindsTheStateByTheCorreiosRangeCases(): iterable {
		yield 'first postcode of São Paulo' => ['01000-000', 'SP'];
		yield 'Avenida Paulista' => ['01310-100', 'SP'];
		yield 'last postcode of São Paulo' => ['19999-999', 'SP'];
		yield 'Rio de Janeiro' => ['20040-020', 'RJ'];
		yield 'Belo Horizonte' => ['30130-010', 'MG'];
		yield 'Amapá after Pará' => ['68900-000', 'AP'];
		yield 'Roraima between the ranges of Amazonas' => ['69301-000', 'RR'];
		yield 'interior of Amazonas' => ['69400-000', 'AM'];
		yield 'Brasília' => ['70040-010', 'DF'];
		yield 'surroundings of the Federal District in Goiás' => ['72800-000', 'GO'];
		yield 'satellite city of the Federal District' => ['73000-000', 'DF'];
		yield 'Goiânia' => ['74000-000', 'GO'];
		yield 'Porto Velho' => ['76801-000', 'RO'];
		yield 'former range of Rondônia, now Mato Grosso' => ['78900-000', 'MT'];
		yield 'Porto Alegre' => ['90010-000', 'RS'];
		yield 'last postcode' => ['99999-999', 'RS'];
	}

	public function testFindsNoStateBeforeTheFirstRange(): void {
		self::assertSame('', Postcode::getState('00999-999'));
	}
}
