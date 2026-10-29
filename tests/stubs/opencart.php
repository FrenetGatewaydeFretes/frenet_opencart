<?php
declare(strict_types=1);

if (!class_exists('Config', false)) {
	/**
	 * Same public API as the OpenCart 3 core Config, without loading files from DIR_CONFIG.
	 */
	class Config {
		/**
		 * @var array<string, mixed>
		 */
		private $data = [];

		/**
		 * @param string $key
		 * @return mixed
		 */
		public function get($key) {
			return isset($this->data[$key]) ? $this->data[$key] : null;
		}

		/**
		 * @param string $key
		 * @param mixed $value
		 * @return void
		 */
		public function set($key, $value) {
			$this->data[$key] = $value;
		}

		/**
		 * @param string $key
		 * @return bool
		 */
		public function has($key) {
			return isset($this->data[$key]);
		}
	}
}

if (!class_exists('Log', false)) {
	/**
	 * Same public API as the OpenCart 3 core Log, keeping lines in memory instead of a file under DIR_LOGS.
	 */
	class Log {
		/**
		 * @var string[]
		 */
		public $lines = [];

		/**
		 * @param string $filename
		 */
		public function __construct($filename) {
		}

		/**
		 * @param mixed $message
		 * @return void
		 */
		public function write($message) {
			$this->lines[] = print_r($message, true);
		}
	}
}
