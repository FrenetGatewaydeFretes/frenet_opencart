<?php
declare(strict_types=1);

namespace Frenet\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LanguageTest extends TestCase {
	private const LANGUAGES = ['en-gb', 'pt-br'];

	private const KEY_PATTERN = '(?:text|entry|help|error|button|placeholder|heading)_[a-z0-9_]+';

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

	#[DataProvider('provideTranslatesEveryKeyInBothLanguagesCases')]
	public function testKeepsThePlaceholdersOfEveryTextInBothLanguages(string $file): void {
		$placeholders = [];

		foreach (self::LANGUAGES as $language) {
			foreach (self::load(str_replace('{language}', $language, $file)) as $key => $text) {
				preg_match_all('/%[sd]/', $text, $matches);

				$placeholders[$language][$key] = $matches[0];
			}

			ksort($placeholders[$language]);
		}

		self::assertSame($placeholders['en-gb'], $placeholders['pt-br']);
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
		$defined = self::getDefinedKeys($area);
		$missing = [];

		foreach (self::getCode($area, ['controller', 'model'], '.php') as $code) {
			preg_match_all("/language->get\\('([a-z0-9_]+)'\\)/", $code, $matches);

			$missing = array_merge($missing, array_diff($matches[1], $defined));
		}

		self::assertSame([], array_values(array_unique($missing)));
	}

	#[DataProvider('provideDefinesEveryKeyTheCodeAsksForCases')]
	public function testDefinesEveryKeyTheTemplatesPrint(string $area): void {
		$defined = self::getDefinedKeys($area);

		foreach (self::getCode($area, ['controller'], '.php') as $code) {
			preg_match_all("/\\\$data\\['([a-z0-9_]+)'\\]/", $code, $matches);

			$defined = array_merge($defined, $matches[1]);
		}

		$missing = [];

		foreach (self::getCode($area, ['view'], '.twig') as $template) {
			preg_match_all('/{[{%]-?\\s*(?:if\\s+(?:not\\s+)?)?(' . self::KEY_PATTERN . ')\\b/', $template, $matches);

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
	 * @param string $area
	 * @return string[]
	 */
	private static function getDefinedKeys(string $area): array {
		$defined = self::CORE_KEYS;

		foreach (glob(self::root() . 'upload/' . $area . '/language/*/extension/*/frenet.php') ?: [] as $path) {
			$defined = array_merge($defined, array_keys(self::load(substr($path, strlen(self::root())))));
		}

		return $defined;
	}

	/**
	 * @param string $area
	 * @param string[] $folders
	 * @param string $extension
	 * @return string[]
	 */
	private static function getCode(string $area, array $folders, string $extension): array {
		$code = [];

		foreach ($folders as $folder) {
			$directory = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::root() . 'upload/' . $area . '/' . $folder, \FilesystemIterator::SKIP_DOTS));

			foreach ($directory as $file) {
				if (substr($file->getPathname(), -strlen($extension)) === $extension) {
					$code[] = (string)file_get_contents($file->getPathname());
				}
			}
		}

		self::assertNotSame([], $code);

		return $code;
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
