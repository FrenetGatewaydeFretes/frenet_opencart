<?php
declare(strict_types=1);

namespace Frenet;

/**
 * Typed read access to the shipping_frenet_* settings so callers never handle raw OpenCart keys.
 */
class Config {
	/**
	 * @var string
	 */
	private const PREFIX = 'shipping_frenet_';

	/**
	 * @var int
	 */
	public const TIMEOUT_MIN = 2;

	/**
	 * @var int
	 */
	public const TIMEOUT_MAX = 10;

	/**
	 * @var int
	 */
	public const TIMEOUT_DEFAULT = 5;

	/**
	 * @var int
	 */
	public const ATTEMPTS_MIN = 1;

	/**
	 * @var int
	 */
	public const ATTEMPTS_MAX = 3;

	/**
	 * @var int
	 */
	public const ATTEMPTS_DEFAULT = 3;

	/**
	 * @var \Config
	 */
	private $config;

	/**
	 * @param \Config $config
	 */
	public function __construct(\Config $config) {
		$this->config = $config;
	}

	/**
	 * Whether the store owner enabled Frenet as a shipping method.
	 *
	 * @return bool
	 */
	public function isEnabled(): bool {
		return (bool)$this->get('status');
	}

	/**
	 * Token generated in the Frenet panel that authenticates every API call.
	 *
	 * @return string
	 */
	public function getToken(): string {
		return trim((string)$this->get('token'));
	}

	/**
	 * Origin postcode with the mask removed, as the Frenet API expects.
	 *
	 * @return string
	 */
	public function getOriginPostcode(): string {
		return (string)preg_replace('/\D/', '', (string)$this->get('postcode'));
	}

	/**
	 * Template for the rate title, where %s is the service name and %d the delivery days.
	 *
	 * @return string
	 */
	public function getDeliveryTimeMessage(): string {
		return (string)$this->get('msg_prazo');
	}

	/**
	 * Weight class the store owner mapped to kilograms, the unit the Frenet API expects.
	 *
	 * @return int
	 */
	public function getWeightClassId(): int {
		return (int)$this->get('weight_class_id');
	}

	/**
	 * Length class the store owner mapped to centimetres, the unit the Frenet API expects.
	 *
	 * @return int
	 */
	public function getLengthClassId(): int {
		return (int)$this->get('length_class_id');
	}

	/**
	 * Seconds each quote attempt may take, kept within the supported range.
	 *
	 * @return int
	 */
	public function getTimeout(): int {
		return $this->getInRange('timeout', self::TIMEOUT_DEFAULT, self::TIMEOUT_MIN, self::TIMEOUT_MAX);
	}

	/**
	 * How many times a failed quote is sent, kept within the supported range.
	 *
	 * @return int
	 */
	public function getAttempts(): int {
		return $this->getInRange('attempts', self::ATTEMPTS_DEFAULT, self::ATTEMPTS_MIN, self::ATTEMPTS_MAX);
	}

	/**
	 * Whether every request and response goes to frenet.log, besides the errors.
	 *
	 * @return bool
	 */
	public function isDebug(): bool {
		return (bool)$this->get('debug');
	}

	/**
	 * Position of Frenet among the shipping methods at checkout.
	 *
	 * @return int
	 */
	public function getSortOrder(): int {
		return (int)$this->get('sort_order');
	}

	/**
	 * @param string $key
	 * @param int $default
	 * @param int $min
	 * @param int $max
	 * @return int
	 */
	private function getInRange(string $key, int $default, int $min, int $max): int {
		$value = $this->get($key);

		if ($value === null || $value === '') {
			return $default;
		}

		return max($min, min($max, (int)$value));
	}

	/**
	 * @param string $key
	 * @return mixed
	 */
	private function get(string $key) {
		return $this->config->get(self::PREFIX . $key);
	}
}
