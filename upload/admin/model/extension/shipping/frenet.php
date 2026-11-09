<?php
use Frenet\Simulator\RateLimit;

class ModelExtensionShippingFrenet extends Model {
	/**
	 * Creates the table where the simulator counts the quotes of each IP, keyed by the hash of the IP.
	 *
	 * @return void
	 */
	public function createSimulatorTable(): void {
		RateLimit::createTable($this->db, DB_PREFIX);
	}

	/**
	 * @return void
	 */
	public function dropSimulatorTable(): void {
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . RateLimit::TABLE . "`");
	}
}
