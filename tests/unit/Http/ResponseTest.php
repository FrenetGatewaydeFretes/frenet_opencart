<?php
declare(strict_types=1);

namespace Frenet\Tests\Http;

use Frenet\Http\Response;
use PHPUnit\Framework\TestCase;

class ResponseTest extends TestCase {
	public function testClassifiesTheStatusCode(): void {
		static::assertTrue((new Response(200, ''))->isSuccessful());
		static::assertFalse((new Response(200, ''))->isClientError());

		static::assertFalse((new Response(401, ''))->isSuccessful());
		static::assertTrue((new Response(401, ''))->isClientError());

		static::assertFalse((new Response(503, ''))->isSuccessful());
		static::assertFalse((new Response(503, ''))->isClientError());
	}

	public function testKeepsTheBody(): void {
		static::assertSame('{"a":1}', (new Response(200, '{"a":1}'))->getBody());
	}
}
