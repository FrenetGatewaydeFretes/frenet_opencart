<?php
declare(strict_types=1);

namespace Frenet\Http;

/**
 * Transport failure (DNS, connection, TLS or timeout) where no HTTP answer was received.
 */
class Exception extends \RuntimeException {
	/**
	 * @var int
	 */
	private const CURL_TIMEOUT = 28;

	/**
	 * Whether the request ran out of time, as opposed to failing to connect at all.
	 *
	 * @return bool
	 */
	public function isTimeout(): bool {
		return $this->getCode() === self::CURL_TIMEOUT;
	}
}
