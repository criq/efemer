<?php

namespace App\Classes\Ingest;

use App\Classes\Content\Tags;
use App\Classes\Jobs\ConsoleStyle;
use App\Classes\Jobs\Signals;
use App\Classes\ThirdParty\Google\Gemini;
use Katu\Tools\Calendar\Time;

class KeywordAnalyzer
{
	public const ENRICHMENT_VERSION = IngestItem::ENRICHMENT_VERSION;
	private const MAX_BODY_CHARS = 4000;
	private const MAX_IMAGE_EDGE = 1024;
	private const JPEG_QUALITY = 80;

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
	 * @return array{item: IngestItem, dryRunPayload?: array}
	 */
	public function analyze(IngestItem $item, int $maxImages = 12, bool $dryRun = false): array
	{
		Signals::throwIfStopped();

		$files = array_values($item->getFiles()->getArrayCopy());
		$prepared = $this->prepareImages($files, $maxImages);
		/** @var list<int> $blockedPositions */
		$blockedPositions = [];

		try {
			$result = $this->callGemini($item, $files, $prepared);
		} catch (\Throwable $exception) {
			if (!$this->isPromptBlocked($exception) || $prepared["images"] === []) {
				throw $exception;
			}

			$reason = $this->blockReason($exception);
			$this->log(ConsoleStyle::warn(
				"Gemini blocked multimodal prompt ({$reason}) — isolating safe photos.",
			));

			$allowed = $this->selectAllowedImages($prepared["images"]);
			$blockedPositions = $this->positionsNotIn($prepared["images"], $allowed);
			foreach ($blockedPositions as $position) {
				$this->log(ConsoleStyle::warn("Skipping blocked photo position={$position}."));
			}

			$prepared = [
				"images" => $allowed,
				"missing" => $prepared["missing"],
				"skippedVideo" => $prepared["skippedVideo"],
				"imagesOmitted" => $allowed === [],
				"blockedPositions" => $blockedPositions,
			];
			$result = $this->callGemini($item, $files, $prepared);
		}

		$usage = $result["usage"];
		$this->log(sprintf(
			"Tokens prompt=%s candidates=%s total=%s",
			$usage["promptTokens"] ?? "?",
			$usage["candidatesTokens"] ?? "?",
			$usage["totalTokens"] ?? "?",
		));

		$payload = $this->decodeJson($result["text"]);
		if ($dryRun) {
			$this->log(ConsoleStyle::muted("dryRun JSON: " . mb_substr(json_encode($payload, JSON_UNESCAPED_UNICODE), 0, 2000)));

			return ["item" => $item, "dryRunPayload" => $payload];
		}

		if ($blockedPositions !== []) {
			$flags = $item->getQualityFlags();
			if (!in_array("llm_images_blocked", $flags, true)) {
				$flags[] = "llm_images_blocked";
				$item->setQualityFlags($flags);
			}
		}

		$this->apply($item, $files, $payload, $result["text"]);
		if ($blockedPositions !== []) {
			$this->markBlockedFiles($files, $blockedPositions, $item);
		}

		// Deterministic QC immediately after LLM apply (typos, cover, roles, pattern).
		$item = IngestItem::get((int)$item->getId()) ?: $item;
		$qc = (new KeywordQc)->review($item, true);
		foreach ($qc["fixed"] as $line) {
			$this->log(ConsoleStyle::ok("QC") . " " . $line);
		}
		foreach ($qc["issues"] as $line) {
			$this->log(ConsoleStyle::warn("QC: " . $line));
		}
		if ($qc["needsRekeyword"]) {
			$this->log(ConsoleStyle::warn("QC: flagged needs_rekeyword"));
		}

		return ["item" => IngestItem::get((int)$item->getId()) ?: $item];
	}

	/**
	 * @param list<IngestItemFile> $files
	 * @param array{images: list<array{index: int, position: int, base64: string}>, missing: list<int>, skippedVideo: list<int>, imagesOmitted?: bool, blockedPositions?: list<int>} $prepared
	 * @return array{text: string, usage: array<string, mixed>, raw: array<string, mixed>}
	 */
	private function callGemini(IngestItem $item, array $files, array $prepared): array
	{
		$parts = [];
		$parts[] = ["text" => $this->buildUserPrompt($item, $files, $prepared)];
		foreach ($prepared["images"] as $image) {
			$parts[] = [
				"inline_data" => [
					"mime_type" => "image/jpeg",
					"data" => $image["base64"],
				],
			];
			$parts[] = ["text" => "[photo position={$image["position"]} fileIndex={$image["index"]}]"];
		}

		return $this->gemini->generateContent($parts, $this->systemInstruction());
	}

	/**
	 * Binary-partition probe: keep photos that pass the safety filter alone or in a safe group.
	 *
	 * @param list<array{index: int, position: int, base64: string}> $images
	 * @return list<array{index: int, position: int, base64: string}>
	 */
	private function selectAllowedImages(array $images): array
	{
		if ($images === []) {
			return [];
		}

		Signals::throwIfStopped();

		if ($this->probeImagesAllowed($images)) {
			return $images;
		}

		if (count($images) === 1) {
			return [];
		}

		$mid = intdiv(count($images), 2);

		return array_values(array_merge(
			$this->selectAllowedImages(array_slice($images, 0, $mid)),
			$this->selectAllowedImages(array_slice($images, $mid)),
		));
	}

	/**
	 * Cheap multimodal ping — only checks whether the image set is accepted.
	 *
	 * @param list<array{index: int, position: int, base64: string}> $images
	 */
	private function probeImagesAllowed(array $images): bool
	{
		$parts = [
			["text" => "Safety probe only. Respond with JSON {\"ok\":true}."],
		];
		foreach ($images as $image) {
			$parts[] = [
				"inline_data" => [
					"mime_type" => "image/jpeg",
					"data" => $image["base64"],
				],
			];
		}

		try {
			$this->gemini->generateContent($parts, null);

			return true;
		} catch (\Throwable $exception) {
			if ($this->isPromptBlocked($exception)) {
				return false;
			}

			throw $exception;
		}
	}

	/**
	 * @param list<array{index: int, position: int, base64: string}> $all
	 * @param list<array{index: int, position: int, base64: string}> $allowed
	 * @return list<int>
	 */
	private function positionsNotIn(array $all, array $allowed): array
	{
		$allowedPositions = [];
		foreach ($allowed as $image) {
			$allowedPositions[(int)$image["position"]] = true;
		}

		$blocked = [];
		foreach ($all as $image) {
			$position = (int)$image["position"];
			if (!isset($allowedPositions[$position])) {
				$blocked[] = $position;
			}
		}

		return $blocked;
	}

	/**
	 * @param list<IngestItemFile> $files
	 * @param list<int> $blockedPositions
	 */
	private function markBlockedFiles(array $files, array $blockedPositions, IngestItem $item): void
	{
		$blockedSet = array_fill_keys($blockedPositions, true);
		$coverWasBlocked = false;

		foreach ($files as $index => $file) {
			$position = (int)($file->getPosition() ?? ($index + 1));
			if (!isset($blockedSet[$position])) {
				continue;
			}

			$file->setDemote(true);
			if (!$file->isRoleLocked()) {
				$file->setRole(IngestItemFile::ROLE_SKIP);
				$file->setRoleRationale("Vyřazeno: Gemini safety filter");
			}
			if ($file->isCover()) {
				$file->setIsCover(false);
				$coverWasBlocked = true;
			}
			$file->persist();
		}

		if (!$coverWasBlocked || $item->isCoverLocked()) {
			return;
		}

		$cover = $this->resolveCover($files, null);
		foreach ($files as $file) {
			$isCover = $cover && (int)$file->getId() === (int)$cover->getId();
			$file->setIsCover($isCover);
			$file->persist();
		}
		$item->setCoverFileId($cover ? (int)$cover->getId() : null);
		$rationale = trim((string)$item->getCoverRationale());
		$suffix = "(cover přepočten po safety filtru)";
		if (!str_contains($rationale, $suffix)) {
			$item->setCoverRationale(trim(($rationale !== "" ? $rationale . " " : "") . $suffix));
		}
		$item->persist();
	}

	private function isPromptBlocked(\Throwable $exception): bool
	{
		$message = $exception->getMessage();

		return str_contains($message, "blocked: PROHIBITED_CONTENT")
			|| str_contains($message, "blocked: SAFETY")
			|| str_contains($message, "blocked: BLOCKLIST")
			|| str_contains($message, "blocked: OTHER")
			|| str_contains($message, "blocked: IMAGE_SAFETY");
	}

	private function blockReason(\Throwable $exception): string
	{
		if (preg_match("/blocked:\s*(\w+)/", $exception->getMessage(), $match)) {
			return $match[1];
		}

		return "unknown";
	}

	/**
	 * @param list<IngestItemFile> $files
	 * @return array{images: list<array{index: int, position: int, base64: string}>, missing: list<int>, skippedVideo: list<int>}
	 */
	private function prepareImages(array $files, int $maxImages): array
	{
		$images = [];
		$missing = [];
		$skippedVideo = [];

		foreach ($files as $index => $file) {
			if (count($images) >= $maxImages) {
				break;
			}

			$position = (int)($file->getPosition() ?? ($index + 1));
			try {
				$storage = $file->getStorageFile();
				$local = $storage->getFile();
				if (!$local || !$local->exists()) {
					$missing[] = $position;
					continue;
				}

				$path = (string)$local;
				$mime = $this->detectMime($path);
				if (str_starts_with($mime, "video/")) {
					$skippedVideo[] = $position;
					continue;
				}
				if (!in_array($mime, ["image/jpeg", "image/png", "image/webp", "image/gif"], true)) {
					$missing[] = $position;
					continue;
				}

				$jpeg = $this->resizeToJpeg($path, $mime);
				if ($jpeg === null) {
					$missing[] = $position;
					continue;
				}

				$images[] = [
					"index" => $index,
					"position" => $position,
					"base64" => base64_encode($jpeg),
				];
			} catch (\Throwable) {
				$missing[] = $position;
			}
		}

		return [
			"images" => $images,
			"missing" => $missing,
			"skippedVideo" => $skippedVideo,
		];
	}

	private function detectMime(string $path): string
	{
		$info = @getimagesize($path);
		if (is_array($info) && !empty($info["mime"])) {
			return (string)$info["mime"];
		}

		$finfo = new \finfo(FILEINFO_MIME_TYPE);
		$mime = $finfo->file($path);

		return is_string($mime) ? $mime : "application/octet-stream";
	}

	private function resizeToJpeg(string $path, string $mime): ?string
	{
		if (!function_exists("imagecreatefromstring")) {
			$raw = (string)file_get_contents($path);
			if ($raw === "" || strlen($raw) > 4_000_000) {
				return null;
			}
			if ($mime === "image/jpeg") {
				return $raw;
			}

			return null;
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
	 * @param list<IngestItemFile> $files
	 * @param array{images: list<array{index: int, position: int, base64: string}>, missing: list<int>, skippedVideo: list<int>, imagesOmitted?: bool, blockedPositions?: list<int>} $prepared
	 */
	private function buildUserPrompt(IngestItem $item, array $files, array $prepared): string
	{
		$body = trim(html_entity_decode(strip_tags((string)$item->getBodyHtml()), ENT_QUOTES | ENT_HTML5, "UTF-8"));
		if (mb_strlen($body) > self::MAX_BODY_CHARS) {
			$body = mb_substr($body, 0, self::MAX_BODY_CHARS) . "…";
		}

		$fileLines = [];
		foreach ($files as $index => $file) {
			$fileLines[] = sprintf(
				"- index=%d id=%d position=%d sourceSlot=%s galleryIndex=%s",
				$index,
				(int)$file->getId(),
				(int)($file->getPosition() ?? ($index + 1)),
				$file->getSourceSlot() ?: "unknown",
				$file->getGalleryIndex() === null ? "null" : (string)$file->getGalleryIndex(),
			);
		}

		$attached = array_map(
			fn (array $img) => "position={$img["position"]} fileIndex={$img["index"]}",
			$prepared["images"],
		);

		$lines = [
			"Analyze this florist / floral-art ingest item.",
			"Source: " . $item->getSourceLabel(),
			"Posted at (reference year/date): " . ($item->getPostedAt() ? $item->getPostedAt()->format("Y-m-d H:i") : "(unknown)"),
			"Title: " . ($item->getTitle() ?: "(none)"),
			"Quality flags: " . implode(", ", $item->getQualityFlags() ?: ["(none)"]),
			"Body text (Czech, may be empty):",
			$body !== "" ? $body : "(empty)",
			"",
			"Files (all):",
			...$fileLines,
			"",
			"Attached images (in order after this text): " . ($attached ? implode("; ", $attached) : "(none)"),
			"Missing downloads: " . ($prepared["missing"] ? implode(", ", $prepared["missing"]) : "(none)"),
			"Skipped videos: " . ($prepared["skippedVideo"] ? implode(", ", $prepared["skippedVideo"]) : "(none)"),
		];

		$blockedPositions = isset($prepared["blockedPositions"]) && is_array($prepared["blockedPositions"])
			? $prepared["blockedPositions"]
			: [];
		if ($blockedPositions !== [] || !empty($prepared["imagesOmitted"])) {
			$lines[] = "";
			if (!empty($prepared["imagesOmitted"])) {
				$lines[] = "NOTE: All photos were omitted — multimodal request blocked by the model safety filter (often baptism / infant / child portraits). Infer keywords/themes/kind from the title and file list only. Prefer coverFileId = first non-blocked pre_copy file id. For files[], use conservative roles; do not invent people details.";
			} else {
				$lines[] = "NOTE: Some photos were omitted after the safety filter blocked them (positions: "
					. implode(", ", $blockedPositions)
					. "). Analyze only the attached images. Mark omitted file ids as demote=true, role=skip. Prefer coverFileId among attached/safe files (still prefer pre_copy when attached).";
			}
		}

		$lines[] = "";
		$lines[] = "Return JSON only matching the schema in the system instruction.";
		$lines[] = "coverFileId must be an ingest_item_files id from the list above (or null).";
		$lines[] = "layoutHints.blocks[].fileIds must use those same file ids.";

		return implode("\n", $lines);
	}

	private function systemInstruction(): string
	{
		return <<<'PROMPT'
You enrich floral / wedding / art content for the Czech studio Efemér.
Respond with a single JSON object (no markdown) using this shape:
{
  "keywords": ["česká klíčová slova"],
  "themes": ["témata"],
  "entities": ["jména lidí, míst, rostlin"],
  "kind": "diary|wedding|gift|portrait|workshop|installation|bouquet|product|other",
  "couple": "Jana a Petr",
  "coverFileId": 123,
  "coverRationale": "krátky česky důvod",
  "palette": ["#aabbcc"],
  "seriesKey": null,
  "tags": [],
  "timeframe": {
    "startsAt": "YYYY-MM-DD HH:MM:SS or null",
    "endsAt": "YYYY-MM-DD HH:MM:SS or null",
    "place": "místo konání nebo null"
  },
  "layoutHints": {
    "pattern": "hero_text_gallery|text_lead_gallery|gallery_essay|single",
    "blocks": [
      {"type": "image", "fileIds": [123], "role": "lead"},
      {"type": "text", "from": "body"},
      {"type": "gallery", "fileIds": [124, 125], "role": "support"}
    ]
  },
  "files": [
    {
      "fileId": 123,
      "keywords": ["specifická klíčová slova k této fotce"],
      "rating": 0.0,
      "role": "lead|support|detail|context|skip",
      "roleRationale": "česky",
      "altText": "český alt",
      "orientation": "landscape|portrait|square",
      "focalCrop": {"x": 0.5, "y": 0.4, "zoom": 1.0},
      "demote": false
    }
  ]
}

Rules:
- Item keywords/themes/entities and per-file keywords, altText, rationales in correct Czech (Latin script only — never Cyrillic). Spell-check; write floristika correctly (not florika, florisitika, or garbled stems).
- Keywords: lowercase except proper names / place names. Avoid duplicate casing variants.
- Each file needs 3–8 specific keywords (plants, objects, people, mood, place) — not only copies of item keywords.
- kind:
  - wedding = ONLY an actual wedding / bridal ritual / novomanželé / svatební obřad (ceremony, bridal bouquet for that wedding, wedding veil, newlyweds). Not masopust, carnival, birthday, výročí party, seasonal gathering, or "veselka" that is not a wedding. Mentioning a past wedding in passing does not make the post wedding.
  - workshop = public gathering, workshop, slavnost, masopust, pouť, vycházka, brigáda, pop-up, seasonal feast, or event invitation where people join (e.g. "zveme", "sraz je", "připojte se", Kupalo, Wanderlust, Ciderfest, masopustní veselky)
  - gift = dar / kytice pro mámu / birthday / named gift or tribute bouquet (including farewell/memorial bouquets for a named person)
  - portrait = person-focused floral portrait without clear wedding
  - bouquet = standalone authored bouquet / commission (not a shop SKU)
  - product = only shop-like / catalog product shots
  - installation / diary / other as appropriate
  - Prefer workshop for masopust / slavnost / seasonal feast write-ups. Prefer gift|portrait|bouquet over diary when those fit better. Never use wedding for carnival or non-bridal veselky.
- couple:
  - When kind=wedding, set couple to the newlyweds' names in Czech form ("Veru a Jindra", "Marie a Tomáš") when identifiable from body, captions, @mentions, or photos. Prefer first names; use "a" between them. One known name alone is OK. Never put raw Instagram handles in couple — resolve to a real name if stated. Also include those names in entities.
  - For every other kind, couple must be null.
- tags (controlled; empty array when neither fits; never invent free keywords here):
  - "invitation" — the post invites readers to an upcoming or current event/workshop/walk/slavnost/pop-up ("zveme", "připojte se", "sraz", "vstupenky", poster with future date/time/venue). Mutually exclusive with "report".
  - "report" — a lasting write-up / photo essay about an event that already happened (workshop recap, slavnost reportáž, after the gathering). Not an open call to attend. Prefer report over invitation when the text is past-tense storytelling rather than a call to join.
  - Do not put both invitation and report. Leave tags=[] for ordinary diary/work posts with no event framing.
- timeframe:
  - Extract any event / happening / diary date or date range mentioned in the text OR visible on poster/flyer images (e.g. "1. 11." on a poster, "19. 9.* 5.-10.10. 2025", "v neděli o Všech svatých v 9:45"). Resolve missing years or Czech holidays (Všech svatých = Nov 1, Dušičky = Nov 2, Filipojakubská noc = Apr 30, Letní slunovrat = Jun 21, Svatá Lucie = Dec 13) using "Posted at" as the reference year.
  - Set place to the venue/town when known (especially for invitation or report).
- Role and pattern codes in English as above.
- sourceSlot=pre_copy ⇒ role=lead (strong). post_copy ⇒ support|detail|context (lead only if truly a second story beat).
- COVER (critical): if any non-demoted pre_copy file exists, coverFileId MUST be one of those pre_copy ids (prefer the best lead among them). Never pick a post_copy cover when pre_copy exists. Cover MUST be a photograph of people/place/plants — never a typography poster, title card, or flyer.
- demote=true ⇒ role=skip for: blurry, duplicate, meme, off-brand, accidental frames, AND any image that is primarily graphic/typography (Instagram title cards, event posters with large overlaid text/dates, flyer slides, solid-color text cards). These must never be cover and never appear in public galleries. Rate them ≤0.3.
- rating calibration: 0.9–1.0 rare photographic hero; ~0.7 solid photo; ~0.5 ordinary filler; ≤0.4 weak / demote candidates; typography slides ≤0.3 + demote.
- layout pattern:
  - hero_text_gallery when there is real body text AND a pre_copy lead
  - text_lead_gallery when body opens and lead comes after
  - gallery_essay only for thin_text / image-led posts with little prose
  - single when essentially one frame
- Include an entry in files[] for every listed file id when possible.
- Do not invent file ids.
PROMPT;
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

	/**
	 * @param list<IngestItemFile> $files
	 * @param array<string, mixed> $payload
	 */
	private function apply(IngestItem $item, array $files, array $payload, string $rawText): void
	{
		$byId = [];
		foreach ($files as $file) {
			$byId[(int)$file->getId()] = $file;
		}

		$item->setKeywordsList(KeywordNormalizer::normalizeList($payload["keywords"] ?? null));
		$item->setThemesList(KeywordNormalizer::normalizeList($payload["themes"] ?? null));
		$entities = KeywordNormalizer::normalizeList($payload["entities"] ?? null, preserveCase: true);
		$kind = $this->normalizeKind($payload["kind"] ?? null);
		$couple = $this->normalizeCouple($payload["couple"] ?? null, $kind);
		if ($couple !== null) {
			foreach (preg_split("/\s+a\s+/ui", $couple) ?: [] as $name) {
				$name = trim((string)$name);
				if ($name !== "" && !in_array($name, $entities, true)) {
					$entities[] = $name;
				}
			}
		}
		$item->setEntitiesList($entities);
		$item->setKind($kind);
		$item->setPalette($this->stringList($payload["palette"] ?? null));
		$seriesKey = isset($payload["seriesKey"]) && is_string($payload["seriesKey"]) ? trim($payload["seriesKey"]) : null;
		$item->setSeriesKey($seriesKey !== "" ? $seriesKey : null);
		$item->setLayoutHints($this->normalizeLayoutHints(
			isset($payload["layoutHints"]) && is_array($payload["layoutHints"]) ? $payload["layoutHints"] : null,
			$item,
			$files,
		));
		$this->applyTimeframe($item, isset($payload["timeframe"]) && is_array($payload["timeframe"]) ? $payload["timeframe"] : null);
		$this->applyTags($item, $payload);
		$payload["couple"] = $couple;
		$encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		$item->setClassificationRaw(is_string($encoded) && $encoded !== "" ? $encoded : $rawText);

		$filePayloads = isset($payload["files"]) && is_array($payload["files"]) ? $payload["files"] : [];
		foreach ($files as $file) {
			$file->clearEnrichment(true);
		}

		foreach ($filePayloads as $row) {
			if (!is_array($row)) {
				continue;
			}
			$fileId = isset($row["fileId"]) ? (int)$row["fileId"] : 0;
			$file = $byId[$fileId] ?? null;
			if (!$file) {
				continue;
			}

			$demote = !empty($row["demote"]);
			$file->setDemote($demote);
			$file->setKeywords(KeywordNormalizer::normalizeList($row["keywords"] ?? null) ?: null);
			$file->setRating($this->clampRating($row["rating"] ?? null));
			$file->setAltText($this->nullableString($row["altText"] ?? null));
			$file->setOrientation($this->nullableString($row["orientation"] ?? null));
			if (isset($row["focalCrop"]) && is_array($row["focalCrop"])) {
				$file->setFocalCrop($row["focalCrop"]);
			} else {
				$file->setFocalCrop(null);
			}

			if (!$file->isRoleLocked()) {
				$role = $this->normalizeRole($row["role"] ?? null, $demote);
				// pre_copy stays lead unless demoted/skip.
				if (
					!$demote
					&& $file->getSourceSlot() === IngestItemFile::SLOT_PRE_COPY
					&& $role !== IngestItemFile::ROLE_SKIP
				) {
					$role = IngestItemFile::ROLE_LEAD;
				}
				$file->setRole($role);
				$file->setRoleRationale($this->nullableString($row["roleRationale"] ?? null));
			}

			$file->setIsCover(false);
			$file->persist();
		}

		foreach ($files as $file) {
			if ($file->getRole() === null && !$file->isRoleLocked()) {
				$prior = $file->getSourceSlot() === IngestItemFile::SLOT_PRE_COPY
					? IngestItemFile::ROLE_LEAD
					: IngestItemFile::ROLE_SUPPORT;
				$file->setRole($file->isDemoted() ? IngestItemFile::ROLE_SKIP : $prior);
				$file->persist();
			}
		}

		if (!$item->isCoverLocked()) {
			$coverId = isset($payload["coverFileId"]) ? (int)$payload["coverFileId"] : 0;
			$cover = $byId[$coverId] ?? null;
			$cover = $this->resolveCover($files, $cover);
			foreach ($files as $file) {
				$isCover = $cover && (int)$file->getId() === (int)$cover->getId();
				$file->setIsCover($isCover);
				$file->persist();
			}
			$item->setCoverFileId($cover ? (int)$cover->getId() : null);
			$rationale = $this->nullableString($payload["coverRationale"] ?? null);
			if ($cover && $coverId > 0 && (int)$cover->getId() !== $coverId) {
				$rationale = trim(($rationale ? $rationale . " " : "") . "(cover vynucen z pre_copy)");
			}
			$item->setCoverRationale($rationale);
		} else {
			$lockedId = $item->getCoverFileId();
			foreach ($files as $file) {
				$file->setIsCover($lockedId !== null && (int)$file->getId() === $lockedId);
				$file->persist();
			}
		}

		$item->setEnrichmentVersion(self::ENRICHMENT_VERSION);
		$item->setEnrichedAt(new Time);
		$item->setStatus(IngestItem::STATUS_KEYWORDED);
		$item->touchUpdated();
		$item->persist();
	}

	/**
	 * @param list<IngestItemFile> $files
	 */
	private function resolveCover(array $files, ?IngestItemFile $proposed): ?IngestItemFile
	{
		$usable = static fn (IngestItemFile $f): bool => $f->getRole() !== IngestItemFile::ROLE_SKIP && !$f->isDemoted();

		$preCopy = array_values(array_filter(
			$files,
			fn (IngestItemFile $f) => $f->getSourceSlot() === IngestItemFile::SLOT_PRE_COPY && $usable($f),
		));

		if ($preCopy !== []) {
			if ($proposed && $proposed->getSourceSlot() === IngestItemFile::SLOT_PRE_COPY && $usable($proposed)) {
				return $proposed;
			}

			return $this->rankCoverCandidates($preCopy);
		}

		if ($proposed && $usable($proposed)) {
			return $proposed;
		}

		return $this->rankCoverCandidates(array_values(array_filter($files, $usable)));
	}

	/**
	 * @param list<IngestItemFile> $candidates
	 */
	private function rankCoverCandidates(array $candidates): ?IngestItemFile
	{
		if ($candidates === []) {
			return null;
		}

		usort($candidates, function (IngestItemFile $a, IngestItemFile $b) {
			$score = fn (IngestItemFile $f) => match ($f->getRole()) {
				IngestItemFile::ROLE_LEAD => 3,
				IngestItemFile::ROLE_SUPPORT => 2,
				IngestItemFile::ROLE_DETAIL => 1,
				default => 0,
			} + (($f->getRating() ?? 0) * 0.1) - ((int)($f->getPosition() ?? 99) * 0.001);

			return $score($b) <=> $score($a);
		});

		return $candidates[0];
	}

	/**
	 * @param list<IngestItemFile> $files
	 * @param array<string, mixed>|null $hints
	 * @return array<string, mixed>|null
	 */
	private function normalizeLayoutHints(?array $hints, IngestItem $item, array $files): ?array
	{
		$hasPreCopy = false;
		foreach ($files as $file) {
			if ($file->getSourceSlot() === IngestItemFile::SLOT_PRE_COPY) {
				$hasPreCopy = true;
				break;
			}
		}

		$body = trim(html_entity_decode(strip_tags((string)$item->getBodyHtml()), ENT_QUOTES | ENT_HTML5, "UTF-8"));
		$thinText = in_array("thin_text", $item->getQualityFlags(), true) || mb_strlen($body) < 80;
		$fileCount = count($files);

		$pattern = is_array($hints) && isset($hints["pattern"]) && is_string($hints["pattern"])
			? trim($hints["pattern"])
			: "";

		$allowed = ["hero_text_gallery", "text_lead_gallery", "gallery_essay", "single"];
		if (!in_array($pattern, $allowed, true)) {
			$pattern = "";
		}

		if ($fileCount <= 1) {
			$pattern = "single";
		} elseif ($thinText) {
			$pattern = "gallery_essay";
		} elseif ($hasPreCopy && $body !== "") {
			$pattern = "hero_text_gallery";
		} elseif ($body !== "" && !$hasPreCopy) {
			$pattern = $pattern === "gallery_essay" ? "text_lead_gallery" : ($pattern ?: "text_lead_gallery");
		} elseif ($pattern === "") {
			$pattern = $thinText ? "gallery_essay" : "hero_text_gallery";
		}

		if ($hints === null) {
			$hints = [];
		}
		$hints["pattern"] = $pattern;

		return $hints;
	}

	/**
	 * @param array<string, mixed>|null $timeframe
	 */
	private function applyTimeframe(IngestItem $item, ?array $timeframe): void
	{
		$startsAt = $this->parseTimeValue($timeframe["startsAt"] ?? null);
		$endsAt = $this->parseTimeValue($timeframe["endsAt"] ?? null);
		$place = $this->nullableString($timeframe["place"] ?? null);

		$item->setStartsAt($startsAt);
		$item->setEndsAt($endsAt);
		$item->setPlace($place);
	}

	/**
	 * Controlled tags are source of truth; isInvitation is synced from the invitation tag.
	 * Legacy timeframe.isInvitation still maps to invitation when tags are omitted.
	 *
	 * @param array<string, mixed> $payload
	 */
	private function applyTags(IngestItem $item, array $payload): void
	{
		$rawTags = $payload["tags"] ?? null;
		$tags = is_array($rawTags) ? $rawTags : [];

		$timeframe = isset($payload["timeframe"]) && is_array($payload["timeframe"]) ? $payload["timeframe"] : [];
		if ($tags === [] && !empty($timeframe["isInvitation"])) {
			$tags = [Tags::INVITATION];
		}

		$item->setTagsList($tags);
	}

	private function parseTimeValue(mixed $value): ?Time
	{
		if (!is_string($value)) {
			return null;
		}
		$value = trim($value);
		if ($value === "" || strtolower($value) === "null") {
			return null;
		}
		if (preg_match("/^\d{4}-\d{2}-\d{2}$/", $value)) {
			$value .= " 10:00:00";
		}
		if (!preg_match("/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2})?$/", $value)) {
			return null;
		}

		try {
			return new Time(str_replace("T", " ", $value));
		} catch (\Throwable) {
			return null;
		}
	}

	private function normalizeKind(mixed $kind): ?string
	{
		if (!is_string($kind)) {
			return null;
		}

		$kind = strtolower(trim($kind));
		$aliases = [
			"commission" => "bouquet",
			"kytice" => "bouquet",
			"funeral" => "bouquet",
			"smuteční" => "bouquet",
			"smutecni" => "bouquet",
			"memorial" => "diary",
			"mourning" => "diary",
		];
		$kind = $aliases[$kind] ?? $kind;

		$allowed = [
			"diary", "wedding", "gift", "portrait",
			"workshop", "installation", "bouquet", "product", "other",
		];

		return in_array($kind, $allowed, true) ? $kind : "other";
	}

	private function normalizeCouple(mixed $couple, ?string $kind): ?string
	{
		if ($kind !== "wedding" || !is_string($couple)) {
			return null;
		}

		$couple = trim(html_entity_decode(strip_tags($couple), ENT_QUOTES | ENT_HTML5, "UTF-8"));
		$couple = preg_replace("/@[\\p{L}\\p{N}._]+/u", "", $couple) ?? $couple;
		$couple = preg_replace("/\s+/u", " ", $couple) ?? $couple;
		$couple = trim($couple, " \t\n\r\0\x0B,\"'«»");
		if ($couple === "" || strtolower($couple) === "null") {
			return null;
		}
		if (mb_strlen($couple) > 80) {
			$couple = rtrim(mb_substr($couple, 0, 77)) . "…";
		}

		return $couple;
	}

	private function normalizeRole(mixed $role, bool $demote): string
	{
		if ($demote) {
			return IngestItemFile::ROLE_SKIP;
		}

		$role = is_string($role) ? strtolower(trim($role)) : "";
		$allowed = [
			IngestItemFile::ROLE_LEAD,
			IngestItemFile::ROLE_SUPPORT,
			IngestItemFile::ROLE_DETAIL,
			IngestItemFile::ROLE_CONTEXT,
			IngestItemFile::ROLE_SKIP,
		];

		return in_array($role, $allowed, true) ? $role : IngestItemFile::ROLE_SUPPORT;
	}

	private function clampRating(mixed $rating): ?float
	{
		if ($rating === null || $rating === "") {
			return null;
		}
		if (!is_numeric($rating)) {
			return null;
		}

		return max(0.0, min(1.0, round((float)$rating, 2)));
	}

	/**
	 * @return list<string>
	 */
	private function stringList(mixed $value): array
	{
		if (!is_array($value)) {
			return [];
		}

		$out = [];
		foreach ($value as $item) {
			if (is_string($item) && trim($item) !== "") {
				$out[] = trim($item);
			}
		}

		return array_values(array_unique($out));
	}

	private function nullableString(mixed $value): ?string
	{
		if (!is_string($value)) {
			return null;
		}
		$value = trim($value);

		return $value !== "" ? $value : null;
	}

	private function log(string $message): void
	{
		foreach ($this->loggers as $logger) {
			$logger($message);
		}
	}
}
