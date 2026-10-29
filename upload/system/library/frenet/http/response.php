<?php
declare(strict_types=1);

namespace Frenet\Http;

/**
 * Raw HTTP answer, kept apart from any Frenet payload parsing so the transport stays reusable.
 */
class Response {
	/**
	 * @var int
	 */
	private $status_code;

	/**
	 * @var string
	 */
	private $body;

	/**
	 * @param int $status_code
	 * @param string $body
	 */
	public function __construct(int $status_code, string $body) {
		$this->status_code = $status_code;
		$this->body = $body;
	}

	/**
	 * @return int
	 */
	public function getStatusCode(): int {
		return $this->status_code;
	}

	/**
	 * @return string
	 */
	public function getBody(): string {
		return $this->body;
	}

	/**
	 * True for any 2xx status.
	 *
	 * @return bool
	 */
	public function isSuccessful(): bool {
		return $this->status_code >= 200 && $this->status_code < 300;
	}

	/**
	 * True for 4xx statuses, which repeating the same request cannot fix.
	 *
	 * @return bool
	 */
	public function isClientError(): bool {
		return $this->status_code >= 400 && $this->status_code < 500;
	}
}
