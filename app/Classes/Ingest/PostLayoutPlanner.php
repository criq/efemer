<?php

namespace App\Classes\Ingest;

use App\Classes\Content\PostParts;
use App\Classes\Html\Sanitizer;
use App\Classes\Jobs\ConsoleStyle;
use App\Classes\ThirdParty\Google\Gemini;

/**
 * Gemini-backed layout for public post parts: hero excerpt + interleaved text/photos.
 */
class PostLayoutPlanner
{
	private const MAX_CANDIDATES = 16;
	private const MAX_IMAGE_EDGE = 768;
	private const JPEG_QUALITY = 72;
	private const MAX_BODY_CHARS = 12000;

	private Gemini $gemini;

	/** @var list<callable(string): void> */
	private array $loggers = [];

	public function __construct(?Gemini $gemini = null)
	{
		$this->gemini = $gemini ?? new Gemini;
	}

	public function onLog(callable $logger): self
	{
		$this->loggers[] = $logger;
		$this->gemini->onLog($logger);

		return $this;
	}

	/**
	 * @param list<IngestItemFile> $candidates ordered photo candidates
	 * @return array{excerpt: ?string, parts: list<array{type: string, html?: string, fileIndex?: int, fileIndexes?: list<int>}>}
	 */
	public function plan(IngestItem $item, array $candidates): array
	{
		$candidates = array_values(array_slice($candidates, 0, self::MAX_CANDIDATES));
		$bodyHtml = Sanitizer::clean($item->getBodyHtml()) ?: "";

		try {
			$planned = $this->planWithGemini($item, $candidates, $bodyHtml);
			$this->log(ConsoleStyle::step(sprintf(
				"LLM layout: excerpt %s, %d parts",
				$planned["excerpt"] !== null ? "yes" : "no",
				count($planned["parts"]),
			)));

			return $planned;
		} catch (\Throwable $exception) {
			$this->log(ConsoleStyle::warn("LLM layout failed: " . $exception->getMessage() . " — heuristic fallback"));

			return $this->planHeuristic($item, $candidates, $bodyHtml);
		}
	}

	/**
	 * @param list<IngestItemFile> $candidates
	 * @return array{excerpt: ?string, parts: list<array{type: string, html?: string, fileIndex?: int, fileIndexes?: list<int>}>}
	 */
	private function planWithGemini(IngestItem $item, array $candidates, string $bodyHtml): array
	{
		$prepared = $this->prepareImages($candidates);
		$userParts = [];
		$userParts[] = ["text" => $this->buildUserPrompt($item, $candidates, $bodyHtml, $prepared)];
		foreach ($prepared as $image) {
			$userParts[] = [
				"inline_data" => [
					"mime_type" => "image/jpeg",
					"data" => $image["base64"],
				],
			];
			$userParts[] = ["text" => "[fileIndex={$image["index"]}]"];
		}

		$result = $this->gemini->generateContent($userParts, $this->systemInstruction());
		$payload = $this->decodeJson($result["text"]);

		return $this->normalizePlan($payload, $candidates, $bodyHtml);
	}

	private function systemInstruction(): string
	{
		return <<<'PROMPT'
You layout a public florist / cultural website page (Efemer, Czech).

Return JSON only:
{
  "excerpt": "short plain-text teaser for cards/listings, 1–2 sentences, Czech, no HTML",
  "parts": [
    { "type": "image", "fileIndex": 0 },
    { "type": "text", "html": "<p>…</p>" },
    { "type": "gallery", "fileIndexes": [2, 3, 4] }
  ]
}

Rules:
- parts must contain the COMPLETE body copy from the source (including the opening lines). Do not omit text just because it also appears in excerpt.
- excerpt is a short teaser for homepage/cards only — usually the opening idea, plain text. It is NOT shown as a separate block on the post page.
- Split body into several short text parts (roughly 1–3 sentences / ~60–180 words each). Prefer more shorter beats over one long wall.
- Keep Czech wording faithful; you may re-paragraph and wrap in <p> (optional <em>). Do not invent facts, dates, or places.
- Interleave photos with text: image, text, image|gallery, text… Do not dump all photos after one text blob.
- You do NOT need to use every photo. Curate: pick the strongest frames for the story (often ~4–8). Leave weak, redundant, or off-mood shots out entirely.
- Group related photos into one gallery block when they form a visual set (same place/moment/detail sequence). Use a single image for a strong standalone beat.
- Prefer cover/lead as the opening image when it fits. Story order over source order.
- First part should be type=image when you include any photos.
- fileIndex / fileIndexes must reference only provided indexes. Never invent indexes.
- Omit empty text.
PROMPT;
	}

	/**
	 * @param list<IngestItemFile> $candidates
	 * @param list<array{index: int, base64: string}> $prepared
	 */
	private function buildUserPrompt(IngestItem $item, array $candidates, string $bodyHtml, array $prepared): string
	{
		$bodyPlain = trim(html_entity_decode(strip_tags($bodyHtml), ENT_QUOTES | ENT_HTML5, "UTF-8"));
		if (mb_strlen($bodyPlain) > self::MAX_BODY_CHARS) {
			$bodyPlain = mb_substr($bodyPlain, 0, self::MAX_BODY_CHARS) . "…";
		}

		$lines = [];
		$lines[] = "Title: " . trim((string)$item->getTitle());
		$tags = $item->getTagsList();
		$lines[] = "Tags: " . ($tags !== [] ? implode(", ", $tags) : "none");
		$lines[] = "Invitation: " . ($item->isInvitation() ? "yes" : "no");
		if ($item->getPlace()) {
			$lines[] = "Place: " . $item->getPlace();
		}
		if ($item->getStartsAt()) {
			$lines[] = "Starts: " . $item->getStartsAt()->format("Y-m-d H:i");
		}
		$lines[] = "";
		$lines[] = "Body (source copy, Czech):";
		$lines[] = $bodyPlain !== "" ? $bodyPlain : "(no body text)";
		$lines[] = "";
		$lines[] = "Photos (candidates — curate; omit any that do not earn a place):";
		foreach ($candidates as $index => $file) {
			$lines[] = sprintf(
				"- fileIndex=%d id=%d role=%s rating=%s cover=%s caption=%s",
				$index,
				(int)$file->getId(),
				$file->getRole() ?? "—",
				$file->getRating() !== null ? (string)$file->getRating() : "—",
				$file->isCover() ? "yes" : "no",
				trim((string)$file->getCaption()) !== "" ? trim((string)$file->getCaption()) : "—",
			);
		}
		$lines[] = "";
		$lines[] = "Inline images attached for indexes: " . implode(", ", array_map(
			fn (array $img) => (string)$img["index"],
			$prepared,
		));
		$lines[] = "Remember: unused candidates are fine. Group related ones into gallery parts.";

		return implode("\n", $lines);
	}

	/**
	 * @param array<string, mixed> $payload
	 * @param list<IngestItemFile> $candidates
	 * @return array{excerpt: ?string, parts: list<array{type: string, html?: string, fileIndex?: int, fileIndexes?: list<int>}>}
	 */
	private function normalizePlan(array $payload, array $candidates, string $bodyHtml): array
	{
		$maxIndex = count($candidates) - 1;
		$excerpt = trim(html_entity_decode(strip_tags((string)($payload["excerpt"] ?? "")), ENT_QUOTES | ENT_HTML5, "UTF-8"));
		if ($excerpt === "") {
			$excerpt = null;
		} elseif (mb_strlen($excerpt) > 320) {
			$excerpt = rtrim(mb_substr($excerpt, 0, 317)) . "…";
		}

		$rawParts = $payload["parts"] ?? null;
		if (!is_array($rawParts) || $rawParts === []) {
			throw new \RuntimeException("LLM returned no parts");
		}

		$parts = [];
		$usedIndexes = [];
		foreach ($rawParts as $part) {
			if (!is_array($part)) {
				continue;
			}
			$type = (string)($part["type"] ?? "");
			if ($type === PostParts::TYPE_TEXT) {
				$html = Sanitizer::clean((string)($part["html"] ?? ""));
				$plain = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, "UTF-8"));
				if ($plain === "") {
					continue;
				}
				$parts[] = ["type" => PostParts::TYPE_TEXT, "html" => $html];
				continue;
			}
			if ($type === PostParts::TYPE_IMAGE) {
				$idx = (int)($part["fileIndex"] ?? -1);
				if ($idx < 0 || $idx > $maxIndex || isset($usedIndexes[$idx])) {
					continue;
				}
				$usedIndexes[$idx] = true;
				$parts[] = ["type" => PostParts::TYPE_IMAGE, "fileIndex" => $idx];
				continue;
			}
			if ($type === PostParts::TYPE_GALLERY) {
				$indexes = [];
				foreach ((array)($part["fileIndexes"] ?? []) as $idx) {
					$idx = (int)$idx;
					if ($idx < 0 || $idx > $maxIndex || isset($usedIndexes[$idx])) {
						continue;
					}
					$usedIndexes[$idx] = true;
					$indexes[] = $idx;
				}
				if ($indexes === []) {
					continue;
				}
				if (count($indexes) === 1) {
					$parts[] = ["type" => PostParts::TYPE_IMAGE, "fileIndex" => $indexes[0]];
				} else {
					$parts[] = ["type" => PostParts::TYPE_GALLERY, "fileIndexes" => $indexes];
				}
			}
		}

		// Intentionally do not force unused candidates into the page — curation is the LLM's job.

		if ($parts === []) {
			throw new \RuntimeException("LLM parts empty after normalize");
		}

		if ($excerpt === null && $bodyHtml !== "") {
			$excerpt = $this->fallbackExcerpt($bodyHtml);
		}

		return ["excerpt" => $excerpt, "parts" => $parts];
	}

	/**
	 * @param list<IngestItemFile> $candidates
	 * @return array{excerpt: ?string, parts: list<array{type: string, html?: string, fileIndex?: int, fileIndexes?: list<int>}>}
	 */
	private function planHeuristic(IngestItem $item, array $candidates, string $bodyHtml): array
	{
		unset($item);
		$paras = $this->paragraphs($bodyHtml);
		$excerpt = null;
		if ($paras !== []) {
			$excerptPlain = trim(html_entity_decode(strip_tags($paras[0]), ENT_QUOTES | ENT_HTML5, "UTF-8"));
			$excerpt = mb_strlen($excerptPlain) > 280
				? rtrim(mb_substr($excerptPlain, 0, 277)) . "…"
				: $excerptPlain;
		}

		// Keep full copy in parts (including opening used for excerpt).
		$chunks = [];
		$buffer = "";
		foreach ($paras as $p) {
			$plain = trim(html_entity_decode(strip_tags($p), ENT_QUOTES | ENT_HTML5, "UTF-8"));
			if ($plain === "") {
				continue;
			}
			if ($buffer === "") {
				$buffer = $p;
				if (mb_strlen($plain) >= 80) {
					$chunks[] = $buffer;
					$buffer = "";
				}
				continue;
			}
			$buffer .= "\n" . $p;
			$bufPlain = trim(html_entity_decode(strip_tags($buffer), ENT_QUOTES | ENT_HTML5, "UTF-8"));
			if (mb_strlen($bufPlain) >= 80) {
				$chunks[] = $buffer;
				$buffer = "";
			}
		}
		if ($buffer !== "") {
			$chunks[] = $buffer;
		}

		// Fallback also curates: hero + a few photos, not every candidate.
		$maxHeuristicPhotos = min(count($candidates), max(4, count($chunks) + 1));
		$parts = [];
		$fileCursor = 0;
		if ($maxHeuristicPhotos > 0) {
			$parts[] = ["type" => PostParts::TYPE_IMAGE, "fileIndex" => $fileCursor++];
		}

		$remainingFiles = max(0, $maxHeuristicPhotos - 1);
		$slots = max(count($chunks), 1);
		$perSlot = $slots > 0 ? (int)ceil($remainingFiles / $slots) : $remainingFiles;
		$perSlot = max(1, min(3, $perSlot));

		foreach ($chunks as $chunk) {
			$parts[] = ["type" => PostParts::TYPE_TEXT, "html" => $chunk];
			$batch = [];
			while ($fileCursor < $maxHeuristicPhotos && count($batch) < $perSlot) {
				$batch[] = $fileCursor++;
			}
			if (count($batch) === 1) {
				$parts[] = ["type" => PostParts::TYPE_IMAGE, "fileIndex" => $batch[0]];
			} elseif (count($batch) > 1) {
				$parts[] = ["type" => PostParts::TYPE_GALLERY, "fileIndexes" => $batch];
			}
		}

		if ($chunks === [] && $bodyHtml !== "" && $excerpt === null) {
			$excerpt = $this->fallbackExcerpt($bodyHtml);
		}

		return ["excerpt" => $excerpt, "parts" => $parts];
	}

	/**
	 * @return list<string>
	 */
	private function paragraphs(string $html): array
	{
		$html = trim($html);
		if ($html === "") {
			return [];
		}
		if (preg_match_all("/<p\\b[^>]*>.*?<\\/p>/is", $html, $matches)) {
			return array_values(array_filter(array_map("trim", $matches[0])));
		}
		$chunks = preg_split("/\\n\\s*\\n/u", $html) ?: [$html];
		$out = [];
		foreach ($chunks as $chunk) {
			$chunk = trim($chunk);
			if ($chunk === "") {
				continue;
			}
			$out[] = preg_match("/^</", $chunk) ? $chunk : "<p>{$chunk}</p>";
		}

		return $out;
	}

	private function fallbackExcerpt(string $bodyHtml): ?string
	{
		$plain = trim(html_entity_decode(strip_tags($bodyHtml), ENT_QUOTES | ENT_HTML5, "UTF-8"));
		if ($plain === "") {
			return null;
		}
		if (preg_match("/^(.+?[.!?])(\\s|$)/u", $plain, $match)) {
			$plain = $match[1];
		}
		if (mb_strlen($plain) > 280) {
			$plain = rtrim(mb_substr($plain, 0, 277)) . "…";
		}

		return $plain;
	}

	/**
	 * @param list<IngestItemFile> $files
	 * @return list<array{index: int, base64: string}>
	 */
	private function prepareImages(array $files): array
	{
		$images = [];
		foreach ($files as $index => $file) {
			try {
				$storage = $file->getStorageFile();
				$local = $storage->getFile();
				if (!$local || !$local->exists()) {
					continue;
				}
				$path = (string)$local;
				$mime = $this->detectMime($path);
				if (!in_array($mime, ["image/jpeg", "image/png", "image/webp", "image/gif"], true)) {
					continue;
				}
				$jpeg = $this->resizeToJpeg($path, $mime);
				if ($jpeg === null) {
					continue;
				}
				$images[] = [
					"index" => $index,
					"base64" => base64_encode($jpeg),
				];
			} catch (\Throwable) {
				continue;
			}
		}

		return $images;
	}

	private function detectMime(string $path): string
	{
		$mime = null;
		if (function_exists("mime_content_type")) {
			$mime = @mime_content_type($path);
		}
		if (!$mime && function_exists("finfo_open")) {
			$finfo = finfo_open(FILEINFO_MIME_TYPE);
			if ($finfo) {
				$mime = finfo_file($finfo, $path) ?: null;
				finfo_close($finfo);
			}
		}

		return is_string($mime) ? $mime : "application/octet-stream";
	}

	private function resizeToJpeg(string $path, string $mime): ?string
	{
		if (!function_exists("imagecreatefromstring")) {
			$raw = (string)file_get_contents($path);
			if ($raw === "" || strlen($raw) > 4_000_000) {
				return null;
			}

			return $mime === "image/jpeg" ? $raw : null;
		}

		$raw = (string)file_get_contents($path);
		if ($raw === "") {
			return null;
		}
		$image = @imagecreatefromstring($raw);
		if (!$image) {
			return null;
		}
		$width = imagesx($image);
		$height = imagesy($image);
		$max = self::MAX_IMAGE_EDGE;
		if ($width > $max || $height > $max) {
			$scale = min($max / $width, $max / $height);
			$newW = max(1, (int)round($width * $scale));
			$newH = max(1, (int)round($height * $scale));
			$resized = imagecreatetruecolor($newW, $newH);
			imagecopyresampled($resized, $image, 0, 0, 0, 0, $newW, $newH, $width, $height);
			imagedestroy($image);
			$image = $resized;
		}
		ob_start();
		imagejpeg($image, null, self::JPEG_QUALITY);
		$jpeg = ob_get_clean();
		imagedestroy($image);

		return is_string($jpeg) && $jpeg !== "" ? $jpeg : null;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function decodeJson(string $text): array
	{
		$text = trim($text);
		if (str_starts_with($text, "```")) {
			$text = preg_replace("/^```(?:json)?\s*/i", "", $text) ?? $text;
			$text = preg_replace("/\s*```$/", "", $text) ?? $text;
			$text = trim($text);
		}
		$decoded = json_decode($text, true);
		if (!is_array($decoded)) {
			throw new \RuntimeException("Could not parse Gemini JSON: " . mb_substr($text, 0, 200));
		}

		return $decoded;
	}

	private function log(string $message): void
	{
		foreach ($this->loggers as $logger) {
			$logger($message);
		}
	}
}
