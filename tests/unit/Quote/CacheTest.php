<?php
declare(strict_types=1);

namespace Frenet\Tests\Quote;

use Frenet\Quote\Cache;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class CacheTest extends TestCase {
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

		self::assertMatchesRegularExpression('/^frenet\.quote\.[0-9a-f]{64}$/', $key);
		self::assertSame($key, $this->cache->getKey('abc123', self::BODY));
		self::assertStringNotContainsString('abc123', $key);
	}

	public function testBuildsAnotherKeyForAnotherTokenOrBody(): void {
		$key = $this->cache->getKey('abc123', self::BODY);

		self::assertNotSame($key, $this->cache->getKey('xyz789', self::BODY));
		self::assertNotSame($key, $this->cache->getKey('abc123', '{"ShippingSevicesArray":[{}]}'));
	}

	public function testKeepsTheWarningsOfTheCoreCacheOutOfThePage(): void {
		$store = new class () extends \Cache {
			public function get($key) {
				return fopen(sys_get_temp_dir() . '/frenet-missing/cache.' . $key, 'r');
			}

			public function set($key, $value) {
				fopen(sys_get_temp_dir() . '/frenet-missing/cache.' . $key, 'r');
			}
		};

		$warnings = [];

		set_error_handler(static function (int $code, string $message) use (&$warnings): bool {
			$warnings[] = $message;

			return true;
		});

		try {
			$cache = new Cache($store);

			self::assertNull($cache->get('frenet.quote.key'));

			$cache->set('frenet.quote.key', self::BODY);
		} finally {
			restore_error_handler();
		}

		self::assertSame([], $warnings);
	}

	public function testKeepsTheBodyForTenMinutes(): void {
		$this->cache->set('frenet.quote.key', self::BODY);

		self::assertSame(self::BODY, $this->cache->get('frenet.quote.key'));
		self::assertEqualsWithDelta(time() + 600, $this->store->entries['frenet.quote.key']['expires'], 1);
	}

	public function testOnlyReadsWhenItCannotWrite(): void {
		$this->store->set('frenet.quote.checkout', ['expires' => time() + 60, 'body' => self::BODY]);

		$cache = new Cache($this->store, false);

		$cache->set('frenet.quote.key', self::BODY);

		self::assertArrayNotHasKey('frenet.quote.key', $this->store->entries);
		self::assertSame(self::BODY, $cache->get('frenet.quote.checkout'));
	}

	public function testIgnoresAnExpiredEntry(): void {
		$this->store->set('frenet.quote.key', ['expires' => time(), 'body' => self::BODY]);

		self::assertNull($this->cache->get('frenet.quote.key'));
	}

	#[TestWith([false], 'missing')]
	#[TestWith(['{"ShippingSevicesArray":[]}'], 'plain string')]
	#[TestWith([['expires' => 0]], 'without body')]
	#[TestWith([['body' => '{"ShippingSevicesArray":[]}']], 'without expiry')]
	public function testIgnoresAnEntryItDidNotWrite(mixed $entry): void {
		$this->store->set('frenet.quote.key', $entry);

		self::assertNull($this->cache->get('frenet.quote.key'));
	}
}
