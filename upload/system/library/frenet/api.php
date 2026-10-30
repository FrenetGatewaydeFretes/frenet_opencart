<?php
declare(strict_types=1);

namespace Frenet;

use Frenet\Http\Client;
use Frenet\Http\Exception as HttpException;
use Frenet\Http\Response;
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
	 * @param Log $log
	 * @param string $token
	 * @param string $opencart_version
	 * @param int $timeout
	 * @param int $attempts
	 * @param string $quote_url
	 */
	public function __construct(Client $client, Log $log, string $token, string $opencart_version, int $timeout, int $attempts, string $quote_url = self::QUOTE_URL) {
		$this->client = $client;
		$this->log = $log;
		$this->token = $token;
		$this->opencart_version = $opencart_version;
		$this->timeout = $timeout;
		$this->attempts = max(1, $attempts);
		$this->quote_url = $quote_url;
	}

	/**
	 * Quotes the cart, sending it again after a failure other than a 4xx until the attempts run out.
	 *
	 * @param Request $request
	 * @return Result
	 * @throws UnavailableException
	 */
	public function quote(Request $request): Result {
		$payload = $request->toArray();
		$body = json_encode($payload);

		if ($body === false) {
			throw new UnavailableException('Could not encode the quote request: ' . json_last_error_msg());
		}

		$headers = [
			'Accept'       => 'application/json',
			'Content-Type' => 'application/json',
			'User-Agent'   => 'Frenet-' . Request::PLATFORM_NAME . '/' . Module::VERSION . ' (OpenCart ' . $this->opencart_version . ')',
			'token'        => $this->token
		];

		$this->log->debug('Quote request', ['url' => $this->quote_url, 'headers' => $headers, 'body' => $payload]);

		$error = '';

		for ($attempt = 1; $attempt <= $this->attempts; $attempt++) {
			if ($attempt > 1) {
				usleep(self::RETRY_DELAY);
			}

			try {
				$response = $this->client->post($this->quote_url, $body, $headers, $this->timeout);
			} catch (HttpException $exception) {
				$error = $exception->getMessage();
				$this->logAttempt($attempt, $error);

				continue;
			}

			$this->log->debug('Quote response', ['attempt' => $attempt, 'status' => $response->getStatusCode(), 'body' => $response->getBody()]);

			if ($response->isClientError()) {
				throw new UnavailableException('The Frenet API refused the quote with ' . $this->describe($response) . ' (not retried)', $response->getStatusCode());
			}

			if (!$response->isSuccessful()) {
				$error = $this->describe($response);
				$this->logAttempt($attempt, $error);

				continue;
			}

			try {
				return (new Parser())->parse($response->getBody());
			} catch (Exception $exception) {
				$error = $exception->getMessage();
				$this->logAttempt($attempt, $error);
			}
		}

		throw new UnavailableException('The Frenet API gave no usable answer after ' . $this->attempts . ' attempt(s). Last error: ' . $error);
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
