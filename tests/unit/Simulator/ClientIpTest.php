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

	public function testReturnsAnEmptyIpWithoutRemoteAddress(): void {
		self::assertSame('', ClientIp::fromServer(['HTTP_CF_CONNECTING_IP' => '198.51.100.4']));
	}
}
