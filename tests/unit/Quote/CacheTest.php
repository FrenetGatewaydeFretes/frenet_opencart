<?php
declare(strict_types=1);

namespace Frenet\Tests\Quote;

use Frenet\Quote\Cache;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class CacheTest extends TestCase {
	private const BODY = '{"ShippingSevicesArray":[]}';

	/**
	 * @var \Cache
	 */
	private $store;

	/**
	 * @var Cache
	 */
	private $cache;

	protected function setUp(): void {
		$this->store = new \Cache();
		$this->cache = new Cache($this->store);
	}

	public function testBuildsAKeyTheOpenCartFileCacheKeepsWhole(): void {
		$key = $this->cache->getKey('abc123', self::BODY);

		static::assertMatchesRegularExpression('/^frenet\.quote\.[0-9a-f]{64}$/', $key);
		static::assertSame($key, $this->cache->getKey('abc123', self::BODY));
		static::assertStringNotContainsString('abc123', $key);
	}

	public function testBuildsAnotherKeyForAnotherTokenOrBody(): void {
		$key = $this->cache->getKey('abc123', self::BODY);

		static::assertNotSame($key, $this->cache->getKey('xyz789', self::BODY));
		static::assertNotSame($key, $this->cache->getKey('abc123', '{"ShippingSevicesArray":[{}]}'));
	}

	public function testKeepsTheBodyForTenMinutes(): void {
		$this->cache->set('frenet.quote.key', self::BODY);

		static::assertSame(self::BODY, $this->cache->get('frenet.quote.key'));
		static::assertEqualsWithDelta(time() + 600, $this->store->entries['frenet.quote.key']['expires'], 1);
	}

	public function testIgnoresAnExpiredEntry(): void {
		$this->store->set('frenet.quote.key', ['expires' => time(), 'body' => self::BODY]);

		static::assertNull($this->cache->get('frenet.quote.key'));
	}

	#[TestWith([false], 'missing')]
	#[TestWith(['{"ShippingSevicesArray":[]}'], 'plain string')]
	#[TestWith([['expires' => 0]], 'without body')]
	#[TestWith([['body' => '{"ShippingSevicesArray":[]}']], 'without expiry')]
	public function testIgnoresAnEntryItDidNotWrite(mixed $entry): void {
		$this->store->set('frenet.quote.key', $entry);

		static::assertNull($this->cache->get('frenet.quote.key'));
	}
}
