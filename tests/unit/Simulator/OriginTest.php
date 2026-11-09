<?php
declare(strict_types=1);

namespace Frenet\Tests\Simulator;

use Frenet\Simulator\Origin;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class OriginTest extends TestCase {
	private const STORE_URLS = ['http://loja.example.com/', 'https://loja.example.com/'];

	#[TestWith(['https://loja.example.com', 'loja.example.com'], 'same host')]
	#[TestWith(['https://LOJA.example.com:8443', 'loja.example.com:8443'], 'port and case')]
	#[TestWith(['https://loja.example.com', 'php-fpm:9000'], 'Host changed by a proxy')]
	#[TestWith(['https://www.outra.example.com', 'www.outra.example.com'], 'domain outside the settings')]
	#[TestWith(['http://[2001:db8::1]:8080', '[2001:db8::1]:8080'], 'IPv6 host')]
	public function testAcceptsTheStorePage(string $origin, string $host): void {
		self::assertFalse(Origin::isForeign(['HTTP_ORIGIN' => $origin, 'HTTP_HOST' => $host, 'HTTP_SEC_FETCH_SITE' => 'same-origin'], self::STORE_URLS));
	}

	#[TestWith(['https://evil.example'], 'other site')]
	#[TestWith(['https://loja.example.com.evil.example'], 'store host as a prefix')]
	#[TestWith(['null'], 'sandboxed page')]
	public function testRefusesAnotherSite(string $origin): void {
		self::assertTrue(Origin::isForeign(['HTTP_ORIGIN' => $origin, 'HTTP_HOST' => 'loja.example.com'], self::STORE_URLS));
	}

	public function testRefusesACrossSiteRequestWithoutOrigin(): void {
		self::assertTrue(Origin::isForeign(['HTTP_HOST' => 'loja.example.com', 'HTTP_SEC_FETCH_SITE' => 'cross-site'], self::STORE_URLS));
	}

	/**
	 * @param array<string, mixed> $headers
	 */
	#[TestWith([[]], 'client without the headers')]
	#[TestWith([['HTTP_SEC_FETCH_SITE' => 'same-origin']], 'same origin')]
	#[TestWith([['HTTP_ORIGIN' => ['https://evil.example']]], 'header as a list')]
	public function testAcceptsARequestWithoutOrigin(array $headers): void {
		self::assertFalse(Origin::isForeign($headers + ['HTTP_HOST' => 'loja.example.com'], self::STORE_URLS));
	}
}
