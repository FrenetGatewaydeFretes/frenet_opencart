<?php
declare(strict_types=1);

namespace Frenet\Tests\Simulator;

use Frenet\Log;
use Frenet\Simulator\RateLimit;
use PHPUnit\Framework\TestCase;

final class RateLimitTest extends TestCase {
	private LimitDb $db;

	private \Log $writer;

	protected function setUp(): void {
		$this->db = new LimitDb();
		$this->writer = new \Log(Log::FILENAME);
	}

	public function testAllowsTheQuotesUpToTheLimit(): void {
		$limit = $this->createRateLimit(3);

		self::assertSame([0, 0, 0], [$limit->hit('203.0.113.7'), $limit->hit('203.0.113.7'), $limit->hit('203.0.113.7')]);
		self::assertSame([], $this->writer->lines);
	}

	public function testCountsEachQuoteWithOneUpsertAndReadsTheCountOfThisConnection(): void {
		$this->createRateLimit(3)->hit('203.0.113.7');

		self::assertCount(2, $this->db->queries);
		self::assertStringStartsWith('INSERT INTO `oc_frenet_simulator_limit` (`client`, `count`, `expires`) VALUES (', $this->db->queries[0]);
		self::assertStringContainsString(' ON DUPLICATE KEY UPDATE `count` = LAST_INSERT_ID(', $this->db->queries[0]);
		self::assertSame('SELECT LAST_INSERT_ID() AS `count`', $this->db->queries[1]);
	}

	public function testBlocksTheQuoteAboveTheLimitUntilTheMinuteEnds(): void {
		$limit = $this->createRateLimit(2);

		$limit->hit('203.0.113.7');
		$limit->hit('203.0.113.7');

		$retry_after = $limit->hit('203.0.113.7');

		self::assertGreaterThanOrEqual(59, $retry_after);
		self::assertLessThanOrEqual(RateLimit::WINDOW, $retry_after);
		self::assertGreaterThan(0, $limit->hit('203.0.113.7'));
	}

	public function testLogsTheBlockedIpOncePerMinute(): void {
		$limit = $this->createRateLimit(1);

		$limit->hit('203.0.113.7');
		$limit->hit('203.0.113.7');
		$limit->hit('203.0.113.7');
		$limit->hit('203.0.113.7');

		self::assertCount(1, $this->writer->lines);
		self::assertStringStartsWith('ERROR Simulator quote limit reached (1 quotes per 60 seconds): IP 203.0.113.7 blocked for ', $this->writer->lines[0]);
	}

	public function testCountsEachIpApart(): void {
		$limit = $this->createRateLimit(1);

		$limit->hit('203.0.113.7');

		self::assertSame(0, $limit->hit('198.51.100.4'));
		self::assertGreaterThan(0, $limit->hit('203.0.113.7'));
	}

	public function testCountsTheAddressesOfAnIpv6NetworkTogether(): void {
		$limit = $this->createRateLimit(1);

		$limit->hit('2001:db8:1:2::a');

		self::assertGreaterThan(0, $limit->hit('2001:db8:1:2:ffff:ffff:ffff:1'));
		self::assertSame(0, $limit->hit('2001:db8:1:3::a'));
		self::assertStringContainsString('IP 2001:db8:1:2::/64 blocked for ', $this->writer->lines[0]);
	}

	public function testCountsAnIpv4MappedAddressAsTheIpv4(): void {
		$limit = $this->createRateLimit(1);

		$limit->hit('::ffff:203.0.113.7');

		self::assertGreaterThan(0, $limit->hit('203.0.113.7'));
		self::assertSame(0, $limit->hit('::ffff:198.51.100.4'));
	}

	public function testStartsANewMinuteAfterTheWindowEnds(): void {
		$limit = $this->createRateLimit(1);

		$limit->hit('203.0.113.7');

		$this->db->rows[hash('sha256', '203.0.113.7')]['expires'] = time() - 1;

		self::assertSame(0, $limit->hit('203.0.113.7'));
	}

	public function testKeepsTheIpOutOfTheTable(): void {
		$this->createRateLimit(1)->hit('203.0.113.7');

		self::assertSame([hash('sha256', '203.0.113.7')], array_keys($this->db->rows));
		self::assertStringNotContainsString('203.0.113.7', implode("\n", $this->db->queries));
	}

	public function testNeverBlocksWithoutLimit(): void {
		$limit = $this->createRateLimit(0);

		for ($i = 0; $i < 100; $i++) {
			self::assertSame(0, $limit->hit('203.0.113.7'));
		}

		self::assertSame([], $this->db->queries);
	}

	public function testQuotesWhenTheTableCannotBeWritten(): void {
		$this->db->failure = new \Exception("Error: Table 'opencart.oc_frenet_simulator_limit' doesn't exist");

		self::assertSame(0, $this->createRateLimit(1)->hit('203.0.113.7'));
		self::assertSame(["ERROR Simulator quote limit could not be checked, quoting anyway: Error: Table 'opencart.oc_frenet_simulator_limit' doesn't exist"], $this->writer->lines);
	}

	public function testRemovesSomeIpsOfPastMinutesFromARandomPointOfTheKeyWhenAnIpStartsAMinute(): void {
		$old = [];

		for ($i = 0; $i < RateLimit::CLEANUP_ROWS * 3; $i++) {
			$old[] = hash('sha256', 'old' . $i);
			$this->db->rows[hash('sha256', 'old' . $i)] = ['count' => 1, 'expires' => time() - 1];
		}

		$this->db->rows[hash('sha256', 'current')] = ['count' => 1, 'expires' => time() + 30];

		$limit = new RateLimit($this->db, 'oc_', new Log($this->writer, false), 3, 1);

		$limit->hit('203.0.113.7');

		self::assertSame(1, preg_match("/ FORCE INDEX \\(PRIMARY\\) WHERE `client` >= '([0-9a-f]{4})' AND `expires` <= \\d+ LIMIT " . RateLimit::CLEANUP_ROWS . '$/', $this->db->queries[2], $start));

		$after_start = count(array_filter($old, static function (string $key) use ($start): bool {
			return $key >= $start[1];
		}));

		self::assertCount(count($old) + 2 - min(RateLimit::CLEANUP_ROWS, $after_start), $this->db->rows);
		self::assertArrayHasKey(hash('sha256', 'current'), $this->db->rows);
		self::assertArrayHasKey(hash('sha256', '203.0.113.7'), $this->db->rows);

		$queries = count($this->db->queries);

		$limit->hit('203.0.113.7');

		self::assertCount($queries + 2, $this->db->queries);
	}

	public function testKeepsTheRemovalOfPastMinutesFromBlockingTheQuote(): void {
		$this->db->cleanup_failure = new \Exception('Error: Deadlock found when trying to get lock');

		self::assertSame(0, (new RateLimit($this->db, 'oc_', new Log($this->writer, false), 1, 1))->hit('203.0.113.7'));
		self::assertSame(['ERROR Simulator quote limits of past minutes could not be removed: Error: Deadlock found when trying to get lock'], $this->writer->lines);
	}

	private function createRateLimit(int $limit): RateLimit {
		return new RateLimit($this->db, 'oc_', new Log($this->writer, false), $limit, 0);
	}
}
