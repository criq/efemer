<?php

namespace App\Classes\Ingest;

/**
 * Deterministic keyword / theme / entity cleanup shared by KeywordAnalyzer and KeywordQc.
 */
class KeywordNormalizer
{
	/**
	 * @return list<string>
	 */
	public static function normalizeList(mixed $value, bool $preserveCase = false): array
	{
		$out = [];
		$seen = [];
		foreach (self::stringList($value) as $raw) {
			$keyword = self::fixTypos($raw);
			if ($keyword === "") {
				continue;
			}
			if (!$preserveCase) {
				$keyword = self::lowercaseKeyword($keyword);
			}
			$key = mb_strtolower($keyword);
			if ($key === "" || isset($seen[$key])) {
				continue;
			}
			$seen[$key] = true;
			$out[] = $keyword;
		}

		return $out;
	}

	public static function containsCyrillic(string $text): bool
	{
		return (bool)preg_match("/\\p{Cyrillic}/u", $text);
	}

	/**
	 * @param list<string> $keywords
	 */
	public static function listContainsCyrillic(array $keywords): bool
	{
		foreach ($keywords as $keyword) {
			if (self::containsCyrillic($keyword)) {
				return true;
			}
		}

		return false;
	}

	public static function fixTypos(string $keyword): string
	{
		$keyword = trim($keyword);
		if ($keyword === "") {
			return $keyword;
		}

		// floristika stem mangling + Cyrillic script bleed (e.g. "flor друже")
		$keyword = preg_replace("/flor\\s*друже/ui", "floristika", $keyword) ?? $keyword;
		$keyword = preg_replace("/\\bflorisitika\\b/ui", "floristika", $keyword) ?? $keyword;
		$keyword = preg_replace("/\\bflorika\\b/ui", "floristika", $keyword) ?? $keyword;
		$keyword = preg_replace("/\\bfloristka\\b/ui", "floristika", $keyword) ?? $keyword;

		if (preg_match("/\\p{Cyrillic}/u", $keyword)) {
			$keyword = preg_replace("/\\p{Cyrillic}+/u", "", $keyword) ?? $keyword;
			$keyword = trim(preg_replace("/\\s{2,}/u", " ", $keyword) ?? $keyword);
			$keyword = preg_replace("/\\bflor\\b/ui", "floristika", $keyword) ?? $keyword;
		}

		$map = [
			"florisitika" => "floristika",
			"florika" => "floristika",
			"přírodní florisitika" => "přírodní floristika",
			"přírodní florika" => "přírodní floristika",
			"autorská florisitika" => "autorská floristika",
			"autorská florika" => "autorská floristika",
			"košilela" => "košile",
		];
		$lower = mb_strtolower($keyword);

		return $map[$lower] ?? $keyword;
	}

	/**
	 * @return list<string>
	 */
	private static function stringList(mixed $value): array
	{
		if (!is_array($value)) {
			return [];
		}

		$out = [];
		foreach ($value as $item) {
			if (is_string($item) && trim($item) !== "") {
				$out[] = trim($item);
			}
		}

		return array_values(array_unique($out));
	}

	private static function lowercaseKeyword(string $keyword): string
	{
		if (preg_match('/^[A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ]{2,5}$/u', $keyword)) {
			return $keyword;
		}

		return mb_strtolower($keyword);
	}
}
