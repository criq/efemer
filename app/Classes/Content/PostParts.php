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
			return self::fallbackResolved($post, $byId);
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

		return $resolved;
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
