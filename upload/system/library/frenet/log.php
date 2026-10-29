<?php
declare(strict_types=1);

namespace Frenet;

/**
 * Writes Frenet entries to their own log file, always masking secrets so a shared log never leaks the token.
 */
class Log {
	/**
	 * @var string
	 */
	public const FILENAME = 'frenet.log';

	/**
	 * @var string
	 */
	private const MASK = '********';

	/**
	 * @var string[]
	 */
	private const SECRET_KEYS = ['token'];

	/**
	 * @var \Log
	 */
	private $writer;

	/**
	 * @var bool
	 */
	private $debug;

	/**
	 * @param \Log $writer
	 * @param bool $debug
	 */
	public function __construct(\Log $writer, bool $debug) {
		$this->writer = $writer;
		$this->debug = $debug;
	}

	/**
	 * Records a failure; written regardless of the debug setting.
	 *
	 * @param string $message
	 * @param array<string, mixed> $context
	 * @return void
	 */
	public function error(string $message, array $context = []): void {
		$this->write('ERROR', $message, $context);
	}

	/**
	 * Records request and response details; written only while debug is enabled.
	 *
	 * @param string $message
	 * @param array<string, mixed> $context
	 * @return void
	 */
	public function debug(string $message, array $context = []): void {
		if ($this->debug) {
			$this->write('DEBUG', $message, $context);
		}
	}

	/**
	 * @param string $level
	 * @param string $message
	 * @param array<string, mixed> $context
	 * @return void
	 */
	private function write(string $level, string $message, array $context): void {
		$line = $level . ' ' . $message;

		if ($context) {
			$line .= ' ' . json_encode($this->mask($context), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
		}

		$this->writer->write($line);
	}

	/**
	 * @param array<mixed> $context
	 * @return array<mixed>
	 */
	private function mask(array $context): array {
		foreach ($context as $key => $value) {
			if (is_string($key) && in_array(strtolower($key), self::SECRET_KEYS, true)) {
				$context[$key] = self::MASK;
			} elseif (is_array($value)) {
				$context[$key] = $this->mask($value);
			}
		}

		return $context;
	}
}
