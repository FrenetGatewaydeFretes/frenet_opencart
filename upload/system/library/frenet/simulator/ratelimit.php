<?php
declare(strict_types=1);

namespace Frenet\Simulator;

use Frenet\Log;

/**
 * Counts the simulator quotes of each IP in a table of its own and blocks the ones above the limit of the admin per minute.
 */
final class RateLimit {
	/**
	 * @var int
	 */
	public const WINDOW = 60;

	/**
	 * @var string
	 */
	public const TABLE = 'frenet_simulator_limit';

	/**
	 * @var int
	 */
	public const CLEANUP_CHANCE = 20;

	/**
	 * @var int
	 */
	public const CLEANUP_ROWS = 100;

	/**
	 * @var \DB
	 */
	private $db;

	/**
	 * @var string
	 */
	private $table;

	/**
	 * @var Log
	 */
	private $log;

	/**
	 * @var int
	 */
	private $limit;

	/**
	 * @var int
	 */
	private $cleanup_chance;

	/**
	 * @param \DB $db
	 * @param string $prefix
	 * @param Log $log
	 * @param int $limit
	 * @param int $cleanup_chance
	 */
	public function __construct(\DB $db, string $prefix, Log $log, int $limit, int $cleanup_chance = self::CLEANUP_CHANCE) {
		$this->db = $db;
		$this->table = '`' . $prefix . self::TABLE . '`';
		$this->log = $log;
		$this->limit = $limit;
		$this->cleanup_chance = $cleanup_chance;
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
		$key = hash('sha256', $client);
		$now = time();
		$expires = $now + self::WINDOW;

		try {
			$this->db->query("INSERT INTO " . $this->table . " (`client`, `count`, `expires`) VALUES ('" . $key . "', LAST_INSERT_ID(1), " . $expires . ") ON DUPLICATE KEY UPDATE `count` = LAST_INSERT_ID(IF(`expires` <= " . $now . ", 1, `count` + 1)), `expires` = IF(`expires` <= " . $now . ", " . $expires . ", `expires`)");

			$count = (int)($this->db->query("SELECT LAST_INSERT_ID() AS `count`")->row['count'] ?? 0);

			if ($count > $this->limit) {
				$expires = (int)($this->db->query("SELECT `expires` FROM " . $this->table . " WHERE `client` = '" . $key . "'")->row['expires'] ?? $expires);
			}
		} catch (\Throwable $exception) {
			$this->log->error('Simulator quote limit could not be checked, quoting anyway: ' . $exception->getMessage());

			return 0;
		}

		if ($count === 1) {
			$this->removeExpired($now);
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
	 * Now and then, when an IP starts a minute, removes a few IPs whose minute ended, so the table keeps only recent ones.
	 *
	 * @param int $now
	 * @return void
	 */
	private function removeExpired(int $now): void {
		if ($this->cleanup_chance < 1 || random_int(1, $this->cleanup_chance) !== 1) {
			return;
		}

		try {
			$this->db->query("DELETE FROM " . $this->table . " WHERE `expires` <= " . $now . " LIMIT " . self::CLEANUP_ROWS);
		} catch (\Throwable $exception) {
			$this->log->error('Simulator quote limits of past minutes could not be removed: ' . $exception->getMessage());
		}
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
