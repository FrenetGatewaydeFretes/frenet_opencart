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
		$weight = 0.0;

		foreach ($request->getItems() as $item) {
			$weight += $item->getWeight() * $item->getQuantity();
		}

		if ($weight <= $this->max_weight) {
			return $this->api->quote($request);
		}

		try {
			$packages = Packer::pack($request->getItems(), $this->max_weight);
		} catch (Exception $exception) {
			$this->report('Cart of ' . round($weight, 6) . ' kg quoted whole, without Correios split in packages: ' . $exception->getMessage());

			return $this->api->quote($request);
		}

		$requests = [Matcher::FULL => $request];

		foreach ($packages as $index => $items) {
			$requests[$index + 1] = $request->withItems($items);
		}

		$this->log->debug('Cart of ' . round($weight, 6) . ' kg quoted in ' . count($packages) . ' packages of up to ' . $this->max_weight . ' kg');

		$results = $this->api->quoteAll($requests);

		$failed = [];

		foreach ($results as $index => $result) {
			if ($result instanceof UnavailableException) {
				$failed[spl_object_hash($result)]['failure'] = $result;
				$failed[spl_object_hash($result)]['indexes'][] = $index;
			}
		}

		foreach ($failed as $part) {
			$this->report($this->describeFailure($part['indexes'], $part['failure']));
		}

		return Matcher::match($results);
	}

	/**
	 * What one failure left out of the checkout, naming every part that got it, as identical packages share one answer.
	 *
	 * @param array<int, int|string> $indexes
	 * @param UnavailableException $failure
	 * @return string
	 */
	private function describeFailure(array $indexes, UnavailableException $failure): string {
		$packages = array_values(array_diff($indexes, [Matcher::FULL]));
		$parts = [];

		if (count($packages) !== count($indexes)) {
			$parts[] = 'Whole cart could not be quoted, other carriers left out';
		}

		if ($packages) {
			$parts[] = (count($packages) > 1 ? 'Packages ' : 'Package ') . implode(', ', $packages) . ' could not be quoted, Correios left out';
		}

		return implode('; ', $parts) . ': ' . $failure->getMessage();
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
