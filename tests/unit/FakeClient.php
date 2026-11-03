<?php
declare(strict_types=1);

namespace Frenet\Tests;

use Frenet\Http\Client;
use Frenet\Http\Response;

/**
 * HTTP client that answers from a queue of responses or exceptions and records every request.
 */
class FakeClient extends Client {
	/**
	 * @var array<int, array{url: string, body: string, headers: array<string, string>, timeout: int}>
	 */
	public $requests = [];

	/**
	 * @var array<int, Response|\Throwable>
	 */
	private $answers;

	/**
	 * @param array<int, Response|\Throwable> $answers
	 */
	public function __construct(array $answers) {
		$this->answers = $answers;
	}

	public function post(string $url, string $body, array $headers, int $timeout): Response {
		$this->requests[] = ['url' => $url, 'body' => $body, 'headers' => $headers, 'timeout' => $timeout];

		$answer = array_shift($this->answers);

		if ($answer instanceof \Throwable) {
			throw $answer;
		}

		if ($answer === null) {
			throw new \LogicException('No answer queued for request ' . count($this->requests));
		}

		return $answer;
	}
}
