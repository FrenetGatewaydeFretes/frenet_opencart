<?php
declare(strict_types=1);

namespace Frenet\Tests;

use Frenet\GeoZone;
use PHPUnit\Framework\TestCase;

final class GeoZoneTest extends TestCase {
	public function testContainsTheAddressOfAZoneOrOfTheWholeCountry(): void {
		$db = new \DB([[['found' => '1']], []]);
		$geo_zone = new GeoZone($db, 'oc_');

		self::assertTrue($geo_zone->contains(5, ['country_id' => '30', 'zone_id' => '464']));
		self::assertFalse($geo_zone->contains(5, []));
		self::assertSame("SELECT 1 FROM `oc_zone_to_geo_zone` WHERE `geo_zone_id` = '5' AND `country_id` = '30' AND (`zone_id` = '464' OR `zone_id` = '0') LIMIT 1", $db->queries[0]);
		self::assertStringContainsString("`country_id` = '0' AND (`zone_id` = '0' OR `zone_id` = '0')", $db->queries[1]);
	}

	public function testTellsADeletedGeoZone(): void {
		$db = new \DB([[['found' => '1']], []]);
		$geo_zone = new GeoZone($db, 'oc_');

		self::assertTrue($geo_zone->exists(5));
		self::assertFalse($geo_zone->exists(6));
		self::assertSame("SELECT 1 FROM `oc_geo_zone` WHERE `geo_zone_id` = '6' LIMIT 1", $db->queries[1]);
	}
}
