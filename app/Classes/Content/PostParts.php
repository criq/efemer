<?php

namespace App\Classes\Content;

use App\Classes\Html\Excerpt;
use App\Classes\Html\Sanitizer;

/**
 * Contract for posts.layoutJson.parts — ordered page content.
 *
 * Shape:
 * {
 *   "pattern": "hero_text_gallery"|…,
 *   "parts": [
 *     { "type": "image", "postFileId": 12 },
 *     { "type": "text", "html": "<p>…</p>" },
 *     { "type": "gallery", "postFileIds": [16, 17, 18] }
 *   ]
 * }
 *
 * post_files holds the selected ordered photos (subset of ingest).
 * Parts reference postFileId (stable after materialize), not ingest ids.
 * bodyHtml / excerpt are derived: body = concat of text parts; excerpt = first text.
 */
class PostParts
{
	public const TYPE_TEXT = "text";
	public const TYPE_IMAGE = "image";
	public const TYPE_GALLERY = "gallery";

	/**
	 * @return list<array{type: string, html?: string, postFileId?: int, postFileIds?: list<int>}>
	 */
	public static function fromLayout(?array $layout): array
	{
		if (!is_array($layout)) {
			return [];
		}
		$parts = $layout["parts"] ?? null;
		if (!is_array($parts)) {
			return [];
		}

		$out = [];
		foreach ($parts as $part) {
			if (!is_array($part)) {
				continue;
			}
			$normalized = self::normalizePart($part);
			if ($normalized !== null) {
				$out[] = $normalized;
			}
		}

		return $out;
	}

	/**
	 * @param array<string, mixed> $part
	 * @return array{type: string, html?: string, postFileId?: int, postFileIds?: list<int>}|null
	 */
	public static function normalizePart(array $part): ?array
	{
		$type = (string)($part["type"] ?? "");
		if ($type === self::TYPE_TEXT) {
			$html = Sanitizer::clean((string)($part["html"] ?? ""));

			return ["type" => self::TYPE_TEXT, "html" => $html];
		}
		if ($type === self::TYPE_IMAGE) {
			$id = (int)($part["postFileId"] ?? 0);
			if ($id < 1) {
				return null;
			}

			return ["type" => self::TYPE_IMAGE, "postFileId" => $id];
		}
		if ($type === self::TYPE_GALLERY) {
			$ids = [];
			foreach ((array)($part["postFileIds"] ?? []) as $id) {
				$id = (int)$id;
				if ($id > 0) {
					$ids[] = $id;
				}
			}
			$ids = array_values(array_unique($ids));
			if ($ids === []) {
				return null;
			}

			return ["type" => self::TYPE_GALLERY, "postFileIds" => $ids];
		}

		return null;
	}

	/**
	 * @param list<array{type: string, html?: string, postFileId?: int, postFileIds?: list<int>}> $parts
	 */
	public static function bodyHtmlFromParts(array $parts): ?string
	{
		$chunks = [];
		foreach ($parts as $part) {
			if (($part["type"] ?? "") === self::TYPE_TEXT) {
				$html = trim((string)($part["html"] ?? ""));
				if ($html !== "") {
					$chunks[] = $html;
				}
			}
		}
		if ($chunks === []) {
			return null;
		}

		return implode("\n", $chunks);
	}

	/**
	 * @param list<array{type: string, html?: string, postFileId?: int, postFileIds?: list<int>}> $parts
	 */
	public static function excerptFromParts(array $parts, int $limit = 220): ?string
	{
		foreach ($parts as $part) {
			if (($part["type"] ?? "") !== self::TYPE_TEXT) {
				continue;
			}
			$excerpt = Excerpt::fromHtml((string)($part["html"] ?? ""), $limit);
			if ($excerpt !== "") {
				return $excerpt;
			}
		}

		return null;
	}

	/**
	 * Drop image/gallery refs that no longer exist on the post.
	 *
	 * @param list<array{type: string, html?: string, postFileId?: int, postFileIds?: list<int>}> $parts
	 * @param array<int, true> $validPostFileIds
	 * @return list<array{type: string, html?: string, postFileId?: int, postFileIds?: list<int>}>
	 */
	public static function scrubMissingFiles(array $parts, array $validPostFileIds): array
	{
		$out = [];
		foreach ($parts as $part) {
			$type = $part["type"] ?? "";
			if ($type === self::TYPE_IMAGE) {
				$id = (int)($part["postFileId"] ?? 0);
				if (!isset($validPostFileIds[$id])) {
					continue;
				}
				$out[] = $part;
				continue;
			}
			if ($type === self::TYPE_GALLERY) {
				$ids = [];
				foreach ((array)($part["postFileIds"] ?? []) as $id) {
					$id = (int)$id;
					if (isset($validPostFileIds[$id])) {
						$ids[] = $id;
					}
				}
				if ($ids === []) {
					continue;
				}
				$part["postFileIds"] = $ids;
				$out[] = $part;
				continue;
			}
			$out[] = $part;
		}

		return $out;
	}

	/**
	 * Resolve parts for Twig: attach PostFile objects.
	 *
	 * @return list<array{type: string, html?: string, file?: PostFile|null, files?: list<PostFile>}>
	 */
	public static function resolveForRender(Post $post): array
	{
		$layout = $post->getLayoutJson();
		$parts = self::fromLayout($layout);
		$byId = [];
		foreach ($post->getFiles() as $file) {
			$byId[(int)$file->getId()] = $file;
		}

		if ($parts === []) {
			return self::stripLeadingTitleFromResolved(
				self::fallbackResolved($post, $byId),
				(string)$post->getTitle(),
			);
		}

		$resolved = [];
		foreach ($parts as $part) {
			$type = $part["type"];
			if ($type === self::TYPE_TEXT) {
				$html = trim((string)($part["html"] ?? ""));
				if ($html === "") {
					continue;
				}
				$resolved[] = ["type" => self::TYPE_TEXT, "html" => $html];
				continue;
			}
			if ($type === self::TYPE_IMAGE) {
				$id = (int)$part["postFileId"];
				$file = $byId[$id] ?? null;
				if (!$file || !$file->getImage()) {
					continue;
				}
				$resolved[] = ["type" => self::TYPE_IMAGE, "file" => $file];
				continue;
			}
			if ($type === self::TYPE_GALLERY) {
				$files = [];
				foreach ($part["postFileIds"] as $id) {
					$file = $byId[(int)$id] ?? null;
					if ($file && $file->getImage()) {
						$files[] = $file;
					}
				}
				if ($files === []) {
					continue;
				}
				$resolved[] = ["type" => self::TYPE_GALLERY, "files" => $files];
			}
		}

		return self::stripLeadingTitleFromResolved($resolved, (string)$post->getTitle());
	}

	/**
	 * Drop a leading body line that only repeats the public title (common for diary dates).
	 *
	 * @param list<array{type: string, html?: string, file?: PostFile|null, files?: list<PostFile>}> $resolved
	 * @return list<array{type: string, html?: string, file?: PostFile|null, files?: list<PostFile>}>
	 */
	public static function stripLeadingTitleFromResolved(array $resolved, string $title): array
	{
		$titlePlain = self::normalizeComparableText($title);
		if ($titlePlain === "") {
			return $resolved;
		}

		foreach ($resolved as $i => $part) {
			if (($part["type"] ?? "") !== self::TYPE_TEXT) {
				continue;
			}
			$html = self::stripLeadingTitleFromHtml((string)($part["html"] ?? ""), $titlePlain);
			if ($html === "") {
				unset($resolved[$i]);
			} else {
				$resolved[$i]["html"] = $html;
			}
			break;
		}

		return array_values($resolved);
	}

	/**
	 * Remove a leading block / first line that equals the title (date or otherwise).
	 */
	public static function stripLeadingTitleFromHtml(string $html, string $titlePlain): string
	{
		$html = trim($html);
		$titlePlain = self::normalizeComparableText($titlePlain);
		if ($html === "" || $titlePlain === "") {
			return $html;
		}

		// Whole first block is only the title (optionally wrapped in em/strong/span).
		$blockPattern = "/^\\s*<(p|div|h[1-6])(\\s[^>]*)?>\\s*(?:<(?:strong|em|b|i|span)(\\s[^>]*)?>\\s*)*"
			. self::flexibleLiteralPattern($titlePlain)
			. "\\s*(?:<\\/(?:strong|em|b|i|span)>\\s*)*<\\/\\1>\\s*/iu";
		$stripped = preg_replace($blockPattern, "", $html, 1, $count);
		if (is_string($stripped) && $count > 0) {
			return trim($stripped);
		}

		// Title as the first line inside a block, before <br> or another block.
		$linePattern = "/^(\\s*<(p|div|h[1-6])(\\s[^>]*)?>\\s*(?:<(?:strong|em|b|i|span)(\\s[^>]*)?>\\s*)*)"
			. self::flexibleLiteralPattern($titlePlain)
			. "\\s*(?:<\\/(?:strong|em|b|i|span)>\\s*)*(?:<br\\s*\\/?>\\s*|\\n+)/iu";
		$stripped = preg_replace($linePattern, "\$1", $html, 1, $count);
		if (is_string($stripped) && $count > 0) {
			return trim($stripped);
		}

		return $html;
	}

	private static function normalizeComparableText(string $raw): string
	{
		$text = trim(html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, "UTF-8"));
		$text = preg_replace("/\\s+/u", " ", $text) ?? $text;

		return trim($text);
	}

	private static function flexibleLiteralPattern(string $plain): string
	{
		// Match the title with flexible whitespace (e.g. "26. 5. 2026" ↔ "26.  5. 2026").
		$parts = preg_split("/\\s+/u", $plain) ?: [];
		$escaped = array_map(static fn(string $part): string => preg_quote($part, "/"), $parts);

		return implode("\\s+", $escaped);
	}

	/**
	 * @param array<int, PostFile> $byId
	 * @return list<array{type: string, html?: string, file?: PostFile|null, files?: list<PostFile>}>
	 */
	private static function fallbackResolved(Post $post, array $byId): array
	{
		$resolved = [];
		$files = array_values($byId);
		if ($files !== []) {
			$resolved[] = ["type" => self::TYPE_IMAGE, "file" => $files[0]];
		}
		$body = trim((string)$post->getBodyHtml());
		if ($body !== "") {
			$resolved[] = ["type" => self::TYPE_TEXT, "html" => $body];
		}
		$rest = array_slice($files, 1);
		if ($rest !== []) {
			$resolved[] = ["type" => self::TYPE_GALLERY, "files" => $rest];
		}

		return $resolved;
	}
}
