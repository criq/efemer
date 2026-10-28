<?php

namespace App\Classes\Ingest;

use App\Classes\Instagram\CaptionDates;
use Katu\Tools\Calendar\Time;

class BodyDates
{
	public static function firstFromHtml(?string $html): ?Time
	{
		$text = trim(html_entity_decode(strip_tags((string)$html), ENT_QUOTES | ENT_HTML5, "UTF-8"));
		if ($text === "") {
			return null;
		}

		$parsed = CaptionDates::parse($text);

		return $parsed["startsAt"];
	}

	public static function parseIndexDate(?string $date): ?Time
	{
		if ($date === null || trim($date) === "") {
			return null;
		}

		if (!preg_match("/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/", trim($date), $match)) {
			return null;
		}

		return new Time(sprintf("%04d-%02d-%02d 12:00:00", (int)$match[3], (int)$match[2], (int)$match[1]));
	}
}
