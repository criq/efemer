<?php

namespace App\Classes\Content;

class Section
{
	public const RITUALY = "ritualy";
	public const UMENI = "umeni";
	public const DENIK = "denik";
	public const UDALOSTI = "udalosti";

	/** @deprecated Merged into DENIK */
	public const HIMMELHAUS = "himmelhaus";
	/** @deprecated Merged into DENIK */
	public const WORKSHOPY = "workshopy";

	/** Legacy journal section codes (pre-deník collapse). */
	public const LEGACY_JOURNAL = [
		self::HIMMELHAUS,
		self::WORKSHOPY,
	];

	public static function all(): array
	{
		return [
			self::RITUALY,
			self::UMENI,
			self::DENIK,
			self::UDALOSTI,
		];
	}

	/**
	 * Lasting public sections (homepage plots + nav).
	 *
	 * @return list<string>
	 */
	public static function work(): array
	{
		return [
			self::RITUALY,
			self::UMENI,
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
			self::UMENI => "Umění",
			self::DENIK, self::HIMMELHAUS, self::WORKSHOPY => "Deník",
			self::UDALOSTI => "Události",
			default => $section,
		};
	}

	public static function navLabel(string $section): string
	{
		return match ($section) {
			self::RITUALY => "rituály",
			self::UMENI => "umění",
			self::DENIK, self::HIMMELHAUS, self::WORKSHOPY => "deník",
			self::UDALOSTI => "události",
			default => $section,
		};
	}

	public static function pathPrefix(string $section): string
	{
		return match ($section) {
			self::RITUALY => "/svatebni-ritualy",
			self::UMENI => "/umeni",
			self::DENIK, self::HIMMELHAUS, self::WORKSHOPY => "/denik",
			self::UDALOSTI => "/udalosti",
			default => "/" . $section,
		};
	}

	public static function routeName(string $section): string
	{
		return match ($section) {
			self::RITUALY => "sections.ritualy",
			self::UMENI => "sections.umeni",
			self::DENIK, self::HIMMELHAUS, self::WORKSHOPY => "sections.denik",
			self::UDALOSTI => "sections.udalosti",
			default => "homepage.index",
		};
	}

	public static function lead(string $section): string
	{
		return match ($section) {
			self::RITUALY => "Bespoke přechodové rituály pro pár a rodinu — ne svatební koordinace, ale obřad na míru místu, rostlinám a lidem.",
			self::UMENI => "Instalace, happeningy a práce s rostlinou jako nositelkou významu.",
			self::DENIK, self::HIMMELHAUS, self::WORKSHOPY => "Zápisky z domu, dílen, slavností a krajiny — chronologicky, tak jak přicházejí.",
			self::UDALOSTI => "Pozvánky na to, co se právě chystá.",
			default => "",
		};
	}
}
