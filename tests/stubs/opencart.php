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
		 * @var string
		 */
		public $filename;

		/**
		 * @param string $filename
		 */
		public function __construct($filename) {
			$this->filename = $filename;
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

if (!class_exists('DB', false)) {
	/**
	 * Same query API as the OpenCart 3 core DB, answering from a queue of row sets and recording every SQL.
	 */
	class DB {
		/**
		 * @var string[]
		 */
		public $queries = [];

		/**
		 * @var array<int, array<int, array<string, mixed>>>
		 */
		private $results;

		/**
		 * @param array<int, array<int, array<string, mixed>>> $results
		 */
		public function __construct(array $results) {
			$this->results = $results;
		}

		/**
		 * @param string $sql
		 * @return \stdClass
		 */
		public function query($sql) {
			$this->queries[] = $sql;
			$rows = array_shift($this->results) ?? [];

			$result = new \stdClass();
			$result->rows = $rows;
			$result->row = $rows[0] ?? [];
			$result->num_rows = count($rows);

			return $result;
		}

		/**
		 * @param string $value
		 * @return string
		 */
		public function escape($value) {
			return addslashes($value);
		}
	}
}

if (!class_exists('Cache', false)) {
	/**
	 * Same get, set and delete as the OpenCart 3 core Cache, keeping entries in memory instead of an adaptor.
	 */
	class Cache {
		/**
		 * @var array<string, mixed>
		 */
		public $entries = [];

		/**
		 * @param string $key
		 * @return mixed
		 */
		public function get($key) {
			return array_key_exists($key, $this->entries) ? $this->entries[$key] : false;
		}

		/**
		 * @param string $key
		 * @param mixed $value
		 * @return void
		 */
		public function set($key, $value) {
			$this->entries[$key] = $value;
		}

		/**
		 * @param string $key
		 * @return void
		 */
		public function delete($key) {
			unset($this->entries[$key]);
		}
	}
}
