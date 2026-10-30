<?php

namespace App\Classes\Content;

class Section
{
	public const RITUALY = "ritualy";
	public const DENIK = "denik";
	public const UDALOSTI = "udalosti";

	/** @deprecated Merged into DENIK */
	public const HIMMELHAUS = "himmelhaus";
	/** @deprecated Merged into DENIK */
	public const WORKSHOPY = "workshopy";
	/** @deprecated Merged into DENIK */
	public const UMENI = "umeni";

	/** Legacy journal section codes (pre-deník collapse). */
	public const LEGACY_JOURNAL = [
		self::HIMMELHAUS,
		self::WORKSHOPY,
		self::UMENI,
	];

	public static function all(): array
	{
		return [
			self::RITUALY,
			self::DENIK,
			self::UDALOSTI,
		];
	}

	/**
	 * Lasting public sections (homepage plots + nav work items).
	 *
	 * @return list<string>
	 */
	public static function work(): array
	{
		return [
			self::RITUALY,
			self::UDALOSTI,
			self::DENIK,
		];
	}

	public static function isValid(string $section): bool
	{
		return in_array($section, self::all(), true)
			|| in_array($section, self::LEGACY_JOURNAL, true);
	}

	public static function isWork(string $section): bool
	{
		return in_array($section, self::work(), true)
			|| in_array($section, self::LEGACY_JOURNAL, true);
	}

	public static function isJournal(string $section): bool
	{
		return $section === self::DENIK
			|| in_array($section, self::LEGACY_JOURNAL, true);
	}

	public static function title(string $section): string
	{
		return match ($section) {
			self::RITUALY => "Svatební rituály",
			self::DENIK, self::HIMMELHAUS, self::WORKSHOPY, self::UMENI => "Deník",
			self::UDALOSTI => "Události",
			default => $section,
		};
	}

	public static function navLabel(string $section): string
	{
		return match ($section) {
			self::RITUALY => "rituály",
			self::DENIK, self::HIMMELHAUS, self::WORKSHOPY, self::UMENI => "deník",
			self::UDALOSTI => "události",
			default => $section,
		};
	}

	public static function pathPrefix(string $section): string
	{
		return match ($section) {
			self::RITUALY => "/svatebni-ritualy",
			self::DENIK, self::HIMMELHAUS, self::WORKSHOPY, self::UMENI => "/denik",
			self::UDALOSTI => "/udalosti",
			default => "/" . $section,
		};
	}

	public static function routeName(string $section): string
	{
		return match ($section) {
			self::RITUALY => "sections.ritualy",
			self::DENIK, self::HIMMELHAUS, self::WORKSHOPY, self::UMENI => "sections.denik",
			self::UDALOSTI => "sections.udalosti",
			default => "homepage.index",
		};
	}

	public static function lead(string $section): string
	{
		return match ($section) {
			self::RITUALY => "Bespoke přechodové rituály pro pár a rodinu — ne svatební koordinace, ale obřad na míru místu, rostlinám a lidem.",
			self::DENIK, self::HIMMELHAUS, self::WORKSHOPY, self::UMENI => "Zápisky z domu, dílen, slavností a krajiny — chronologicky, tak jak přicházejí.",
			self::UDALOSTI => "Reportáže ze slavností, dílen a společných setkání — to, co už se stalo a zůstává.",
			default => "",
		};
	}
}
