<?php
declare(strict_types=1);

namespace Frenet\Tests\Simulator;

use Frenet\Simulator\ClientIp;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class ClientIpTest extends TestCase {
	public function testUsesTheRemoteAddressOutsideCloudflare(): void {
		self::assertSame('203.0.113.7', ClientIp::fromServer(['REMOTE_ADDR' => '203.0.113.7']));
	}

	#[TestWith(['173.245.48.10'], 'first IPv4 range')]
	#[TestWith(['172.71.255.254'], 'end of 172.64.0.0/13')]
	#[TestWith(['131.0.72.1'], 'last IPv4 range')]
	#[TestWith(['2606:4700:10::6816:1'], 'IPv6 /32')]
	#[TestWith(['2a06:98c7:ffff::1'], 'end of 2a06:98c0::/29')]
	#[TestWith(['::ffff:104.16.0.1'], 'IPv4 mapped as IPv6')]
	public function testUsesTheVisitorIpSentByCloudflare(string $remote): void {
		self::assertSame('198.51.100.4', ClientIp::fromServer(['REMOTE_ADDR' => $remote, 'HTTP_CF_CONNECTING_IP' => '198.51.100.4']));
	}

	public function testUsesAnIpv6VisitorSentByCloudflare(): void {
		self::assertSame('2001:db8::7', ClientIp::fromServer(['REMOTE_ADDR' => '162.158.1.1', 'HTTP_CF_CONNECTING_IP' => '2001:db8::7']));
	}

	#[TestWith(['203.0.113.7'], 'visitor sending the header himself')]
	#[TestWith(['172.72.0.1'], 'just after 172.64.0.0/13')]
	#[TestWith(['2a06:98c8::1'], 'just after 2a06:98c0::/29')]
	#[TestWith(['10.0.0.5'], 'private network')]
	public function testIgnoresTheHeaderOutsideCloudflare(string $remote): void {
		self::assertSame($remote, ClientIp::fromServer(['REMOTE_ADDR' => $remote, 'HTTP_CF_CONNECTING_IP' => '198.51.100.4']));
	}

	#[TestWith(['198.51.100.4, 203.0.113.9'], 'list')]
	#[TestWith(['unknown'], 'text')]
	#[TestWith([''], 'empty')]
	public function testIgnoresAHeaderThatIsNotAnIp(string $header): void {
		self::assertSame('173.245.48.10', ClientIp::fromServer(['REMOTE_ADDR' => '173.245.48.10', 'HTTP_CF_CONNECTING_IP' => $header]));
	}

	public function testIgnoresAHeaderSentAsAList(): void {
		self::assertSame('173.245.48.10', ClientIp::fromServer(['REMOTE_ADDR' => '173.245.48.10', 'HTTP_CF_CONNECTING_IP' => ['198.51.100.4']]));
	}

	#[TestWith(['10.0.0.2', '198.51.100.4'], 'load balancer')]
	#[TestWith(['127.0.0.1', '1.1.1.1, 198.51.100.4'], 'address typed by the visitor before the one of the proxy')]
	#[TestWith(['192.168.0.9', '198.51.100.4, 172.16.0.3, 10.0.0.2'], 'proxies of the store network after the visitor')]
	#[TestWith(['172.31.255.1', ' 198.51.100.4 '], 'spaces')]
	#[TestWith(['100.64.0.1', '198.51.100.4'], 'shared address space')]
	#[TestWith(['::ffff:10.0.0.2', '198.51.100.4'], 'IPv4 mapped as IPv6')]
	#[TestWith(['fd00::2', '198.51.100.4'], 'IPv6 unique local')]
	#[TestWith(['::1', '198.51.100.4'], 'IPv6 loopback')]
	public function testUsesTheVisitorIpAddedByAProxyOfTheStoreNetwork(string $remote, string $forwarded): void {
		self::assertSame('198.51.100.4', ClientIp::fromServer(['REMOTE_ADDR' => $remote, 'HTTP_X_FORWARDED_FOR' => $forwarded]));
	}

	public function testUsesAnIpv6VisitorAddedByAProxyOfTheStoreNetwork(): void {
		self::assertSame('2001:db8::7', ClientIp::fromServer(['REMOTE_ADDR' => '10.0.0.2', 'HTTP_X_FORWARDED_FOR' => '2001:db8::7']));
	}

	#[TestWith(['203.0.113.7'], 'public address')]
	#[TestWith(['172.32.0.1'], 'just after 172.16.0.0/12')]
	#[TestWith(['100.128.0.1'], 'just after 100.64.0.0/10')]
	public function testIgnoresTheForwardedHeaderOutsideTheStoreNetwork(string $remote): void {
		self::assertSame($remote, ClientIp::fromServer(['REMOTE_ADDR' => $remote, 'HTTP_X_FORWARDED_FOR' => '198.51.100.4']));
	}

	#[TestWith([''], 'empty')]
	#[TestWith(['10.0.0.3, 192.168.1.1'], 'only addresses of the store network')]
	#[TestWith(['198.51.100.4, unknown'], 'text added after the visitor')]
	#[TestWith(['198.51.100.4:51000'], 'address with port')]
	public function testKeepsTheProxyAddressWithoutAVisitorInTheForwardedHeader(string $forwarded): void {
		self::assertSame('10.0.0.2', ClientIp::fromServer(['REMOTE_ADDR' => '10.0.0.2', 'HTTP_X_FORWARDED_FOR' => $forwarded]));
	}

	public function testIgnoresAForwardedHeaderSentAsAList(): void {
		self::assertSame('10.0.0.2', ClientIp::fromServer(['REMOTE_ADDR' => '10.0.0.2', 'HTTP_X_FORWARDED_FOR' => ['198.51.100.4']]));
	}

	public function testUsesTheVisitorIpSentByCloudflareBeforeALoadBalancer(): void {
		self::assertSame('198.51.100.4', ClientIp::fromServer(['REMOTE_ADDR' => '10.0.0.2', 'HTTP_X_FORWARDED_FOR' => '198.51.100.4, 162.158.1.1', 'HTTP_CF_CONNECTING_IP' => '198.51.100.4']));
	}

	public function testIgnoresTheCloudflareHeaderWhenTheLoadBalancerWasNotReachedByCloudflare(): void {
		self::assertSame('203.0.113.7', ClientIp::fromServer(['REMOTE_ADDR' => '10.0.0.2', 'HTTP_X_FORWARDED_FOR' => '162.158.1.1, 203.0.113.7', 'HTTP_CF_CONNECTING_IP' => '198.51.100.4']));
	}

	public function testReturnsAnEmptyIpWithoutRemoteAddress(): void {
		self::assertSame('', ClientIp::fromServer(['HTTP_CF_CONNECTING_IP' => '198.51.100.4']));
	}
}
