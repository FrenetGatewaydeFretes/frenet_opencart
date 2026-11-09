<?php
declare(strict_types=1);

namespace Frenet\Simulator;

/**
 * Finds the IP of the visitor, also when the store is behind Cloudflare or behind a proxy of its own network, such as a load
 * balancer, where every request comes from the same address.
 */
final class ClientIp {
	/**
	 * @var string
	 */
	private const CLOUDFLARE_HEADER = 'HTTP_CF_CONNECTING_IP';

	/**
	 * @var string
	 */
	private const FORWARDED_HEADER = 'HTTP_X_FORWARDED_FOR';

	/**
	 * @var string[]
	 */
	private const INTERNAL_RANGES = [
		'10.0.0.0/8',
		'172.16.0.0/12',
		'192.168.0.0/16',
		'127.0.0.0/8',
		'100.64.0.0/10',
		'169.254.0.0/16',
		'::1/128',
		'fc00::/7',
		'fe80::/10'
	];

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
	 * The IP of the visitor: behind a proxy of the store network, the last address of X-Forwarded-For outside that network,
	 * which the proxy itself added, and from a Cloudflare address, the IP Cloudflare sends in CF-Connecting-IP.
	 *
	 * @param array<string, mixed> $server
	 * @return string
	 */
	public static function fromServer(array $server): string {
		$peer = (string)($server['REMOTE_ADDR'] ?? '');

		if (self::inRanges($peer, self::INTERNAL_RANGES)) {
			$peer = self::getForwarded($server[self::FORWARDED_HEADER] ?? '') ?: $peer;
		}

		$visitor = $server[self::CLOUDFLARE_HEADER] ?? '';

		if (is_string($visitor) && filter_var($visitor, FILTER_VALIDATE_IP) && self::inRanges($peer, self::CLOUDFLARE_RANGES)) {
			return $visitor;
		}

		return $peer;
	}

	/**
	 * The last address of X-Forwarded-For outside the store network, or an empty string when the header has none.
	 *
	 * @param mixed $header
	 * @return string
	 */
	private static function getForwarded($header): string {
		if (!is_string($header)) {
			return '';
		}

		foreach (array_reverse(explode(',', $header)) as $address) {
			$ip = trim($address);

			if (!filter_var($ip, FILTER_VALIDATE_IP)) {
				return '';
			}

			if (!self::inRanges($ip, self::INTERNAL_RANGES)) {
				return $ip;
			}
		}

		return '';
	}

	/**
	 * @param string $ip
	 * @param string[] $ranges
	 * @return bool
	 */
	private static function inRanges(string $ip, array $ranges): bool {
		if (!filter_var($ip, FILTER_VALIDATE_IP)) {
			return false;
		}

		$packed = (string)inet_pton($ip);

		if (substr($packed, 0, 12) === str_repeat("\0", 10) . "\xff\xff") {
			$packed = substr($packed, 12);
		}

		foreach ($ranges as $range) {
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
