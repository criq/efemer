<?php

namespace App\Classes\Instagram;

use Katu\Tools\Calendar\Time;

class CaptionDates
{
	/**
	 * @return array{startsAt: ?Time, endsAt: ?Time}
	 */
	public static function parse(string $caption): array
	{
		$caption = trim($caption);
		$year = (int)date("Y");

		if (preg_match("/(\\d{1,2})\\.\\s*[–\\-]\\s*(\\d{1,2})\\.\\s*(\\d{1,2})\\.\\s*(\\d{4})/u", $caption, $match)) {
			return [
				"startsAt" => self::at((int)$match[1], (int)$match[3], (int)$match[4]),
				"endsAt" => self::at((int)$match[2], (int)$match[3], (int)$match[4], true),
			];
		}

		if (preg_match("/(\\d{1,2})\\.\\s*(\\d{1,2})\\.\\s*(\\d{4})/u", $caption, $match)) {
			return [
				"startsAt" => self::at((int)$match[1], (int)$match[2], (int)$match[3]),
				"endsAt" => null,
			];
		}

		$months = self::months();
		$monthPattern = implode("|", array_map("preg_quote", array_keys($months)));
		if (preg_match("/(\\d{1,2})\\.\\s*[–\\-]\\s*(\\d{1,2})\\.\\s*(" . $monthPattern . ")(?:\\s+(\\d{4}))?/ui", $caption, $match)) {
			$month = $months[mb_strtolower($match[3])];
			$resolvedYear = isset($match[4]) && $match[4] !== "" ? (int)$match[4] : $year;

			return [
				"startsAt" => self::at((int)$match[1], $month, $resolvedYear),
				"endsAt" => self::at((int)$match[2], $month, $resolvedYear, true),
			];
		}

		if (preg_match("/(\\d{1,2})\\.\\s*(" . $monthPattern . ")(?:\\s+(\\d{4}))?/ui", $caption, $match)) {
			$month = $months[mb_strtolower($match[2])];
			$resolvedYear = isset($match[3]) && $match[3] !== "" ? (int)$match[3] : $year;

			return [
				"startsAt" => self::at((int)$match[1], $month, $resolvedYear),
				"endsAt" => null,
			];
		}

		return [
			"startsAt" => null,
			"endsAt" => null,
		];
	}

	private static function at(int $day, int $month, int $year, bool $endOfDay = false): Time
	{
		$time = $endOfDay ? "18:00:00" : "10:00:00";

		return new Time(sprintf("%04d-%02d-%02d %s", $year, $month, $day, $time));
	}

	/**
	 * @return array<string, int>
	 */
	private static function months(): array
	{
		return [
			"ledna" => 1,
			"unora" => 2,
			"února" => 2,
			"brezna" => 3,
			"března" => 3,
			"dubna" => 4,
			"kvetna" => 5,
			"května" => 5,
			"cervna" => 6,
			"června" => 6,
			"cervence" => 7,
			"července" => 7,
			"srpna" => 8,
			"zari" => 9,
			"září" => 9,
			"rijna" => 10,
			"října" => 10,
			"listopadu" => 11,
			"prosince" => 12,
		];
	}
}
