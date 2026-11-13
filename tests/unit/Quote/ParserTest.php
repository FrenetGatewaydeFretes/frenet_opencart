<?php
declare(strict_types=1);

namespace Frenet\Tests\Quote;

use Frenet\Quote\Exception;
use Frenet\Quote\Parser;
use Frenet\Quote\Service;
use PHPUnit\Framework\TestCase;

final class ParserTest extends TestCase {
	public function testReadsEveryServiceOfTheResponse(): void {
		$services = (new Parser())->parse((string)file_get_contents(dirname(__DIR__, 2) . '/mock/fixtures/quote.json'))->getServices();

		self::assertSame(['03298', '03220'], $this->codes($services));
		self::assertSame('PAC', $services[0]->getDescription());
		self::assertSame(25.9, $services[0]->getPrice());
		self::assertSame(7, $services[0]->getDeliveryTime());
		self::assertSame('Correios', $services[0]->getCarrier());
	}

	public function testReadsAServiceWithoutCarrierAsAnEmptyCarrier(): void {
		$services = (new Parser())->parse('{"ShippingSevicesArray":[{"ServiceCode":"JADLOG","ServiceDescription":"Jadlog","ShippingPrice":"30.00","Carrier":null}]}')->getServices();

		self::assertSame('', $services[0]->getCarrier());
	}

	public function testReadsAResponseWithASingleService(): void {
		$services = (new Parser())->parse((string)file_get_contents(dirname(__DIR__, 2) . '/mock/fixtures/quote-single.json'))->getServices();

		self::assertSame(['03220'], $this->codes($services));
	}

	public function testReadsASingleServiceSentAsAnObjectInsteadOfAList(): void {
		$services = (new Parser())->parse('{"ShippingSevicesArray":{"ServiceCode":"03220","ServiceDescription":"SEDEX","ShippingPrice":"42.50","DeliveryTime":"3"}}')->getServices();

		self::assertSame(['03220'], $this->codes($services));
	}

	public function testSkipsServicesThatCannotBeOfferedAndKeepsTheErrorMessages(): void {
		$result = (new Parser())->parse((string)json_encode(['ShippingSevicesArray' => [
			['ServiceCode' => '04227', 'ServiceDescription' => 'Mini Envios', 'Error' => true, 'Msg' => 'Erro: Peso excedido.'],
			['ServiceCode' => '03298', 'ServiceDescription' => 'PAC', 'ShippingPrice' => '25.90', 'DeliveryTime' => '7', 'Error' => true, 'Msg' => 'CEP fora da área'],
			['ServiceDescription' => 'Sem código', 'ShippingPrice' => '10.00'],
			['ServiceCode' => '', 'ShippingPrice' => '10.00'],
			['ServiceCode' => 'JADLOG', 'ServiceDescription' => 'Jadlog'],
			'invalid',
			['ServiceCode' => '03220', 'ServiceDescription' => 'SEDEX', 'ShippingPrice' => '42,50', 'Error' => false]
		]]));

		self::assertSame(['03220'], $this->codes($result->getServices()));
		self::assertSame(42.5, $result->getServices()[0]->getPrice());
		self::assertSame([
			['code' => '04227', 'description' => 'Mini Envios', 'carrier' => '', 'message' => 'Erro: Peso excedido.'],
			['code' => '03298', 'description' => 'PAC', 'carrier' => '', 'message' => 'CEP fora da área']
		], $result->getErrors());
	}

	public function testAServiceWithoutDeliveryTimeNeverInheritsThePreviousOne(): void {
		$services = (new Parser())->parse((string)json_encode(['ShippingSevicesArray' => [
			['ServiceCode' => '03220', 'ServiceDescription' => 'SEDEX', 'ShippingPrice' => '42.50', 'DeliveryTime' => '3'],
			['ServiceCode' => 'F_3', 'ServiceDescription' => 'Jadlog Package', 'ShippingPrice' => '18.06']
		]]))->getServices();

		self::assertSame(3, $services[0]->getDeliveryTime());
		self::assertSame(0, $services[1]->getDeliveryTime());
		self::assertSame('Jadlog Package', $services[1]->getTitle('%s - Em média %d dia(s)', 0));
	}

	public function testReturnsNoServiceWhenTheResponseHasNone(): void {
		$empty = (new Parser())->parse('{"ShippingSevicesArray":[]}');
		$message = (new Parser())->parse('{"Message":"Nenhuma transportadora"}');

		self::assertSame([], $empty->getServices());
		self::assertSame([], $empty->getErrors());
		self::assertSame('', $empty->getMessage());
		self::assertSame([], $message->getServices());
		self::assertSame('Nenhuma transportadora', $message->getMessage());
		self::assertSame([], (new Parser())->parse('{"ShippingSevicesArray":null}')->getServices());
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
