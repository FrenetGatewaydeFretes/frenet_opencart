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

	/**
	 * Removes a route from the access and modify permissions of every user group, keeping groups without those lists intact.
	 *
	 * @param string $route
	 * @return void
	 */
	public function removePermissions(string $route): void {
		foreach ($this->db->query("SELECT `user_group_id`, `permission` FROM `" . DB_PREFIX . "user_group`")->rows as $user_group) {
			$permission = json_decode((string)$user_group['permission'], true);

			if (!is_array($permission)) {
				continue;
			}

			$changed = false;

			foreach (['access', 'modify'] as $type) {
				if (isset($permission[$type]) && is_array($permission[$type]) && in_array($route, $permission[$type], true)) {
					$permission[$type] = array_values(array_diff($permission[$type], [$route]));
					$changed = true;
				}
			}

			if ($changed) {
				$this->db->query("UPDATE `" . DB_PREFIX . "user_group` SET `permission` = '" . $this->db->escape((string)json_encode($permission)) . "' WHERE `user_group_id` = '" . (int)$user_group['user_group_id'] . "'");
			}
		}
	}
}
