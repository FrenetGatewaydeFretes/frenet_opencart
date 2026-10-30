<?php
declare(strict_types=1);

namespace Frenet\Tests\Quote;

use Frenet\Quote\Service;
use PHPUnit\Framework\TestCase;

class ServiceTest extends TestCase {
	public function testFillsTheTemplateWithTheNameAndTheDeliveryDays(): void {
		static::assertSame('PAC - Em média 7 dia(s)', (new Service('03298', 'PAC', 25.9, 7))->getTitle('%s - Em média %d dia(s)'));
	}

	public function testUsesTheServiceNameWithoutTemplateOrDeliveryDays(): void {
		static::assertSame('PAC', (new Service('03298', 'PAC', 25.9, 7))->getTitle(''));
		static::assertSame('PAC', (new Service('03298', 'PAC', 25.9, 0))->getTitle('%s - Em média %d dia(s)'));
	}

	public function testNeverFailsOnATemplateWithUnexpectedPlaceholders(): void {
		static::assertSame('PAC PAC 7 %f', (new Service('03298', 'PAC', 25.9, 7))->getTitle('%s %s %d %f'));
	}
}
