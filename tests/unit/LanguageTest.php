<?php
declare(strict_types=1);

namespace Frenet\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LanguageTest extends TestCase {
	private const LANGUAGES = ['en-gb', 'pt-br'];

	private const CORE_KEYS = ['text_home', 'text_extension', 'text_enabled', 'text_disabled', 'text_yes', 'text_no', 'text_none', 'text_all_zones', 'text_loading', 'button_save', 'button_cancel', 'decimal_point'];

	#[DataProvider('provideTranslatesEveryKeyInBothLanguagesCases')]
	public function testTranslatesEveryKeyInBothLanguages(string $file): void {
		$keys = [];

		foreach (self::LANGUAGES as $language) {
			$keys[$language] = array_keys(self::load(str_replace('{language}', $language, $file)));
			sort($keys[$language]);
		}

		self::assertNotSame([], $keys['en-gb']);
		self::assertSame($keys['en-gb'], $keys['pt-br']);
	}

	/**
	 * @return iterable<string, array{0: string}>
	 */
	public static function provideTranslatesEveryKeyInBothLanguagesCases(): iterable {
		foreach (['admin', 'catalog'] as $area) {
			foreach (['shipping', 'total'] as $type) {
				$file = 'upload/' . $area . '/language/{language}/extension/' . $type . '/frenet.php';

				yield $area . ' ' . $type => [$file];
			}
		}
	}

	#[DataProvider('provideDefinesEveryKeyTheCodeAsksForCases')]
	public function testDefinesEveryKeyTheCodeAsksFor(string $area): void {
		$defined = self::CORE_KEYS;

		foreach (glob(self::root() . 'upload/' . $area . '/language/*/extension/*/frenet.php') ?: [] as $path) {
			$defined = array_merge($defined, array_keys(self::load(substr($path, strlen(self::root())))));
		}

		$missing = [];

		foreach (glob(self::root() . 'upload/' . $area . '/{controller,model}/extension/*/frenet.php', GLOB_BRACE) ?: [] as $path) {
			preg_match_all("/language->get\\('([a-z0-9_]+)'\\)/", (string)file_get_contents($path), $matches);

			$missing = array_merge($missing, array_diff($matches[1], $defined));
		}

		self::assertSame([], array_values(array_unique($missing)));
	}

	/**
	 * @return iterable<string, array{0: string}>
	 */
	public static function provideDefinesEveryKeyTheCodeAsksForCases(): iterable {
		yield 'admin' => ['admin'];
		yield 'catalog' => ['catalog'];
	}

	/**
	 * @param string $file
	 * @return array<string, string>
	 */
	private static function load(string $file): array {
		$_ = [];

		require self::root() . $file;

		return $_;
	}

	/**
	 * @return string
	 */
	private static function root(): string {
		return dirname(__DIR__, 2) . '/';
	}
}
