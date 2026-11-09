<?php
declare(strict_types=1);

namespace Frenet\Quote;

/**
 * Keeps successful Frenet answers in the OpenCart cache for a few minutes, keyed by the token and the request body.
 */
class Cache {
	/**
	 * @var int
	 */
	public const TTL = 600;

	/**
	 * @var string
	 */
	private const PREFIX = 'frenet.quote.';

	/**
	 * @var \Cache
	 */
	private $cache;

	/**
	 * @param \Cache $cache
	 */
	public function __construct(\Cache $cache) {
		$this->cache = $cache;
	}

	/**
	 * Builds the cache key, so another account or any change in the cart, address or settings sent asks the API again.
	 *
	 * @param string $token
	 * @param string $body
	 * @return string
	 */
	public function getKey(string $token, string $body): string {
		return self::PREFIX . hash('sha256', $token . "\n" . $body);
	}

	/**
	 * Returns the cached response body while it is younger than TTL, otherwise null.
	 *
	 * @param string $key
	 * @return string|null
	 */
	public function get(string $key): ?string {
		$entry = $this->quietly(function () use ($key) {
			return $this->cache->get($key);
		});

		if (!is_array($entry) || !is_string($entry['body'] ?? null) || (int)($entry['expires'] ?? 0) <= time()) {
			return null;
		}

		return $entry['body'];
	}

	/**
	 * @param string $key
	 * @param string $body
	 * @return void
	 */
	public function set(string $key, string $body): void {
		$this->quietly(function () use ($key, $body) {
			$this->cache->set($key, ['expires' => time() + self::TTL, 'body' => $body]);
		});
	}

	/**
	 * Runs a call to the core cache without its warnings, which the file cache of old OpenCart 3 prints before the JSON.
	 *
	 * @param callable $call
	 * @return mixed
	 */
	private function quietly(callable $call) {
		set_error_handler(static function (): bool {
			return true;
		}, E_WARNING | E_NOTICE);

		try {
			return $call();
		} finally {
			restore_error_handler();
		}
	}
}
