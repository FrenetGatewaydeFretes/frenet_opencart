<?php
declare(strict_types=1);

namespace Frenet\Simulator;

/**
 * Finds the IP of the visitor, also when the store is behind Cloudflare and every request comes from a Cloudflare address.
 */
final class ClientIp {
	/**
	 * @var string
	 */
	private const CLOUDFLARE_HEADER = 'HTTP_CF_CONNECTING_IP';

	/**
	 * @var string[]
	 */
	private const CLOUDFLARE_RANGES = [
		'173.245.48.0/20',
		'103.21.244.0/22',
		'103.22.200.0/22',
		'103.31.4.0/22',
		'141.101.64.0/18',
		'108.162.192.0/18',
		'190.93.240.0/20',
		'188.114.96.0/20',
		'197.234.240.0/22',
		'198.41.128.0/17',
		'162.158.0.0/15',
		'104.16.0.0/13',
		'104.24.0.0/14',
		'172.64.0.0/13',
		'131.0.72.0/22',
		'2400:cb00::/32',
		'2606:4700::/32',
		'2803:f800::/32',
		'2405:b500::/32',
		'2405:8100::/32',
		'2a06:98c0::/29',
		'2c0f:f248::/32'
	];

	/**
	 * The IP Cloudflare sends in CF-Connecting-IP when the request comes from a Cloudflare address, otherwise REMOTE_ADDR.
	 *
	 * @param array<string, mixed> $server
	 * @return string
	 */
	public static function fromServer(array $server): string {
		$remote = (string)($server['REMOTE_ADDR'] ?? '');
		$visitor = $server[self::CLOUDFLARE_HEADER] ?? '';

		if (is_string($visitor) && filter_var($visitor, FILTER_VALIDATE_IP) && self::isCloudflare($remote)) {
			return $visitor;
		}

		return $remote;
	}

	/**
	 * @param string $ip
	 * @return bool
	 */
	private static function isCloudflare(string $ip): bool {
		if (!filter_var($ip, FILTER_VALIDATE_IP)) {
			return false;
		}

		$packed = (string)inet_pton($ip);

		if (substr($packed, 0, 12) === str_repeat("\0", 10) . "\xff\xff") {
			$packed = substr($packed, 12);
		}

		foreach (self::CLOUDFLARE_RANGES as $range) {
			[$network, $bits] = explode('/', $range);

			if (self::inRange($packed, (string)inet_pton($network), (int)$bits)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param string $packed
	 * @param string $network
	 * @param int $bits
	 * @return bool
	 */
	private static function inRange(string $packed, string $network, int $bits): bool {
		if (strlen($packed) !== strlen($network)) {
			return false;
		}

		$bytes = intdiv($bits, 8);

		if (substr($packed, 0, $bytes) !== substr($network, 0, $bytes)) {
			return false;
		}

		$rest = $bits % 8;

		return $rest === 0 || ((ord($packed[$bytes]) ^ ord($network[$bytes])) & (0xFF << (8 - $rest)) & 0xFF) === 0;
	}
}
