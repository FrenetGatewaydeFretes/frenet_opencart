<?php
use Frenet\Simulator\RateLimit;

class ModelExtensionShippingFrenet extends Model {
	/**
	 * Creates the table where the simulator counts the quotes of each IP, keyed by the hash of the IP.
	 *
	 * @return void
	 */
	public function createSimulatorTable(): void {
		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . RateLimit::TABLE . "` (`client` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, `count` INT UNSIGNED NOT NULL, `expires` INT UNSIGNED NOT NULL, PRIMARY KEY (`client`), KEY `expires` (`expires`)) ENGINE=InnoDB");
	}

	/**
	 * @return void
	 */
	public function dropSimulatorTable(): void {
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . RateLimit::TABLE . "`");
	}
}
