<?php
declare(strict_types=1);

namespace Frenet\Tests\Quote;

use Frenet\Api;
use Frenet\Http\Response;
use Frenet\Log;
use Frenet\Quote\Cache;
use Frenet\Quote\Item;
use Frenet\Quote\Request;
use Frenet\Quote\Splitter;
use Frenet\Tests\FakeClient;
use PHPUnit\Framework\TestCase;

final class SplitterTest extends TestCase {
	private const FULL = '{"ShippingSevicesArray":[{"ServiceCode":"03298","ServiceDescription":"PAC","Carrier":"Correios","Error":true,"Msg":"Peso excedido"},{"ServiceCode":"JADLOG","ServiceDescription":"Jadlog","Carrier":"Jadlog","ShippingPrice":"70.00","DeliveryTime":"4"}]}';

	private const PACKAGE_1 = '{"ShippingSevicesArray":[{"ServiceCode":"03298","ServiceDescription":"PAC","Carrier":"Correios","ShippingPrice":"40.00","DeliveryTime":"7"}]}';

	private const PACKAGE_2 = '{"ShippingSevicesArray":[{"ServiceCode":"03298","ServiceDescription":"PAC","Carrier":"Correios","ShippingPrice":"25.50","DeliveryTime":"8"}]}';

	/**
	 * @var \Log
	 */
	private $writer;

	protected function setUp(): void {
		$this->writer = new \Log('frenet.log');
	}

	public function testQuotesACartWithinTheLimitOnce(): void {
		$client = new FakeClient([new Response(200, self::PACKAGE_1)]);

		$result = $this->createSplitter($client)->quote($this->request(15.0, 2));

		self::assertCount(1, $client->requests);
		self::assertSame(40.0, $result->getServices()[0]->getPrice());
	}

	public function testQuotesA45KgCartWholeAndInTwoPackagesAtTheSameTime(): void {
		$client = new FakeClient([new Response(200, self::FULL), new Response(200, self::PACKAGE_1), new Response(200, self::PACKAGE_2)]);

		$result = $this->createSplitter($client)->quote($this->request(15.0, 3));

		$bodies = array_map(static function (array $request): array {
			return json_decode($request['body'], true);
		}, $client->requests);

		self::assertSame([3, 2, 1], array_column(array_column(array_column($bodies, 'ShippingItemArray'), 0), 'Quantity'));
		self::assertSame([270, 180, 90], array_column($bodies, 'ShipmentInvoiceValue'));
		self::assertSame(['PAC', 'Jadlog'], array_map(static function ($service): string {
			return $service->getDescription();
		}, $result->getServices()));
		self::assertSame(65.5, $result->getServices()[0]->getPrice());
		self::assertSame(8, $result->getServices()[0]->getDeliveryTime());
		self::assertSame([], $this->writer->lines);
	}

	public function testQuotesTheCartWholeWhenAUnitIsHeavierThanThePackage(): void {
		$client = new FakeClient([new Response(200, self::FULL)]);

		$result = $this->createSplitter($client)->quote($this->request(45.0, 1));

		self::assertCount(1, $client->requests);
		self::assertSame(['Jadlog'], [$result->getServices()[0]->getDescription()]);
		self::assertSame(['ERROR Cart of 45 kg quoted whole, without Correios split in packages: one unit of SACO weighs 45 kg, more than the package limit of 30 kg'], $this->writer->lines);
	}

	public function testQuotesTheCartWholeWhenItNeedsMoreThanTenPackages(): void {
		$client = new FakeClient([new Response(200, self::FULL)]);

		$this->createSplitter($client)->quote($this->request(25.0, 11));

		self::assertCount(1, $client->requests);
		self::assertSame(['ERROR Cart of 275 kg quoted whole, without Correios split in packages: the cart needs more than 10 packages of up to 30 kg'], $this->writer->lines);
	}

	public function testLogsAPackageThatCouldNotBeQuotedAndLeavesCorreiosOut(): void {
		$client = new FakeClient([new Response(200, self::FULL), new Response(200, self::PACKAGE_1), new Response(400, '{"Message":"Peso inválido"}')]);

		$result = $this->createSplitter($client)->quote($this->request(15.0, 3));

		self::assertSame(['Jadlog'], [$result->getServices()[0]->getDescription()]);
		self::assertCount(1, $result->getServices());
		self::assertSame(['ERROR Package 2 could not be quoted, Correios left out: The Frenet API refused the quote with HTTP 400: Peso inválido (not retried)'], $this->writer->lines);
	}

	public function testLogsTheWholeCartThatCouldNotBeQuotedAndKeepsCorreios(): void {
		$client = new FakeClient([new Response(401, '{"Message":"Acesso negado!"}'), new Response(200, self::PACKAGE_1), new Response(200, self::PACKAGE_2)]);

		$result = $this->createSplitter($client)->quote($this->request(15.0, 3));

		self::assertSame(65.5, $result->getServices()[0]->getPrice());
		self::assertSame(['ERROR Whole cart could not be quoted, other carriers left out: The Frenet API refused the quote with HTTP 401: Acesso negado! (not retried)'], $this->writer->lines);
	}

	public function testLogsWhyTheCartWasNotSplitOnlyInDebugWhenQuiet(): void {
		$client = new FakeClient([new Response(200, self::FULL)]);

		$this->createSplitter($client, true)->quote($this->request(45.0, 1));

		self::assertSame([], $this->writer->lines);
	}

	/**
	 * @param FakeClient $client
	 * @param bool $quiet
	 * @return Splitter
	 */
	private function createSplitter(FakeClient $client, bool $quiet = false): Splitter {
		$log = new Log($this->writer, false);

		return new Splitter(new Api($client, new Cache(new \Cache()), $log, 'abc123', '3.0.5.1', 5, 1), $log, 30.0, $quiet);
	}

	/**
	 * @param float $weight
	 * @param int $quantity
	 * @return Request
	 */
	private function request(float $weight, int $quantity): Request {
		return new Request('01310100', '20040020', 'BR', 90.0 * $quantity, '', [new Item($weight, 40.0, 30.0, 30.0, $quantity, 'SACO', 'Rações', 90.0)], '3.0.5.1');
	}
}
