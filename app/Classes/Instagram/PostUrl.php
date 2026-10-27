<?php

namespace App\Classes\Instagram;

class PostUrl
{
	public static function shortcode(string $value): ?string
	{
		$value = trim($value);
		if ($value === "") {
			return null;
		}

		if (preg_match("#instagram\\.com/(?:p|reel|tv|reels)/([A-Za-z0-9_-]+)#i", $value, $match)) {
			return $match[1];
		}

		if (preg_match("#^([A-Za-z0-9_-]{5,20})$#", $value, $match)) {
			return $match[1];
		}

		return null;
	}

	public static function canonical(string $shortcode): string
	{
		return "https://www.instagram.com/p/" . $shortcode . "/";
	}
}
