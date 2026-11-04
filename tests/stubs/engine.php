<?php
declare(strict_types=1);

if (!defined('DB_PREFIX')) {
	define('DB_PREFIX', 'oc_');
}

if (!defined('VERSION')) {
	define('VERSION', '3.0.5.1');
}

if (!class_exists('Controller', false)) {
	/**
	 * Same public API as the OpenCart 3 core Controller, which reads every registry object through __get.
	 *
	 * @property \Config $config
	 * @property \DB $db
	 * @property \Cart\Weight $weight
	 * @property \Cart\Length $length
	 * @property mixed $cart
	 * @property mixed $currency
	 * @property mixed $document
	 * @property mixed $language
	 * @property mixed $load
	 * @property mixed $request
	 * @property mixed $response
	 * @property mixed $session
	 * @property mixed $tax
	 * @property mixed $url
	 * @property mixed $user
	 * @property mixed $model_localisation_geo_zone
	 * @property mixed $model_localisation_length_class
	 * @property mixed $model_localisation_tax_class
	 * @property mixed $model_localisation_weight_class
	 * @property mixed $model_setting_setting
	 */
	abstract class Controller {
		/**
		 * @var mixed
		 */
		protected $registry;

		/**
		 * @param mixed $registry
		 */
		public function __construct($registry) {
			$this->registry = $registry;
		}

		/**
		 * @param string $key
		 * @return mixed
		 */
		public function __get($key) {
			return $this->registry->get($key);
		}

		/**
		 * @param string $key
		 * @param mixed $value
		 * @return void
		 */
		public function __set($key, $value) {
			$this->registry->set($key, $value);
		}
	}
}

if (!class_exists('Model', false)) {
	/**
	 * Same public API as the OpenCart 3 core Model, which reads every registry object through __get.
	 *
	 * @property \Config $config
	 * @property \DB $db
	 * @property \Cart\Weight $weight
	 * @property \Cart\Length $length
	 * @property mixed $cart
	 * @property mixed $currency
	 * @property mixed $document
	 * @property mixed $language
	 * @property mixed $load
	 * @property mixed $request
	 * @property mixed $response
	 * @property mixed $session
	 * @property mixed $tax
	 * @property mixed $url
	 * @property mixed $user
	 * @property mixed $model_localisation_geo_zone
	 * @property mixed $model_localisation_length_class
	 * @property mixed $model_localisation_tax_class
	 * @property mixed $model_localisation_weight_class
	 * @property mixed $model_setting_setting
	 */
	abstract class Model {
		/**
		 * @var mixed
		 */
		protected $registry;

		/**
		 * @param mixed $registry
		 */
		public function __construct($registry) {
			$this->registry = $registry;
		}

		/**
		 * @param string $key
		 * @return mixed
		 */
		public function __get($key) {
			return $this->registry->get($key);
		}

		/**
		 * @param string $key
		 * @param mixed $value
		 * @return void
		 */
		public function __set($key, $value) {
			$this->registry->set($key, $value);
		}
	}
}
