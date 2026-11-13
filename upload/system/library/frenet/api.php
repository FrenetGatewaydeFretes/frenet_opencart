<?php
declare(strict_types=1);

namespace Frenet;

use Frenet\Http\Client;
use Frenet\Http\Exception as HttpException;
use Frenet\Http\Response;
use Frenet\Quote\Cache;
use Frenet\Quote\Exception;
use Frenet\Quote\Parser;
use Frenet\Quote\Request;
use Frenet\Quote\Result;
use Frenet\Quote\UnavailableException;

/**
 * Frenet REST API, authenticated by the token generated in the Frenet panel.
 */
class Api {
	/**
	 * @var string
	 */
	public const QUOTE_URL = 'https://api.frenet.com.br/shipping/quote';

	/**
	 * @var int
	 */
	private const RETRY_DELAY = 300000;

	/**
	 * @var Client
	 */
	private $client;

	/**
	 * @var Cache
	 */
	private $cache;

	/**
	 * @var Log
	 */
	private $log;

	/**
	 * @var string
	 */
	private $token;

	/**
	 * @var string
	 */
	private $opencart_version;

	/**
	 * @var int
	 */
	private $timeout;

	/**
	 * @var int
	 */
	private $attempts;

	/**
	 * @var string
	 */
	private $quote_url;

	/**
	 * @param Client $client
	 * @param Cache $cache
	 * @param Log $log
	 * @param string $token
	 * @param string $opencart_version
	 * @param int $timeout
	 * @param int $attempts
	 * @param string $quote_url
	 */
	public function __construct(Client $client, Cache $cache, Log $log, string $token, string $opencart_version, int $timeout, int $attempts, string $quote_url = self::QUOTE_URL) {
		$this->client = $client;
		$this->cache = $cache;
		$this->log = $log;
		$this->token = $token;
		$this->opencart_version = $opencart_version;
		$this->timeout = $timeout;
		$this->attempts = max(1, $attempts);
		$this->quote_url = $quote_url;
	}

	/**
	 * Quotes the cart from the cache or the API, sending it again after a failure other than a refusal (4xx but 408 and 429) until the attempts run out.
	 *
	 * @param Request $request
	 * @return Result
	 * @throws UnavailableException
	 */
	public function quote(Request $request): Result {
		$result = $this->quoteAll([$request])[0];

		if ($result instanceof UnavailableException) {
			throw $result;
		}

		return $result;
	}

	/**
	 * Quotes the requests at the same time, each from the cache or the API, with the attempts of the settings for each one.
	 *
	 * @param array<int|string, Request> $requests
	 * @return array<int|string, Result|UnavailableException>
	 */
	public function quoteAll(array $requests): array {
		$results = [];
		$bodies = [];
		$keys = [];

		foreach ($requests as $index => $request) {
			$payload = $request->toArray();
			$body = json_encode($payload);

			if ($body === false) {
				$results[$index] = new UnavailableException('Could not encode the quote request: ' . json_last_error_msg());

				continue;
			}

			$keys[$index] = $this->cache->getKey($this->token, $body);
			$cached = $this->readCache($keys[$index]);

			if ($cached !== null) {
				$this->log->debug('Quote served from the cache', ['key' => $keys[$index], 'body' => $payload]);
				$results[$index] = $cached;

				continue;
			}

			$bodies[$index] = $body;
			$this->log->debug('Quote request', ['url' => $this->quote_url, 'headers' => $this->getHeaders(), 'body' => $payload]);
		}

		$errors = [];

		for ($attempt = 1; $attempt <= $this->attempts && $bodies; $attempt++) {
			if ($attempt > 1) {
				usleep(self::RETRY_DELAY);
			}

			foreach ($this->client->postAll($this->quote_url, $bodies, $this->getHeaders(), $this->timeout) as $index => $answer) {
				$label = count($requests) > 1 ? ' (' . $index . ')' : '';

				if ($answer instanceof HttpException) {
					$errors[$index] = $answer->getMessage();
					$this->logAttempt($attempt, $errors[$index] . $label);

					continue;
				}

				$this->log->debug('Quote response', ['attempt' => $attempt, 'status' => $answer->getStatusCode(), 'body' => $answer->getBody()]);

				if ($answer->isRefused()) {
					$results[$index] = new UnavailableException('The Frenet API refused the quote with ' . $this->describe($answer) . ' (not retried)', $answer->getStatusCode());
					unset($bodies[$index]);

					continue;
				}

				if (!$answer->isSuccessful()) {
					$errors[$index] = $this->describe($answer);
					$this->logAttempt($attempt, $errors[$index] . $label);

					continue;
				}

				try {
					$result = (new Parser())->parse($answer->getBody());
				} catch (Exception $exception) {
					$errors[$index] = $exception->getMessage();
					$this->logAttempt($attempt, $errors[$index] . $label);

					continue;
				}

				if ($result->getServices()) {
					$this->writeCache($keys[$index], $answer->getBody());
				}

				$results[$index] = $result;
				unset($bodies[$index]);
			}
		}

		foreach (array_keys($bodies) as $index) {
			$results[$index] = new UnavailableException('The Frenet API gave no usable answer after ' . $this->attempts . ' attempt(s). Last error: ' . ($errors[$index] ?? ''));
		}

		$ordered = [];

		foreach (array_keys($requests) as $index) {
			$ordered[$index] = $results[$index];
		}

		return $ordered;
	}

	/**
	 * @return array<string, string>
	 */
	private function getHeaders(): array {
		return [
			'Accept'       => 'application/json',
			'Content-Type' => 'application/json',
			'User-Agent'   => 'Frenet-' . Request::PLATFORM_NAME . '/' . Module::VERSION . ' (OpenCart ' . $this->opencart_version . ')',
			'token'        => $this->token
		];
	}

	/**
	 * Returns the cached answer, treating any cache failure as a miss so the quote still reaches the API.
	 *
	 * @param string $key
	 * @return Result|null
	 */
	private function readCache(string $key): ?Result {
		try {
			$body = $this->cache->get($key);

			return $body !== null ? (new Parser())->parse($body) : null;
		} catch (\Throwable $exception) {
			$this->log->error('Quote cache could not be read, asking the Frenet API: ' . $exception->getMessage());

			return null;
		}
	}

	/**
	 * Stores the answer, logging a cache failure instead of losing a quote the API already answered.
	 *
	 * @param string $key
	 * @param string $body
	 * @return void
	 */
	private function writeCache(string $key, string $body): void {
		try {
			$this->cache->set($key, $body);
		} catch (\Throwable $exception) {
			$this->log->error('Quote cache could not be written: ' . $exception->getMessage());
		}
	}

	/**
	 * @param int $attempt
	 * @param string $error
	 * @return void
	 */
	private function logAttempt(int $attempt, string $error): void {
		$this->log->error('Quote attempt ' . $attempt . '/' . $this->attempts . ' failed: ' . $error);
	}

	/**
	 * @param Response $response
	 * @return string
	 */
	private function describe(Response $response): string {
		$data = json_decode($response->getBody(), true);
		$message = is_array($data) && is_scalar($data['Message'] ?? null) ? (string)$data['Message'] : '';

		return 'HTTP ' . $response->getStatusCode() . ($message !== '' ? ': ' . $message : '');
	}
}
