<?php
declare(strict_types=1);

namespace Frenet\Tests;

use Frenet\Api;
use Frenet\Http\Client;
use Frenet\Log;
use Frenet\Quote\Item;
use Frenet\Quote\Request;
use PHPUnit\Framework\TestCase;

class ApiMockTest extends TestCase {
	/**
	 * @var \Log
	 */
	private $writer;

	protected function setUp(): void {
		$this->writer = new \Log('frenet.log');
	}

	public function testRetriesTheFlakyMockUntilItAnswers(): void {
		@unlink(sys_get_temp_dir() . '/frenet-mock-flaky');
		$api = new Api(new Client(), new Log($this->writer, false), 'flaky', '3.0.5.1', 5, 3, getenv('FRENET_MOCK_URL') . '/shipping/quote');

		$first = $api->quote($this->request());
		$second = $api->quote($this->request());

		static::assertCount(2, $first->getServices());
		static::assertCount(2, $second->getServices());
		static::assertCount(2, array_filter($this->writer->lines, static function (string $line): bool {
			return strpos($line, 'HTTP 503: Mock failing every other quote') !== false;
		}));
	}

	public function testReadsTheEmptyAndTheErrorFixturesOfTheMock(): void {
		$url = getenv('FRENET_MOCK_URL') . '/shipping/quote';
		$empty = (new Api(new Client(), new Log($this->writer, false), 'empty', '3.0.5.1', 5, 3, $url))->quote($this->request());
		$errors = (new Api(new Client(), new Log($this->writer, false), 'with-errors', '3.0.5.1', 5, 3, $url))->quote($this->request());

		static::assertSame([], $empty->getServices());
		static::assertSame('Nenhuma transportadora disponível para o CEP informado', $empty->getMessage());
		static::assertCount(2, $errors->getServices());
		static::assertSame(0, $errors->getServices()[1]->getDeliveryTime());
		static::assertSame('Erro: Peso excedido.', $errors->getErrors()[0]['message']);
	}

	/**
	 * @return Request
	 */
	private function request(): Request {
		return new Request('01310100', '20040020', 'BR', 100.0, '', [new Item(1.0, 16.0, 2.0, 11.0, 1)], '3.0.5.1');
	}
}
