<?php
declare(strict_types=1);

namespace Frenet\Tests;

use Frenet\Log;
use PHPUnit\Framework\TestCase;

class LogTest extends TestCase {
	public function testWritesErrorsEvenWithDebugDisabled(): void {
		$writer = new \Log(Log::FILENAME);

		(new Log($writer, false))->error('Quote failed', ['postcode' => '01310100']);

		static::assertSame(['ERROR Quote failed {"postcode":"01310100"}'], $writer->lines);
	}

	public function testSkipsDebugEntriesWhileDebugIsDisabled(): void {
		$writer = new \Log(Log::FILENAME);

		(new Log($writer, false))->debug('Request', ['body' => '{}']);

		static::assertSame([], $writer->lines);
	}

	public function testWritesDebugEntriesWhileDebugIsEnabled(): void {
		$writer = new \Log(Log::FILENAME);

		(new Log($writer, true))->debug('Request');

		static::assertSame(['DEBUG Request'], $writer->lines);
	}

	public function testMasksTheTokenAtAnyDepthAndCase(): void {
		$writer = new \Log(Log::FILENAME);

		(new Log($writer, true))->debug('Request', [
			'Token'   => 'secret',
			'headers' => ['token' => 'secret', 'Content-Type' => 'application/json']
		]);

		static::assertStringNotContainsString('secret', $writer->lines[0]);
		static::assertSame('DEBUG Request {"Token":"********","headers":{"token":"********","Content-Type":"application/json"}}', $writer->lines[0]);
	}
}
