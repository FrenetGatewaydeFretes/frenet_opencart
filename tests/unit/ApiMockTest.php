<?php
declare(strict_types=1);

namespace Frenet\Tests;

use Frenet\Api;
use Frenet\Http\Client;
use Frenet\Log;
use Frenet\Quote\Cache;
use Frenet\Quote\Item;
use Frenet\Quote\Request;
use Frenet\Quote\UnavailableException;
use PHPUnit\Framework\TestCase;

final class ApiMockTest extends TestCase {
	/**
	 * @var \Log
	 */
	private $writer;

	protected function setUp(): void {
		$this->writer = new \Log('frenet.log');
	}

	public function testRetriesTheFlakyMockUntilItAnswers(): void {
		@unlink(sys_get_temp_dir() . '/frenet-mock-flaky');
		$first = $this->createApi('flaky')->quote($this->request());
		$second = $this->createApi('flaky')->quote($this->request());

		self::assertCount(2, $first->getServices());
		self::assertCount(2, $second->getServices());
		self::assertCount(2, array_filter($this->writer->lines, static function (string $line): bool {
			return strpos($line, 'HTTP 503: Mock failing every other quote') !== false;
		}));
	}

	public function testReadsTheEmptyAndTheErrorFixturesOfTheMock(): void {
		$empty = $this->createApi('empty')->quote($this->request());
		$errors = $this->createApi('with-errors')->quote($this->request());

		self::assertSame([], $empty->getServices());
		self::assertSame('Nenhuma transportadora disponível para o CEP informado', $empty->getMessage());
		self::assertCount(2, $errors->getServices());
		self::assertSame(0, $errors->getServices()[1]->getDeliveryTime());
		self::assertSame('Erro: Peso excedido.', $errors->getErrors()[0]['message']);
	}

	public function testAnswersByWeightForThePackageSplit(): void {
		$api = $this->createApi('packages');
		$heavy = $api->quote(new Request('01310100', '20040020', 'BR', 100.0, '', [new Item(15.0, 30.0, 4.0, 20.0, 3, 'MBA', '')], '3.0.5.1'));
		$package = $api->quote(new Request('01310100', '20040020', 'BR', 100.0, '', [new Item(15.0, 30.0, 4.0, 20.0, 2, 'MBA', '')], '3.0.5.1'));

		self::assertSame(['Jadlog'], array_map(static function ($service): string {
			return $service->getCarrier();
		}, $heavy->getServices()));
		self::assertSame('Peso excedido', $heavy->getErrors()[0]['message']);
		self::assertSame([40.0, 80.0, 80.0], array_map(static function ($service): float {
			return $service->getPrice();
		}, $package->getServices()));
		self::assertSame(6, $package->getServices()[1]->getDeliveryTime());
	}

	public function testAnswersTooManyRequestsForTheFifteenKiloPackageWhenBusy(): void {
		$api = $this->createApi('packages-busy');

		try {
			$api->quote(new Request('01310100', '20040020', 'BR', 100.0, '', [new Item(15.0, 30.0, 4.0, 20.0, 1, 'MBA', '')], '3.0.5.1'));
			self::fail('Expected the busy mock to refuse the 15 kg package');
		} catch (UnavailableException $exception) {
			self::assertStringContainsString('HTTP 429: Too many requests', $exception->getMessage());
		}

		self::assertCount(3, $api->quote(new Request('01310100', '20040020', 'BR', 100.0, '', [new Item(15.0, 30.0, 4.0, 20.0, 2, 'MBA', '')], '3.0.5.1'))->getServices());
	}

	/**
	 * @param string $token
	 * @return Api
	 */
	private function createApi(string $token): Api {
		return new Api(new Client(), new Cache(new \Cache()), new Log($this->writer, false), $token, '3.0.5.1', 5, 3, getenv('FRENET_MOCK_URL') . '/shipping/quote');
	}

	/**
	 * @return Request
	 */
	private function request(): Request {
		return new Request('01310100', '20040020', 'BR', 100.0, '', [new Item(1.0, 16.0, 2.0, 11.0, 1, 'CAM-01', 'Camisetas')], '3.0.5.1');
	}
}
