<?php
declare(strict_types=1);

namespace Frenet\Tests\Quote;

use Frenet\Quote\Exception;
use Frenet\Quote\Parser;
use Frenet\Quote\Service;
use PHPUnit\Framework\TestCase;

class ParserTest extends TestCase {
	public function testReadsEveryServiceOfTheResponse(): void {
		$services = (new Parser())->parse((string)file_get_contents(dirname(__DIR__, 2) . '/mock/fixtures/quote.json'));

		static::assertSame(['03298', '03220'], $this->codes($services));
		static::assertSame('PAC', $services[0]->getDescription());
		static::assertSame(25.9, $services[0]->getPrice());
		static::assertSame(7, $services[0]->getDeliveryTime());
	}

	public function testReadsAResponseWithASingleService(): void {
		$services = (new Parser())->parse((string)file_get_contents(dirname(__DIR__, 2) . '/mock/fixtures/quote-single.json'));

		static::assertSame(['03220'], $this->codes($services));
	}

	public function testReadsASingleServiceSentAsAnObjectInsteadOfAList(): void {
		$services = (new Parser())->parse('{"ShippingSevicesArray":{"ServiceCode":"03220","ServiceDescription":"SEDEX","ShippingPrice":"42.50","DeliveryTime":"3"}}');

		static::assertSame(['03220'], $this->codes($services));
	}

	public function testSkipsServicesThatCannotBeOffered(): void {
		$services = (new Parser())->parse((string)json_encode(['ShippingSevicesArray' => [
			['ServiceCode' => '03298', 'ServiceDescription' => 'PAC', 'ShippingPrice' => '25.90', 'DeliveryTime' => '7', 'Error' => true, 'Msg' => 'CEP fora da área'],
			['ServiceDescription' => 'Sem código', 'ShippingPrice' => '10.00'],
			['ServiceCode' => '', 'ShippingPrice' => '10.00'],
			['ServiceCode' => 'JADLOG', 'ServiceDescription' => 'Jadlog'],
			'invalid',
			['ServiceCode' => '03220', 'ServiceDescription' => 'SEDEX', 'ShippingPrice' => '42,50', 'Error' => false]
		]]));

		static::assertSame(['03220'], $this->codes($services));
		static::assertSame(42.5, $services[0]->getPrice());
		static::assertSame(0, $services[0]->getDeliveryTime());
	}

	public function testReturnsNoServiceWhenTheResponseHasNone(): void {
		static::assertSame([], (new Parser())->parse('{"ShippingSevicesArray":[]}'));
		static::assertSame([], (new Parser())->parse('{"Message":"Nenhuma transportadora"}'));
		static::assertSame([], (new Parser())->parse('{"ShippingSevicesArray":null}'));
	}

	public function testRefusesABodyThatIsNotJson(): void {
		$this->expectException(Exception::class);

		(new Parser())->parse('<html>Bad gateway</html>');
	}

	/**
	 * @param Service[] $services
	 * @return string[]
	 */
	private function codes(array $services): array {
		return array_map(static function (Service $service): string {
			return $service->getCode();
		}, $services);
	}
}
