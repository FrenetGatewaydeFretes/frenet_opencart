<?php
declare(strict_types=1);

namespace Frenet\Simulator;

use Frenet\Log;

/**
 * Counts the simulator quotes of each IP in the OpenCart cache and blocks the ones above the limit of the admin per minute.
 */
final class RateLimit {
	/**
	 * @var int
	 */
	public const WINDOW = 60;

	/**
	 * @var string
	 */
	private const PREFIX = 'frenet.simulator.';

	/**
	 * @var \Cache
	 */
	private $cache;

	/**
	 * @var Log
	 */
	private $log;

	/**
	 * @var int
	 */
	private $limit;

	/**
	 * @param \Cache $cache
	 * @param Log $log
	 * @param int $limit
	 */
	public function __construct(\Cache $cache, Log $log, int $limit) {
		$this->cache = $cache;
		$this->log = $log;
		$this->limit = $limit;
	}

	/**
	 * Counts a quote of the IP and returns 0 within the limit, otherwise the seconds until the minute of the IP ends.
	 *
	 * @param string $ip
	 * @return int
	 */
	public function hit(string $ip): int {
		if ($this->limit <= 0) {
			return 0;
		}

		$client = self::getClient($ip);
		$key = self::PREFIX . hash('sha256', $client);
		$now = time();

		try {
			$entry = $this->cache->get($key);

			if (!is_array($entry) || (int)($entry['expires'] ?? 0) <= $now) {
				$entry = ['count' => 0, 'expires' => $now + self::WINDOW];
			}

			$count = (int)($entry['count'] ?? 0) + 1;
			$expires = (int)$entry['expires'];

			if ($count <= $this->limit + 1) {
				$this->cache->set($key, ['count' => $count, 'expires' => $expires]);
			}
		} catch (\Throwable $exception) {
			$this->log->error('Simulator quote limit could not be checked, quoting anyway: ' . $exception->getMessage());

			return 0;
		}

		if ($count <= $this->limit) {
			return 0;
		}

		$retry_after = max(1, $expires - $now);

		if ($count === $this->limit + 1) {
			$this->log->error('Simulator quote limit reached (' . $this->limit . ' quotes per ' . self::WINDOW . ' seconds): IP ' . $client . ' blocked for ' . $retry_after . ' seconds');
		}

		return $retry_after;
	}

	/**
	 * The IPv4 address, or the /64 network of an IPv6 address, since one IPv6 client may use every address of its /64.
	 *
	 * @param string $ip
	 * @return string
	 */
	private static function getClient(string $ip): string {
		if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
			return $ip;
		}

		$packed = (string)inet_pton($ip);

		if (substr($packed, 0, 12) === str_repeat("\0", 10) . "\xff\xff") {
			return (string)inet_ntop(substr($packed, 12));
		}

		return (string)inet_ntop(substr($packed, 0, 8) . str_repeat("\0", 8)) . '/64';
	}
}
