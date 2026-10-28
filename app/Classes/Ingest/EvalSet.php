<?php

namespace App\Classes\Ingest;

use Katu\Files\File;

class EvalSet
{
	public static function coversPath(): File
	{
		return new File(\App\App::getBaseDir(), "app", "Resources", "Ingest", "eval", "covers.json");
	}

	public static function rolesPath(): File
	{
		return new File(\App\App::getBaseDir(), "app", "Resources", "Ingest", "eval", "roles.json");
	}

	/**
	 * @return list<array{source: string, sourceKey: string, coverPosition: int, notes?: string}>
	 */
	public static function covers(): array
	{
		return self::loadItems(self::coversPath());
	}

	/**
	 * @return list<array{source: string, sourceKey: string, leadPositions: list<int>, pattern?: string, notes?: string}>
	 */
	public static function roles(): array
	{
		return self::loadItems(self::rolesPath());
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private static function loadItems(File $file): array
	{
		if (!$file->exists()) {
			throw new \RuntimeException("Eval set missing: " . $file);
		}

		$decoded = json_decode((string)$file->get(), true);
		if (!is_array($decoded) || !isset($decoded["items"]) || !is_array($decoded["items"])) {
			throw new \RuntimeException("Invalid eval set JSON: " . $file);
		}

		return array_values(array_filter($decoded["items"], "is_array"));
	}

	/**
	 * @return array{
	 *   cover: array{total: int, scored: int, pass: int, fail: int, missing: int, failures: list<string>},
	 *   role: array{total: int, scored: int, pass: int, fail: int, missing: int, patternPass: int, patternFail: int, failures: list<string>}
	 * }
	 */
	public static function evaluate(): array
	{
		return [
			"cover" => self::evaluateCovers(),
			"role" => self::evaluateRoles(),
		];
	}

	/**
	 * @return array{total: int, scored: int, pass: int, fail: int, missing: int, failures: list<string>}
	 */
	private static function evaluateCovers(): array
	{
		$items = self::covers();
		$pass = 0;
		$fail = 0;
		$missing = 0;
		$scored = 0;
		$failures = [];

		foreach ($items as $row) {
			$source = (string)($row["source"] ?? "");
			$sourceKey = IngestItem::normalizeSourceKey((string)($row["sourceKey"] ?? ""));
			$expected = (int)($row["coverPosition"] ?? 0);
			if ($source === "" || $sourceKey === "" || $expected < 1) {
				continue;
			}

			$item = IngestItem::getBySourceKey($source, $sourceKey);
			if (!$item || $item->getStatus() !== IngestItem::STATUS_KEYWORDED) {
				$missing++;
				$failures[] = "{$source}/{$sourceKey}: not keyworded yet";
				continue;
			}

			$scored++;
			$actual = null;
			foreach ($item->getFiles() as $file) {
				if ($file->isCover()) {
					$actual = (int)$file->getPosition();
					break;
				}
			}

			if ($actual === $expected) {
				$pass++;
			} else {
				$fail++;
				$failures[] = sprintf(
					"%s/%s: cover expected pos %d, got %s",
					$source,
					$sourceKey,
					$expected,
					$actual === null ? "none" : (string)$actual,
				);
			}
		}

		return [
			"total" => count($items),
			"scored" => $scored,
			"pass" => $pass,
			"fail" => $fail,
			"missing" => $missing,
			"failures" => $failures,
		];
	}

	/**
	 * @return array{total: int, scored: int, pass: int, fail: int, missing: int, patternPass: int, patternFail: int, failures: list<string>}
	 */
	private static function evaluateRoles(): array
	{
		$items = self::roles();
		$pass = 0;
		$fail = 0;
		$missing = 0;
		$scored = 0;
		$patternPass = 0;
		$patternFail = 0;
		$failures = [];

		foreach ($items as $row) {
			$source = (string)($row["source"] ?? "");
			$sourceKey = IngestItem::normalizeSourceKey((string)($row["sourceKey"] ?? ""));
			$expectedLeads = array_values(array_map("intval", is_array($row["leadPositions"] ?? null) ? $row["leadPositions"] : []));
			sort($expectedLeads);
			if ($source === "" || $sourceKey === "" || $expectedLeads === []) {
				continue;
			}

			$item = IngestItem::getBySourceKey($source, $sourceKey);
			if (!$item || $item->getStatus() !== IngestItem::STATUS_KEYWORDED) {
				$missing++;
				$failures[] = "{$source}/{$sourceKey}: not keyworded yet";
				continue;
			}

			$scored++;
			$actualLeads = [];
			foreach ($item->getFiles() as $file) {
				if ($file->getRole() === IngestItemFile::ROLE_LEAD) {
					$actualLeads[] = (int)$file->getPosition();
				}
			}
			sort($actualLeads);

			// Pass if every expected lead is marked lead (extra leads OK).
			$missingLeads = array_values(array_diff($expectedLeads, $actualLeads));
			if ($missingLeads === []) {
				$pass++;
			} else {
				$fail++;
				$failures[] = sprintf(
					"%s/%s: lead expected [%s], got [%s]",
					$source,
					$sourceKey,
					implode(",", $expectedLeads),
					implode(",", $actualLeads) ?: "none",
				);
			}

			$expectedPattern = isset($row["pattern"]) && is_string($row["pattern"]) ? $row["pattern"] : null;
			if ($expectedPattern) {
				$hints = $item->getLayoutHints();
				$actualPattern = is_array($hints) ? ($hints["pattern"] ?? null) : null;
				if ($actualPattern === $expectedPattern) {
					$patternPass++;
				} else {
					$patternFail++;
					$failures[] = sprintf(
						"%s/%s: pattern expected %s, got %s",
						$source,
						$sourceKey,
						$expectedPattern,
						is_string($actualPattern) ? $actualPattern : "none",
					);
				}
			}
		}

		return [
			"total" => count($items),
			"scored" => $scored,
			"pass" => $pass,
			"fail" => $fail,
			"missing" => $missing,
			"patternPass" => $patternPass,
			"patternFail" => $patternFail,
			"failures" => $failures,
		];
	}
}
