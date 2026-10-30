<?php

namespace App\Classes\Content;

/**
 * Controlled content tags (not free keywords).
 * Source of truth for invitation vs event-report; isInvitation is a synced cache.
 */
class Tags
{
	public const INVITATION = "invitation";
	public const REPORT = "report";

	/** @var list<string> */
	public const ALLOWED = [
		self::INVITATION,
		self::REPORT,
	];

	/**
	 * Normalize raw tags to the allowlist. invitation and report are mutually exclusive;
	 * invitation wins when both appear.
	 *
	 * @param mixed $raw
	 * @return list<string>
	 */
	public static function normalize(mixed $raw): array
	{
		$candidates = [];
		if (is_string($raw)) {
			$decoded = json_decode($raw, true);
			if (is_array($decoded)) {
				$raw = $decoded;
			} else {
				$raw = preg_split("/\s*,\s*/", $raw) ?: [];
			}
		}
		if (!is_array($raw)) {
			return [];
		}

		foreach ($raw as $value) {
			if (!is_string($value)) {
				continue;
			}
			$tag = strtolower(trim($value));
			if ($tag === "" || !in_array($tag, self::ALLOWED, true)) {
				continue;
			}
			$candidates[] = $tag;
		}

		$candidates = array_values(array_unique($candidates));
		if (in_array(self::INVITATION, $candidates, true) && in_array(self::REPORT, $candidates, true)) {
			$candidates = array_values(array_filter(
				$candidates,
				fn (string $tag) => $tag !== self::REPORT,
			));
		}

		sort($candidates);

		return $candidates;
	}

	/**
	 * @param list<string> $tags
	 */
	public static function encode(array $tags): ?string
	{
		$tags = self::normalize($tags);

		return $tags === [] ? null : json_encode($tags, JSON_UNESCAPED_UNICODE);
	}

	/**
	 * @return list<string>
	 */
	public static function decode(?string $json): array
	{
		if ($json === null || $json === "") {
			return [];
		}

		$decoded = json_decode($json, true);

		return self::normalize(is_array($decoded) ? $decoded : []);
	}

	/**
	 * @param list<string>|string|null $tags
	 */
	public static function has(array|string|null $tags, string $tag): bool
	{
		$list = is_array($tags) ? self::normalize($tags) : self::decode(is_string($tags) ? $tags : null);

		return in_array($tag, $list, true);
	}

	/**
	 * Czech labels for admin UI.
	 *
	 * @return array<string, string>
	 */
	public static function labels(): array
	{
		return [
			self::INVITATION => "pozvánka",
			self::REPORT => "reportáž",
		];
	}
}
