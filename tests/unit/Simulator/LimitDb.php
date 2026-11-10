<?php
declare(strict_types=1);

namespace Frenet\Tests\Simulator;

/**
 * Answers the SQL of Frenet\Simulator\RateLimit as MySQL would, keeping the rows of the limit table in memory.
 */
final class LimitDb extends \DB {
	/**
	 * @var array<string, array{count: int, expires: int}>
	 */
	public $rows = [];

	/**
	 * @var \Throwable|null
	 */
	public $failure;

	/**
	 * @var \Throwable|null
	 */
	public $cleanup_failure;

	/**
	 * @var bool
	 */
	public $table_exists = true;

	/**
	 * @var \Throwable|null
	 */
	public $create_failure;

	/**
	 * @var int
	 */
	private $last_insert_id = 0;

	public function __construct() {
		parent::__construct([]);
	}

	/**
	 * @param string $sql
	 * @return \stdClass
	 */
	public function query($sql) {
		$this->queries[] = $sql;

		if ($this->failure) {
			throw $this->failure;
		}

		$rows = [];

		if (strpos($sql, 'CREATE TABLE IF NOT EXISTS `oc_frenet_simulator_limit` (`client` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, ') === 0) {
			if ($this->create_failure) {
				throw $this->create_failure;
			}

			$this->table_exists = true;
		} elseif (!$this->table_exists) {
			throw new \Exception("Error: Table 'opencart.oc_frenet_simulator_limit' doesn't exist<br />Error No: 1146");
		} elseif (preg_match("/^INSERT INTO `oc_frenet_simulator_limit` .* VALUES \\('([0-9a-f]{64})', LAST_INSERT_ID\\(1\\), (\\d+)\\) ON DUPLICATE KEY UPDATE `count` = LAST_INSERT_ID\\(IF\\(`expires` <= (\\d+), 1, `count` \\+ 1\\)\\), `expires` = IF\\(`expires` <= \\3, \\2, `expires`\\)$/", $sql, $match)) {
			$row = $this->rows[$match[1]] ?? null;
			$this->rows[$match[1]] = !$row || $row['expires'] <= (int)$match[3] ? ['count' => 1, 'expires' => (int)$match[2]] : ['count' => $row['count'] + 1, 'expires' => $row['expires']];
			$this->last_insert_id = $this->rows[$match[1]]['count'];
		} elseif ($sql === 'SELECT LAST_INSERT_ID() AS `count`') {
			$rows = [['count' => (string)$this->last_insert_id]];
		} elseif (preg_match("/^SELECT `expires` FROM `oc_frenet_simulator_limit` WHERE `client` = '([0-9a-f]{64})'$/", $sql, $match)) {
			$rows = isset($this->rows[$match[1]]) ? [['expires' => (string)$this->rows[$match[1]]['expires']]] : [];
		} elseif (preg_match("/^SELECT `client` FROM `oc_frenet_simulator_limit` FORCE INDEX \\(PRIMARY\\) WHERE `client` >= '([0-9a-f]{4})' AND `expires` <= (\\d+) LIMIT (\\d+)$/", $sql, $match)) {
			if ($this->cleanup_failure) {
				throw $this->cleanup_failure;
			}

			$keys = array_keys($this->rows);

			sort($keys);

			foreach ($keys as $key) {
				if ($key >= $match[1] && $this->rows[$key]['expires'] <= (int)$match[2] && count($rows) < (int)$match[3]) {
					$rows[] = ['client' => $key];
				}
			}
		} elseif (preg_match("/^DELETE FROM `oc_frenet_simulator_limit` WHERE `client` IN \\('([0-9a-f', ]+)'\\) AND `expires` <= (\\d+)$/", $sql, $match)) {
			foreach (explode("', '", $match[1]) as $key) {
				if (isset($this->rows[$key]) && $this->rows[$key]['expires'] <= (int)$match[2]) {
					unset($this->rows[$key]);
				}
			}
		} else {
			throw new \LogicException('Unexpected SQL: ' . $sql);
		}

		$result = new \stdClass();
		$result->rows = $rows;
		$result->row = $rows[0] ?? [];
		$result->num_rows = count($rows);

		return $result;
	}
}
