<?php
declare(strict_types=1);

namespace Cart;

if (!class_exists('Cart\Weight', false)) {
	/**
	 * Same conversion API as the OpenCart 3 core Cart\Weight, built from an array instead of the weight_class table.
	 */
	class Weight {
		/**
		 * @var array<int, array{unit: string, value: float}>
		 */
		private $weights;

		/**
		 * @param array<int, array{unit: string, value: float}> $weights
		 */
		public function __construct(array $weights) {
			$this->weights = $weights;
		}

		/**
		 * @param mixed $value
		 * @param mixed $from
		 * @param mixed $to
		 * @return mixed
		 */
		public function convert($value, $from, $to) {
			if ($from == $to) {
				return $value;
			}

			$from = isset($this->weights[$from]) ? $this->weights[$from]['value'] : 1;
			$to = isset($this->weights[$to]) ? $this->weights[$to]['value'] : 1;

			return $value * ($to / $from);
		}

		/**
		 * @param mixed $weight_class_id
		 * @return string
		 */
		public function getUnit($weight_class_id) {
			return isset($this->weights[$weight_class_id]) ? $this->weights[$weight_class_id]['unit'] : '';
		}
	}
}

if (!class_exists('Cart\Length', false)) {
	/**
	 * Same conversion API as the OpenCart 3 core Cart\Length, built from an array instead of the length_class table.
	 */
	class Length {
		/**
		 * @var array<int, array{unit: string, value: float}>
		 */
		private $lengths;

		/**
		 * @param array<int, array{unit: string, value: float}> $lengths
		 */
		public function __construct(array $lengths) {
			$this->lengths = $lengths;
		}

		/**
		 * @param mixed $value
		 * @param mixed $from
		 * @param mixed $to
		 * @return mixed
		 */
		public function convert($value, $from, $to) {
			if ($from == $to) {
				return $value;
			}

			$from = isset($this->lengths[$from]) ? $this->lengths[$from]['value'] : 1;
			$to = isset($this->lengths[$to]) ? $this->lengths[$to]['value'] : 1;

			return $value * ($to / $from);
		}

		/**
		 * @param mixed $length_class_id
		 * @return string
		 */
		public function getUnit($length_class_id) {
			return isset($this->lengths[$length_class_id]) ? $this->lengths[$length_class_id]['unit'] : '';
		}
	}
}
