<?php
declare(strict_types=1);

namespace Frenet\Tests;

use Frenet\Api;
use Frenet\Http\Client;
use Frenet\Http\Exception as HttpException;
use Frenet\Http\Response;
use Frenet\Log;
use Frenet\Module;
use Frenet\Quote\Item;
use Frenet\Quote\Request;
use Frenet\Quote\UnavailableException;
use PHPUnit\Framework\TestCase;

class ApiTest extends TestCase {
	private const SERVICES = '{"ShippingSevicesArray":[{"ServiceCode":"03220","ServiceDescription":"SEDEX","ShippingPrice":"42.50","DeliveryTime":"3"}]}';

	/**
	 * @var \Log
	 */
	private $writer;

	protected function setUp(): void {
		$this->writer = new \Log('frenet.log');
	}

	public function testPostsTheRequestAsJsonWithTheTokenTheModuleVersionAndTheTimeout(): void {
		$client = new FakeClient([new Response(200, self::SERVICES)]);

		$this->createApi($client, 3, 7)->quote($this->request());

		static::assertCount(1, $client->requests);
		static::assertSame('https://api.frenet.com.br/shipping/quote', $client->requests[0]['url']);
		static::assertSame(json_encode($this->request()->toArray()), $client->requests[0]['body']);
		static::assertSame([
			'Accept'       => 'application/json',
			'Content-Type' => 'application/json',
			'User-Agent'   => 'Frenet-OpenCart3/' . Module::VERSION . ' (OpenCart 3.0.5.1)',
			'token'        => 'abc123'
		], $client->requests[0]['headers']);
		static::assertSame(7, $client->requests[0]['timeout']);
	}

	public function testRetriesATimeoutAndReturnsTheNextAnswer(): void {
		$client = new FakeClient([new HttpException('Operation timed out after 5000 milliseconds', 28), new Response(200, self::SERVICES)]);

		$result = $this->createApi($client, 3)->quote($this->request());

		static::assertCount(2, $client->requests);
		static::assertSame('SEDEX', $result->getServices()[0]->getDescription());
		static::assertSame(['ERROR Quote attempt 1/3 failed: Operation timed out after 5000 milliseconds'], $this->writer->lines);
	}

	public function testRetriesServerErrorsAndInvalidJsonUntilTheAttemptsRunOut(): void {
		$client = new FakeClient([new Response(503, '{"Message":"Service Unavailable"}'), new Response(200, '<html>Bad gateway</html>'), new HttpException('Connection refused', 7)]);

		try {
			$this->createApi($client, 3)->quote($this->request());
			static::fail('Expected the quote to be unavailable');
		} catch (UnavailableException $exception) {
			static::assertSame('The Frenet API gave no usable answer after 3 attempt(s). Last error: Connection refused', $exception->getMessage());
		}

		static::assertCount(3, $client->requests);
		static::assertSame([
			'ERROR Quote attempt 1/3 failed: HTTP 503: Service Unavailable',
			'ERROR Quote attempt 2/3 failed: The Frenet API answered with invalid JSON',
			'ERROR Quote attempt 3/3 failed: Connection refused'
		], $this->writer->lines);
	}

	public function testNeverRetriesAClientError(): void {
		$client = new FakeClient([new Response(401, '{"Message":"Acesso negado!","Timeout":0}'), new Response(200, self::SERVICES)]);

		try {
			$this->createApi($client, 3)->quote($this->request());
			static::fail('Expected the quote to be refused');
		} catch (UnavailableException $exception) {
			static::assertSame(401, $exception->getCode());
			static::assertSame('The Frenet API refused the quote with HTTP 401: Acesso negado! (not retried)', $exception->getMessage());
		}

		static::assertCount(1, $client->requests);
	}

	public function testSendsOnceWhenASingleAttemptIsConfigured(): void {
		$client = new FakeClient([new Response(500, ''), new Response(200, self::SERVICES)]);

		try {
			$this->createApi($client, 1)->quote($this->request());
			static::fail('Expected the quote to be unavailable');
		} catch (UnavailableException $exception) {
			static::assertStringEndsWith('Last error: HTTP 500', $exception->getMessage());
		}

		static::assertCount(1, $client->requests);
	}

	public function testLogsTheRequestAndTheResponseWithoutTheTokenWhenDebugIsOn(): void {
		$client = new FakeClient([new Response(200, self::SERVICES)]);

		$this->createApi($client, 3, 5, true)->quote($this->request());

		static::assertCount(2, $this->writer->lines);
		static::assertStringStartsWith('DEBUG Quote request {"url":"https://api.frenet.com.br/shipping/quote"', $this->writer->lines[0]);
		static::assertStringContainsString('"token":"********"', $this->writer->lines[0]);
		static::assertStringContainsString('"RecipientCEP":"20040020"', $this->writer->lines[0]);
		static::assertStringNotContainsString('abc123', implode("\n", $this->writer->lines));
		static::assertStringStartsWith('DEBUG Quote response {"attempt":1,"status":200', $this->writer->lines[1]);
	}

	public function testQuotesTheMockOverHttp(): void {
		$result = (new Api(new Client(), new Log($this->writer, false), 'abc123', '3.0.5.1', 5, 3, getenv('FRENET_MOCK_URL') . '/shipping/quote'))->quote($this->request());

		static::assertCount(2, $result->getServices());
	}

	/**
	 * @param FakeClient $client
	 * @param int $attempts
	 * @param int $timeout
	 * @param bool $debug
	 * @return Api
	 */
	private function createApi(FakeClient $client, int $attempts, int $timeout = 5, bool $debug = false): Api {
		return new Api($client, new Log($this->writer, $debug), 'abc123', '3.0.5.1', $timeout, $attempts);
	}

	/**
	 * @return Request
	 */
	private function request(): Request {
		return new Request('01310100', '20040020', 'BR', 100.0, '', [new Item(1.0, 16.0, 2.0, 11.0, 1)], '3.0.5.1');
	}
}
