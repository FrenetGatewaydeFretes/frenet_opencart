<?php
declare(strict_types=1);

namespace Frenet\Tests\Simulator;

use Frenet\Log;
use Frenet\Simulator\RateLimit;
use PHPUnit\Framework\TestCase;

final class RateLimitTest extends TestCase {
	private \Cache $cache;

	private \Log $writer;

	protected function setUp(): void {
		$this->cache = new \Cache();
		$this->writer = new \Log(Log::FILENAME);
	}

	public function testAllowsTheQuotesUpToTheLimit(): void {
		$limit = $this->createRateLimit(3);

		self::assertSame([0, 0, 0], [$limit->hit('203.0.113.7'), $limit->hit('203.0.113.7'), $limit->hit('203.0.113.7')]);
		self::assertSame([], $this->writer->lines);
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

	public function testStartsANewMinuteAfterTheWindowEnds(): void {
		$limit = $this->createRateLimit(1);

		$limit->hit('203.0.113.7');

		$key = array_key_first($this->cache->entries);
		$this->cache->entries[$key]['expires'] = time() - 1;

		self::assertSame(0, $limit->hit('203.0.113.7'));
	}

	public function testKeepsTheIpOutOfTheCacheKey(): void {
		$this->createRateLimit(1)->hit('203.0.113.7');

		self::assertSame(['frenet.simulator.' . hash('sha256', '203.0.113.7')], array_keys($this->cache->entries));
	}

	public function testNeverBlocksWithoutLimit(): void {
		$limit = $this->createRateLimit(0);

		for ($i = 0; $i < 100; $i++) {
			self::assertSame(0, $limit->hit('203.0.113.7'));
		}

		self::assertSame([], $this->cache->entries);
	}

	public function testQuotesWhenTheCacheCannotBeRead(): void {
		$cache = new class () extends \Cache {
			public function get($key) {
				throw new \ValueError('fread(): Argument #2 ($length) must be greater than 0');
			}
		};

		self::assertSame(0, (new RateLimit($cache, new Log($this->writer, false), 1))->hit('203.0.113.7'));
		self::assertSame(['ERROR Simulator quote limit could not be checked, quoting anyway: fread(): Argument #2 ($length) must be greater than 0'], $this->writer->lines);
	}

	private function createRateLimit(int $limit): RateLimit {
		return new RateLimit($this->cache, new Log($this->writer, false), $limit);
	}
}
