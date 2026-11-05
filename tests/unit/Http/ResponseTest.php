<?php
declare(strict_types=1);

namespace Frenet\Tests\Http;

use Frenet\Http\Response;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase {
	public function testClassifiesTheStatusCode(): void {
		self::assertTrue((new Response(200, ''))->isSuccessful());
		self::assertFalse((new Response(200, ''))->isClientError());

		self::assertFalse((new Response(401, ''))->isSuccessful());
		self::assertTrue((new Response(401, ''))->isClientError());

		self::assertFalse((new Response(503, ''))->isSuccessful());
		self::assertFalse((new Response(503, ''))->isClientError());
	}

	public function testKeepsTheBody(): void {
		self::assertSame('{"a":1}', (new Response(200, '{"a":1}'))->getBody());
	}
}
