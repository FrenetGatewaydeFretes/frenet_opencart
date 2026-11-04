<?php
declare(strict_types=1);

namespace Frenet\Tests\Http;

use Frenet\Http\Client;
use Frenet\Http\Exception;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase {
	public function testSendsTheBodyAndHeaders(): void {
		$response = (new Client())->post($this->url('/mock/echo'), '{"a":1}', ['Content-Type' => 'application/json', 'token' => 'abc'], 5);

		$echo = json_decode($response->getBody(), true);

		self::assertSame(200, $response->getStatusCode());
		self::assertSame('POST', $echo['method']);
		self::assertSame('{"a":1}', $echo['body']);
		self::assertSame('application/json', $echo['headers']['content-type']);
		self::assertSame('abc', $echo['headers']['token']);
	}

	public function testReturnsErrorStatusesInsteadOfThrowing(): void {
		$client = new Client();

		self::assertSame(401, $client->post($this->url('/mock/status/401'), '', [], 5)->getStatusCode());
		self::assertSame(503, $client->post($this->url('/mock/status/503'), '', [], 5)->getStatusCode());
	}

	public function testThrowsATimeoutWhenTheApiIsSlowerThanTheLimit(): void {
		try {
			(new Client())->post($this->url('/mock/delay/3'), '', [], 1);
			self::fail('Expected a timeout');
		} catch (Exception $exception) {
			self::assertTrue($exception->isTimeout());
		}
	}

	public function testThrowsAConnectionErrorWhenNothingListens(): void {
		try {
			(new Client())->post('http://127.0.0.1:1/shipping/quote', '', [], 1);
			self::fail('Expected a connection error');
		} catch (Exception $exception) {
			self::assertFalse($exception->isTimeout());
			self::assertNotSame('', $exception->getMessage());
		}
	}

	public function testMockQuoteRejectsRequestsWithoutToken(): void {
		$response = (new Client())->post($this->url('/shipping/quote'), '{}', ['Content-Type' => 'application/json'], 5);

		self::assertSame(401, $response->getStatusCode());
	}

	public function testMockQuoteAnswersWithServicesWhenTheTokenIsSent(): void {
		$response = (new Client())->post($this->url('/shipping/quote'), '{}', ['Content-Type' => 'application/json', 'token' => 'abc'], 5);

		$quote = json_decode($response->getBody(), true);

		self::assertTrue($response->isSuccessful());
		self::assertSame(['03298', '03220'], array_column($quote['ShippingSevicesArray'], 'ServiceCode'));
	}

	/**
	 * @param string $path
	 * @return string
	 */
	private function url(string $path): string {
		return getenv('FRENET_MOCK_URL') . $path;
	}
}
