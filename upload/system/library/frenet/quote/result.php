<?php
declare(strict_types=1);

namespace Frenet\Quote;

/**
 * What the Frenet API answered: the services that can be offered and the ones it reported with an error.
 */
class Result {
	/**
	 * @var Service[]
	 */
	private $services;

	/**
	 * @var array<int, array{code: string, description: string, message: string}>
	 */
	private $errors;

	/**
	 * @var string
	 */
	private $message;

	/**
	 * @param Service[] $services
	 * @param array<int, array{code: string, description: string, message: string}> $errors
	 * @param string $message
	 */
	public function __construct(array $services, array $errors, string $message) {
		$this->services = $services;
		$this->errors = $errors;
		$this->message = $message;
	}

	/**
	 * @return Service[]
	 */
	public function getServices(): array {
		return $this->services;
	}

	/**
	 * Services the API returned with Error set, with the Msg that explains why.
	 *
	 * @return array<int, array{code: string, description: string, message: string}>
	 */
	public function getErrors(): array {
		return $this->errors;
	}

	/**
	 * Message field of the response, usually filled only when no service came back.
	 *
	 * @return string
	 */
	public function getMessage(): string {
		return $this->message;
	}
}
