<?php
declare(strict_types=1);

namespace Frenet\Http;

/**
 * cURL transport with a hard time limit, so a slow Frenet API can never hold the checkout until PHP gives up.
 */
class Client {
	/**
	 * @var float
	 */
	private const SELECT_TIMEOUT = 1.0;

	/**
	 * @var int
	 */
	private const SELECT_FAILED_PAUSE = 1000;

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
		$answer = $this->postAll($url, [$body], $headers, $timeout)[0];

		if ($answer instanceof Exception) {
			throw $answer;
		}

		return $answer;
	}

	/**
	 * Sends the POST requests at the same time, each with its own time limit, so they take about as long as the slowest.
	 *
	 * @param string $url
	 * @param array<int|string, string> $bodies
	 * @param array<string, string> $headers
	 * @param int $timeout
	 * @return array<int|string, Exception|Response>
	 */
	public function postAll(string $url, array $bodies, array $headers, int $timeout): array {
		$answers = [];
		$handles = [];
		$multi = curl_multi_init();

		foreach ($bodies as $key => $body) {
			$handle = curl_init($url);

			if ($handle === false) {
				$answers[$key] = new Exception('Could not initialize cURL');

				continue;
			}

			curl_setopt_array($handle, $this->getOptions($body, $headers, $timeout));

			curl_multi_add_handle($multi, $handle);
			$handles[$key] = $handle;
		}

		$results = $this->run($multi, $handles);

		foreach ($handles as $key => $handle) {
			$code = $results[$key] ?? null;

			if ($code === null) {
				$answers[$key] = new Exception('The transfer stopped before an answer came back');
			} elseif ($code !== CURLE_OK) {
				$answers[$key] = new Exception(curl_error($handle) ?: curl_strerror($code), $code);
			} else {
				$answers[$key] = new Response((int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE), (string)curl_multi_getcontent($handle));
			}

			curl_multi_remove_handle($multi, $handle);
		}

		curl_multi_close($multi);

		$ordered = [];

		foreach (array_keys($bodies) as $key) {
			$ordered[$key] = $answers[$key];
		}

		return $ordered;
	}

	/**
	 * Runs the transfers until all finish or cURL fails, and returns the result code of each transfer that finished.
	 *
	 * @param resource $multi
	 * @param array<int|string, resource> $handles
	 * @return array<int|string, int>
	 */
	private function run($multi, array $handles): array {
		$results = [];

		do {
			do {
				$status = curl_multi_exec($multi, $running);
			} while ($status === CURLM_CALL_MULTI_PERFORM);

			while ($info = curl_multi_info_read($multi)) {
				$key = array_search($info['handle'], $handles, true);

				if ($key !== false) {
					$results[$key] = (int)$info['result'];
				}
			}

			if ($running && curl_multi_select($multi, self::SELECT_TIMEOUT) === -1) {
				usleep(self::SELECT_FAILED_PAUSE);
			}
		} while ($running && $status === CURLM_OK);

		return $results;
	}

	/**
	 * Options of one request, waiting to share an HTTP/2 connection so the requests sent together open a single connection.
	 *
	 * @param string $body
	 * @param array<string, string> $headers
	 * @param int $timeout
	 * @return array<int, mixed>
	 */
	private function getOptions(string $body, array $headers, int $timeout): array {
		$options = [
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
		];

		if (defined('CURLOPT_PIPEWAIT')) {
			$options[constant('CURLOPT_PIPEWAIT')] = true;
		}

		return $options;
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
