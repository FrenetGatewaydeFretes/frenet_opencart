<?php
declare(strict_types=1);

namespace Frenet;

use Frenet\Http\Client;
use Frenet\Quote\Exception;
use Frenet\Quote\Parser;
use Frenet\Quote\Request;
use Frenet\Quote\Service;

/**
 * Frenet REST API, authenticated by the token generated in the Frenet panel.
 */
class Api {
	/**
	 * @var string
	 */
	public const QUOTE_URL = 'https://api.frenet.com.br/shipping/quote';

	/**
	 * @var int
	 */
	private const TIMEOUT = 5;

	/**
	 * @var Client
	 */
	private $client;

	/**
	 * @var string
	 */
	private $token;

	/**
	 * @var string
	 */
	private $opencart_version;

	/**
	 * @var string
	 */
	private $quote_url;

	/**
	 * @param Client $client
	 * @param string $token
	 * @param string $opencart_version
	 * @param string $quote_url
	 */
	public function __construct(Client $client, string $token, string $opencart_version, string $quote_url = self::QUOTE_URL) {
		$this->client = $client;
		$this->token = $token;
		$this->opencart_version = $opencart_version;
		$this->quote_url = $quote_url;
	}

	/**
	 * Quotes the cart and returns the services the store's Frenet account offers for it.
	 *
	 * @param Request $request
	 * @return Service[]
	 * @throws Http\Exception
	 * @throws Exception
	 */
	public function quote(Request $request): array {
		$body = json_encode($request->toArray());

		if ($body === false) {
			throw new Exception('Could not encode the quote request: ' . json_last_error_msg());
		}

		$response = $this->client->post($this->quote_url, $body, [
			'Accept'       => 'application/json',
			'Content-Type' => 'application/json',
			'User-Agent'   => 'Frenet-' . Request::PLATFORM_NAME . '/' . Module::VERSION . ' (OpenCart ' . $this->opencart_version . ')',
			'token'        => $this->token
		], self::TIMEOUT);

		if (!$response->isSuccessful()) {
			throw new Exception('The Frenet API answered with HTTP ' . $response->getStatusCode(), $response->getStatusCode());
		}

		return (new Parser())->parse($response->getBody());
	}
}
