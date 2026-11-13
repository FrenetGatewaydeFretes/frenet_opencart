<?php
declare(strict_types=1);

namespace Frenet\Quote;

use Frenet\Api;
use Frenet\Log;

/**
 * Quotes a cart heavier than the package limit in packages, together with the whole cart, all at the same time.
 */
final class Splitter {
	/**
	 * @var int
	 */
	public const MAX_PACKAGES = 10;

	/**
	 * @var Api
	 */
	private $api;

	/**
	 * @var Log
	 */
	private $log;

	/**
	 * @var float
	 */
	private $max_weight;

	/**
	 * @var bool
	 */
	private $quiet;

	/**
	 * @param Api $api
	 * @param Log $log
	 * @param float $max_weight
	 * @param bool $quiet
	 */
	public function __construct(Api $api, Log $log, float $max_weight, bool $quiet) {
		$this->api = $api;
		$this->log = $log;
		$this->max_weight = $max_weight;
		$this->quiet = $quiet;
	}

	/**
	 * Quotes the cart whole when it fits one package or cannot be split, and in packages otherwise.
	 *
	 * @param Request $request
	 * @return Result
	 * @throws UnavailableException
	 */
	public function quote(Request $request): Result {
		$weight = round(array_sum(array_map(static function (Item $item): float {
			return $item->getWeight() * $item->getQuantity();
		}, $request->getItems())), 6);

		if ($weight <= $this->max_weight) {
			return $this->api->quote($request);
		}

		try {
			$packages = Packer::pack($request->getItems(), $this->max_weight, self::MAX_PACKAGES);
		} catch (Exception $exception) {
			$this->report('Cart of ' . $weight . ' kg quoted whole, without Correios split in packages: ' . $exception->getMessage());

			return $this->api->quote($request);
		}

		$requests = [Matcher::FULL => $request];

		foreach ($packages as $index => $items) {
			$requests[$index + 1] = $request->withItems($items);
		}

		$this->log->debug('Cart of ' . $weight . ' kg quoted in ' . count($packages) . ' packages of up to ' . $this->max_weight . ' kg');

		$results = $this->api->quoteAll($requests);

		foreach ($results as $index => $result) {
			if ($result instanceof UnavailableException) {
				$this->report(($index === Matcher::FULL ? 'Whole cart could not be quoted, other carriers left out: ' : 'Package ' . $index . ' could not be quoted, Correios left out: ') . $result->getMessage());
			}
		}

		return Matcher::match($results);
	}

	/**
	 * Logs as an error at checkout and only in debug for the simulator, which buyers call far more.
	 *
	 * @param string $message
	 * @return void
	 */
	private function report(string $message): void {
		if ($this->quiet) {
			$this->log->debug($message);
		} else {
			$this->log->error($message);
		}
	}
}
