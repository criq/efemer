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

		$segments = [];

		foreach (explode("/", $input) as $segment) {
			$segment = trim($segment);
			if ($segment === "") {
				continue;
			}

			$slug = \URLify::slug($segment, 200, "-", "cs");
			if ($slug !== "") {
				$segments[] = $slug;
			}
		}

		return count($segments) ? "/" . implode("/", $segments) : "";
	}

	public static function getRoutePath(?string $path): string
	{
		return ltrim((string)$path, "/");
	}
}
