<?php

namespace App\Classes\Content;

class Section
{
	public const RITUALY = "ritualy";
	public const HIMMELHAUS = "himmelhaus";
	public const WORKSHOPY = "workshopy";
	public const UMENI = "umeni";
	public const UDALOSTI = "udalosti";

	public static function all(): array
	{
		return [
			self::RITUALY,
			self::HIMMELHAUS,
			self::WORKSHOPY,
			self::UMENI,
			self::UDALOSTI,
		];
	}

	public static function work(): array
	{
		return [
			self::RITUALY,
			self::HIMMELHAUS,
			self::WORKSHOPY,
			self::UMENI,
		];
	}

	public static function isValid(string $section): bool
	{
		return in_array($section, self::all(), true);
	}

	public static function isWork(string $section): bool
	{
		return in_array($section, self::work(), true);
	}

	public static function title(string $section): string
	{
		return match ($section) {
			self::RITUALY => "Svatební rituály",
			self::HIMMELHAUS => "Himmelhaus",
			self::WORKSHOPY => "Workshopy",
			self::UMENI => "Umění",
			self::UDALOSTI => "Události",
			default => $section,
		};
	}

	public static function navLabel(string $section): string
	{
		return match ($section) {
			self::RITUALY => "rituály",
			self::HIMMELHAUS => "himmelhaus",
			self::WORKSHOPY => "workshopy",
			self::UMENI => "umění",
			self::UDALOSTI => "události",
			default => $section,
		};
	}

	public static function pathPrefix(string $section): string
	{
		return match ($section) {
			self::RITUALY => "/svatebni-ritualy",
			self::HIMMELHAUS => "/himmelhaus",
			self::WORKSHOPY => "/workshopy",
			self::UMENI => "/umeni",
			self::UDALOSTI => "/udalosti",
			default => "/" . $section,
		};
	}

	public static function routeName(string $section): string
	{
		return match ($section) {
			self::RITUALY => "sections.ritualy",
			self::HIMMELHAUS => "sections.himmelhaus",
			self::WORKSHOPY => "sections.workshopy",
			self::UMENI => "sections.umeni",
			self::UDALOSTI => "sections.udalosti",
			default => "homepage.index",
		};
	}

	public static function lead(string $section): string
	{
		return match ($section) {
			self::RITUALY => "Bespoke přechodové rituály pro pár a rodinu — ne svatební koordinace, ale obřad na míru místu, rostlinám a lidem.",
			self::HIMMELHAUS => "Deník kamenného domu v Českém středohoří, kde Lucie žije a otevírá dům slavnostem.",
			self::WORKSHOPY => "Reportáže z dílen, slavností a společných setkání — to, co už se stalo a zůstává.",
			self::UMENI => "Instalace, happeningy, kniha Svátosti a práce s rostlinou jako nositelkou významu.",
			self::UDALOSTI => "Slavnosti, poutě a otevřený dům — pozvánky na to, co se právě chystá.",
			default => "",
		};
	}
}
