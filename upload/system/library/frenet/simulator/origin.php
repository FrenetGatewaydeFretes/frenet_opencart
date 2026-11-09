<?php
declare(strict_types=1);

namespace Frenet\Simulator;

/**
 * Tells a quote sent by the browser from another site, which the store page never sends, by the Origin and Sec-Fetch-Site headers.
 */
final class Origin {
	/**
	 * Whether the browser says the request comes from a page outside the store, comparing the host of Origin with the host of
	 * the request and of the store URLs, so a proxy that changes the Host does not refuse the store page.
	 *
	 * @param array<string, mixed> $server
	 * @param array<int, mixed> $store_urls
	 * @return bool
	 */
	public static function isForeign(array $server, array $store_urls): bool {
		$origin = $server['HTTP_ORIGIN'] ?? '';

		if (!is_string($origin) || $origin === '') {
			return ($server['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site';
		}

		$host = self::getHost($origin);

		if ($host === '') {
			return true;
		}

		$hosts = [self::getHost('//' . (string)($server['HTTP_HOST'] ?? ''))];

		foreach ($store_urls as $url) {
			$hosts[] = self::getHost((string)$url);
		}

		return !in_array($host, $hosts, true);
	}

	/**
	 * @param string $url
	 * @return string
	 */
	private static function getHost(string $url): string {
		return strtolower((string)parse_url($url, PHP_URL_HOST));
	}
}
