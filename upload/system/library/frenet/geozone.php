<?php
declare(strict_types=1);

namespace Frenet;

/**
 * Checks addresses against the geo zone chosen for Frenet, the way the core shipping and total extensions do.
 */
final class GeoZone {
	/**
	 * @var \DB
	 */
	private $db;

	/**
	 * @var string
	 */
	private $prefix;

	/**
	 * @param \DB $db
	 * @param string $prefix
	 */
	public function __construct(\DB $db, string $prefix) {
		$this->db = $db;
		$this->prefix = $prefix;
	}

	/**
	 * Whether the geo zone covers the country and zone of the address, the whole country included.
	 *
	 * @param int $geo_zone_id
	 * @param array<string, mixed> $address
	 * @return bool
	 */
	public function contains(int $geo_zone_id, array $address): bool {
		return (bool)$this->db->query("SELECT 1 FROM `" . $this->prefix . "zone_to_geo_zone` WHERE `geo_zone_id` = '" . $geo_zone_id . "' AND `country_id` = '" . (int)($address['country_id'] ?? 0) . "' AND (`zone_id` = '" . (int)($address['zone_id'] ?? 0) . "' OR `zone_id` = '0') LIMIT 1")->num_rows;
	}

	/**
	 * @param int $geo_zone_id
	 * @return bool
	 */
	public function exists(int $geo_zone_id): bool {
		return (bool)$this->db->query("SELECT 1 FROM `" . $this->prefix . "geo_zone` WHERE `geo_zone_id` = '" . $geo_zone_id . "' LIMIT 1")->num_rows;
	}
}
