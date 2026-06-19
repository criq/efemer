<?php

namespace App\Classes\Tools;

class Slugger
{
	public const HOMEPAGE_PATH = "/";

	public static function isHomepageInput(?string $input): bool
	{
		return trim(trim((string)$input), "/") === "";
	}

	public static function isHomepagePath(?string $path): bool
	{
		return $path === self::HOMEPAGE_PATH;
	}

	public static function slugifyPagePath(?string $input): string
	{
		$input = trim((string)$input);
		$input = trim($input, "/");

		if ($input === "") {
			return self::HOMEPAGE_PATH;
		}

		$slug = \URLify::slug($input, 200, "-", "cs");

		return $slug !== "" ? "/" . $slug : "";
	}

	public static function getRoutePath(?string $path): string
	{
		return ltrim((string)$path, "/");
	}
}
