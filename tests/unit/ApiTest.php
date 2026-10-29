<?php
declare(strict_types=1);

namespace Frenet\Tests;

use Frenet\Api;
use Frenet\Http\Client;
use Frenet\Http\Response;
use Frenet\Module;
use Frenet\Quote\Exception;
use Frenet\Quote\Item;
use Frenet\Quote\Request;
use PHPUnit\Framework\TestCase;

class ApiTest extends TestCase {
	public function testPostsTheRequestAsJsonWithTheTokenAndTheModuleVersion(): void {
		$client = new class() extends Client {
			/**
			 * @var array<string, mixed>
			 */
			public $sent = [];

			public function post(string $url, string $body, array $headers, int $timeout): Response {
				$this->sent = ['url' => $url, 'body' => $body, 'headers' => $headers, 'timeout' => $timeout];

				return new Response(200, '{"ShippingSevicesArray":[]}');
			}
		};

		(new Api($client, 'abc123', '3.0.5.1'))->quote($this->request());

		static::assertSame('https://api.frenet.com.br/shipping/quote', $client->sent['url']);
		static::assertSame(json_encode($this->request()->toArray()), $client->sent['body']);
		static::assertSame([
			'Accept'       => 'application/json',
			'Content-Type' => 'application/json',
			'User-Agent'   => 'Frenet-OpenCart3/' . Module::VERSION . ' (OpenCart 3.0.5.1)',
			'token'        => 'abc123'
		], $client->sent['headers']);
		static::assertSame(5, $client->sent['timeout']);
	}

	public function testReturnsTheServicesOfTheMock(): void {
		$services = (new Api(new Client(), 'abc123', '3.0.5.1', getenv('FRENET_MOCK_URL') . '/shipping/quote'))->quote($this->request());

		static::assertCount(2, $services);
		static::assertSame('SEDEX', $services[1]->getDescription());
	}

	public function testRefusesAnInvalidToken(): void {
		try {
			(new Api(new Client(), '', '3.0.5.1', getenv('FRENET_MOCK_URL') . '/shipping/quote'))->quote($this->request());
			static::fail('Expected the API to refuse the request');
		} catch (Exception $exception) {
			static::assertSame(401, $exception->getCode());
		}
	}

	/**
	 * @return Request
	 */
	private function request(): Request {
		return new Request('01310100', '20040020', 'BR', 100.0, '', [new Item(1.0, 16.0, 2.0, 11.0, 1)], '3.0.5.1');
	}
}
