<?php
declare(strict_types=1);

namespace Frenet\Tests;

use Frenet\Api;
use Frenet\Http\Client;
use Frenet\Http\Exception as HttpException;
use Frenet\Http\Response;
use Frenet\Log;
use Frenet\Module;
use Frenet\Quote\Cache;
use Frenet\Quote\Item;
use Frenet\Quote\Request;
use Frenet\Quote\UnavailableException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ApiTest extends TestCase {
	private const SERVICES = '{"ShippingSevicesArray":[{"ServiceCode":"03220","ServiceDescription":"SEDEX","ShippingPrice":"42.50","DeliveryTime":"3"}]}';

	/**
	 * @var \Log
	 */
	private $writer;

	/**
	 * @var \Cache
	 */
	private $store;

	protected function setUp(): void {
		$this->writer = new \Log('frenet.log');
		$this->store = new \Cache();
	}

	public function testPostsTheRequestAsJsonWithTheTokenTheModuleVersionAndTheTimeout(): void {
		$client = new FakeClient([new Response(200, self::SERVICES)]);

		$this->createApi($client, 3, 7)->quote($this->request());

		self::assertCount(1, $client->requests);
		self::assertSame('https://api.frenet.com.br/shipping/quote', $client->requests[0]['url']);
		self::assertSame(json_encode($this->request()->toArray()), $client->requests[0]['body']);
		self::assertSame([
			'Accept'       => 'application/json',
			'Content-Type' => 'application/json',
			'User-Agent'   => 'Frenet-OpenCart3/' . Module::VERSION . ' (OpenCart 3.0.5.1)',
			'token'        => 'abc123'
		], $client->requests[0]['headers']);
		self::assertSame(7, $client->requests[0]['timeout']);
	}

	public function testRetriesATimeoutAndReturnsTheNextAnswer(): void {
		$client = new FakeClient([new HttpException('Operation timed out after 5000 milliseconds', 28), new Response(200, self::SERVICES)]);

		$result = $this->createApi($client, 3)->quote($this->request());

		self::assertCount(2, $client->requests);
		self::assertSame('SEDEX', $result->getServices()[0]->getDescription());
		self::assertSame(['ERROR Quote attempt 1/3 failed: Operation timed out after 5000 milliseconds'], $this->writer->lines);
	}

	public function testRetriesServerErrorsAndInvalidJsonUntilTheAttemptsRunOut(): void {
		$client = new FakeClient([new Response(503, '{"Message":"Service Unavailable"}'), new Response(200, '<html>Bad gateway</html>'), new HttpException('Connection refused', 7)]);

		try {
			$this->createApi($client, 3)->quote($this->request());
			self::fail('Expected the quote to be unavailable');
		} catch (UnavailableException $exception) {
			self::assertSame('The Frenet API gave no usable answer after 3 attempt(s). Last error: Connection refused', $exception->getMessage());
		}

		self::assertCount(3, $client->requests);
		self::assertSame([
			'ERROR Quote attempt 1/3 failed: HTTP 503: Service Unavailable',
			'ERROR Quote attempt 2/3 failed: The Frenet API answered with invalid JSON',
			'ERROR Quote attempt 3/3 failed: Connection refused'
		], $this->writer->lines);
	}

	public function testNeverRetriesAClientError(): void {
		$client = new FakeClient([new Response(401, '{"Message":"Acesso negado!","Timeout":0}'), new Response(200, self::SERVICES)]);

		try {
			$this->createApi($client, 3)->quote($this->request());
			self::fail('Expected the quote to be refused');
		} catch (UnavailableException $exception) {
			self::assertSame(401, $exception->getCode());
			self::assertSame('The Frenet API refused the quote with HTTP 401: Acesso negado! (not retried)', $exception->getMessage());
		}

		self::assertCount(1, $client->requests);
	}

	public function testSendsOnceWhenASingleAttemptIsConfigured(): void {
		$client = new FakeClient([new Response(500, ''), new Response(200, self::SERVICES)]);

		try {
			$this->createApi($client, 1)->quote($this->request());
			self::fail('Expected the quote to be unavailable');
		} catch (UnavailableException $exception) {
			self::assertStringEndsWith('Last error: HTTP 500', $exception->getMessage());
		}

		self::assertCount(1, $client->requests);
	}

	public function testLogsTheRequestAndTheResponseWithoutTheTokenWhenDebugIsOn(): void {
		$client = new FakeClient([new Response(200, self::SERVICES)]);

		$this->createApi($client, 3, 5, true)->quote($this->request());

		self::assertCount(2, $this->writer->lines);
		self::assertStringStartsWith('DEBUG Quote request {"url":"https://api.frenet.com.br/shipping/quote"', $this->writer->lines[0]);
		self::assertStringContainsString('"token":"********"', $this->writer->lines[0]);
		self::assertStringContainsString('"RecipientCEP":"20040020"', $this->writer->lines[0]);
		self::assertStringNotContainsString('abc123', implode("\n", $this->writer->lines));
		self::assertStringStartsWith('DEBUG Quote response {"attempt":1,"status":200', $this->writer->lines[1]);
	}

	public function testQuotesTheMockOverHttp(): void {
		$result = (new Api(new Client(), new Cache($this->store), new Log($this->writer, false), 'abc123', '3.0.5.1', 5, 3, getenv('FRENET_MOCK_URL') . '/shipping/quote'))->quote($this->request());

		self::assertCount(2, $result->getServices());
	}

	public function testServesARepeatedQuoteFromTheCacheWithoutCallingTheApi(): void {
		$client = new FakeClient([new Response(200, self::SERVICES)]);
		$api = $this->createApi($client, 3);

		$api->quote($this->request());
		$result = $api->quote($this->request());

		self::assertCount(1, $client->requests);
		self::assertSame('SEDEX', $result->getServices()[0]->getDescription());
		self::assertSame(42.5, $result->getServices()[0]->getPrice());
	}

	#[DataProvider('provideQuotesAgainWhenTheCartOrThePostcodeChangesCases')]
	public function testQuotesAgainWhenTheCartOrThePostcodeChanges(Request $changed): void {
		$client = new FakeClient([new Response(200, self::SERVICES), new Response(200, self::SERVICES)]);
		$api = $this->createApi($client, 3);

		$api->quote($this->request());
		$api->quote($changed);

		self::assertCount(2, $client->requests);
		self::assertSame(json_encode($changed->toArray()), $client->requests[1]['body']);
	}

	/**
	 * @return iterable<string, array{Request}>
	 */
	public static function provideQuotesAgainWhenTheCartOrThePostcodeChangesCases(): iterable {
		yield 'another postcode' => [new Request('01310100', '01310100', 'BR', 100.0, '', [new Item(1.0, 16.0, 2.0, 11.0, 1, 'CAM-01', 'Camisetas')], '3.0.5.1')];
		yield 'another quantity' => [new Request('01310100', '20040020', 'BR', 200.0, '', [new Item(1.0, 16.0, 2.0, 11.0, 2, 'CAM-01', 'Camisetas')], '3.0.5.1')];
		yield 'another product' => [new Request('01310100', '20040020', 'BR', 100.0, '', [new Item(1.0, 16.0, 2.0, 11.0, 1, 'CAM-02', 'Camisetas')], '3.0.5.1')];
		yield 'another coupon' => [new Request('01310100', '20040020', 'BR', 100.0, 'FRETE10', [new Item(1.0, 16.0, 2.0, 11.0, 1, 'CAM-01', 'Camisetas')], '3.0.5.1')];
	}

	public function testQuotesAgainWithAnotherToken(): void {
		$client = new FakeClient([new Response(200, self::SERVICES), new Response(200, self::SERVICES)]);

		$this->createApi($client, 3)->quote($this->request());
		(new Api($client, new Cache($this->store), new Log($this->writer, false), 'xyz789', '3.0.5.1', 5, 3))->quote($this->request());

		self::assertCount(2, $client->requests);
	}

	#[DataProvider('provideQuotesAgainAfterAFailureCases')]
	public function testQuotesAgainAfterAFailure(HttpException|Response $failure): void {
		$client = new FakeClient([$failure, new Response(200, self::SERVICES)]);
		$api = $this->createApi($client, 1);

		try {
			$api->quote($this->request());
			self::fail('Expected the quote to be unavailable');
		} catch (UnavailableException $exception) {
			self::assertCount(1, $client->requests);
		}

		$result = $api->quote($this->request());

		self::assertCount(2, $client->requests);
		self::assertCount(1, $result->getServices());
	}

	/**
	 * @return iterable<string, array{HttpException|Response}>
	 */
	public static function provideQuotesAgainAfterAFailureCases(): iterable {
		yield 'timeout' => [new HttpException('Operation timed out after 5000 milliseconds', 28)];
		yield 'server error' => [new Response(503, '{"Message":"Service Unavailable"}')];
		yield 'client error' => [new Response(401, '{"Message":"Acesso negado!"}')];
		yield 'invalid JSON' => [new Response(200, '<html>Bad gateway</html>')];
	}

	public function testQuotesAgainAfterAnAnswerWithoutServices(): void {
		$client = new FakeClient([new Response(200, '{"ShippingSevicesArray":[],"Message":"Nenhuma transportadora disponível"}'), new Response(200, self::SERVICES)]);
		$api = $this->createApi($client, 3);

		$empty = $api->quote($this->request());
		$result = $api->quote($this->request());

		self::assertSame([], $empty->getServices());
		self::assertCount(2, $client->requests);
		self::assertCount(1, $result->getServices());
	}

	public function testCachesAnAnswerWithSomeServicesInError(): void {
		$body = '{"ShippingSevicesArray":[{"ServiceCode":"03220","ServiceDescription":"SEDEX","ShippingPrice":"42.50","DeliveryTime":"3"},{"ServiceCode":"04227","ServiceDescription":"Mini Envios","Error":true,"Msg":"Medidas excedidas"}]}';
		$client = new FakeClient([new Response(200, $body)]);
		$api = $this->createApi($client, 3);

		$api->quote($this->request());
		$result = $api->quote($this->request());

		self::assertCount(1, $client->requests);
		self::assertCount(1, $result->getServices());
		self::assertSame('Medidas excedidas', $result->getErrors()[0]['message']);
	}

	public function testQuotesAgainAfterTheCacheExpires(): void {
		$client = new FakeClient([new Response(200, self::SERVICES), new Response(200, self::SERVICES)]);
		$api = $this->createApi($client, 3);

		$api->quote($this->request());

		foreach ($this->store->entries as $key => $entry) {
			$this->store->entries[$key]['expires'] = time();
		}

		$api->quote($this->request());

		self::assertCount(2, $client->requests);
	}

	public function testQuotesTheApiWhenTheCacheCannotBeRead(): void {
		$this->store = new class extends \Cache {
			public function get($key) {
				throw new \ValueError('fread(): Argument #2 ($length) must be greater than 0');
			}
		};
		$client = new FakeClient([new Response(200, self::SERVICES)]);

		$result = $this->createApi($client, 3)->quote($this->request());

		self::assertCount(1, $client->requests);
		self::assertCount(1, $result->getServices());
		self::assertSame(['ERROR Quote cache could not be read, asking the Frenet API: fread(): Argument #2 ($length) must be greater than 0'], $this->writer->lines);
	}

	public function testQuotesTheApiWhenTheCachedAnswerCannotBeParsed(): void {
		$client = new FakeClient([new Response(200, self::SERVICES)]);
		$api = $this->createApi($client, 3);
		$this->store->set((new Cache($this->store))->getKey('abc123', (string)json_encode($this->request()->toArray())), ['expires' => time() + 60, 'body' => '<html>Bad gateway</html>']);

		$result = $api->quote($this->request());

		self::assertCount(1, $client->requests);
		self::assertCount(1, $result->getServices());
		self::assertSame(['ERROR Quote cache could not be read, asking the Frenet API: The Frenet API answered with invalid JSON'], $this->writer->lines);
	}

	public function testReturnsTheQuoteWhenTheCacheCannotBeWritten(): void {
		$this->store = new class extends \Cache {
			public function set($key, $value) {
				throw new \TypeError('flock(): Argument #1 ($stream) must be of type resource, bool given');
			}
		};
		$client = new FakeClient([new Response(200, self::SERVICES)]);

		$result = $this->createApi($client, 3)->quote($this->request());

		self::assertCount(1, $result->getServices());
		self::assertSame(['ERROR Quote cache could not be written: flock(): Argument #1 ($stream) must be of type resource, bool given'], $this->writer->lines);
	}

	public function testLogsACachedQuoteWithoutTheTokenWhenDebugIsOn(): void {
		$client = new FakeClient([new Response(200, self::SERVICES)]);
		$api = $this->createApi($client, 3, 5, true);

		$api->quote($this->request());
		$api->quote($this->request());

		self::assertCount(3, $this->writer->lines);
		self::assertStringStartsWith('DEBUG Quote served from the cache {"key":"frenet.quote.', $this->writer->lines[2]);
		self::assertStringContainsString('"RecipientCEP":"20040020"', $this->writer->lines[2]);
		self::assertStringNotContainsString('abc123', implode("\n", $this->writer->lines));
	}

	public function testQuotesSeveralRequestsInOneRoundAndKeepsEachResultUnderItsKey(): void {
		$client = new FakeClient([new Response(200, self::SERVICES), new Response(200, self::SERVICES)]);

		$results = $this->createApi($client, 3)->quoteAll(['full' => $this->request(), 1 => $this->request(2)]);

		self::assertSame(['full', 1], array_keys($results));
		self::assertCount(2, $client->requests);
		self::assertSame('SEDEX', $results[1]->getServices()[0]->getDescription());
		self::assertSame([], $this->writer->lines);
	}

	public function testRetriesOnlyTheRequestsThatFailed(): void {
		$client = new FakeClient([new Response(200, self::SERVICES), new Response(503, '{"Message":"Indisponível"}'), new Response(200, self::SERVICES)]);

		$results = $this->createApi($client, 3)->quoteAll(['full' => $this->request(), 1 => $this->request(2)]);

		self::assertCount(3, $client->requests);
		self::assertSame($client->requests[1]['body'], $client->requests[2]['body']);
		self::assertSame('SEDEX', $results['full']->getServices()[0]->getDescription());
		self::assertSame('SEDEX', $results[1]->getServices()[0]->getDescription());
		self::assertSame(['ERROR Quote attempt 1/3 failed: HTTP 503: Indisponível (1)'], $this->writer->lines);
	}

	public function testReturnsTheFailureOfEachRequestWithoutStoppingTheOthers(): void {
		$client = new FakeClient([new Response(401, '{"Message":"Acesso negado!"}'), new Response(200, self::SERVICES), new HttpException('Connection refused', 7)]);

		$results = $this->createApi($client, 1)->quoteAll(['full' => $this->request(), 1 => $this->request(2), 2 => $this->request(3)]);

		self::assertCount(3, $client->requests);
		self::assertInstanceOf(UnavailableException::class, $results['full']);
		self::assertSame(401, $results['full']->getCode());
		self::assertSame('SEDEX', $results[1]->getServices()[0]->getDescription());
		self::assertInstanceOf(UnavailableException::class, $results[2]);
		self::assertStringContainsString('Last error: Connection refused', $results[2]->getMessage());
	}

	public function testSendsOnlyTheRequestsMissingFromTheCache(): void {
		$this->createApi(new FakeClient([new Response(200, self::SERVICES)]), 1)->quote($this->request(2));
		$client = new FakeClient([new Response(200, self::SERVICES)]);

		$results = $this->createApi($client, 1)->quoteAll(['full' => $this->request(), 1 => $this->request(2)]);

		self::assertCount(1, $client->requests);
		self::assertSame(1, json_decode($client->requests[0]['body'], true)['ShippingItemArray'][0]['Quantity']);
		self::assertSame('SEDEX', $results[1]->getServices()[0]->getDescription());
	}

	/**
	 * @param FakeClient $client
	 * @param int $attempts
	 * @param int $timeout
	 * @param bool $debug
	 * @return Api
	 */
	private function createApi(FakeClient $client, int $attempts, int $timeout = 5, bool $debug = false): Api {
		return new Api($client, new Cache($this->store), new Log($this->writer, $debug), 'abc123', '3.0.5.1', $timeout, $attempts);
	}

	/**
	 * @param int $quantity
	 * @return Request
	 */
	private function request(int $quantity = 1): Request {
		return new Request('01310100', '20040020', 'BR', 100.0, '', [new Item(1.0, 16.0, 2.0, 11.0, $quantity, 'CAM-01', 'Camisetas')], '3.0.5.1');
	}
}
