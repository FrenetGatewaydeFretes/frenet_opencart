<?php
declare(strict_types=1);

namespace Frenet\Http;

/**
 * cURL transport with a hard time limit, so a slow Frenet API can never hold the checkout until PHP gives up.
 */
class Client {
	/**
	 * Sends a POST request and returns whatever HTTP answer came back, including 4xx and 5xx.
	 *
	 * @param string $url
	 * @param string $body
	 * @param array<string, string> $headers
	 * @param int $timeout
	 * @return Response
	 * @throws Exception
	 */
	public function post(string $url, string $body, array $headers, int $timeout): Response {
		$handle = curl_init($url);

		if ($handle === false) {
			throw new Exception('Could not initialize cURL');
		}

		curl_setopt_array($handle, [
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => $body,
			CURLOPT_HTTPHEADER     => $this->formatHeaders($headers),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CONNECTTIMEOUT => $timeout,
			CURLOPT_TIMEOUT        => $timeout,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2
		]);

		$result = curl_exec($handle);

		if (!is_string($result)) {
			throw new Exception(curl_error($handle), curl_errno($handle));
		}

		return new Response((int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE), $result);
	}

	/**
	 * @param array<string, string> $headers
	 * @return string[]
	 */
	private function formatHeaders(array $headers): array {
		$lines = [];

		foreach ($headers as $name => $value) {
			$lines[] = $name . ': ' . $value;
		}

		return $lines;
	}
}
