<?php

namespace App\Classes\Ingest;

use App\Classes\Content\Post;
use App\Classes\Content\PostParts;
use App\Classes\Content\Section;
use App\Classes\Content\Tags;
use App\Classes\Instagram\CaptionDates;
use App\Classes\Jobs\ConsoleStyle;
use Katu\Tools\Calendar\Time;

/**
 * Stage 3: materialize keyworded ingest items into public posts with LLM-planned parts.
 */
class PostMaterializer
{
	private const MAX_PHOTOS = 16;

	private PostLayoutPlanner $planner;

	/** @var list<callable(string): void> */
	private array $loggers = [];

	public function __construct(?PostLayoutPlanner $planner = null)
	{
		$this->planner = $planner ?? new PostLayoutPlanner;
	}

	public function onLog(callable $logger): self
	{
		$this->loggers[] = $logger;
		$this->planner->onLog($logger);

		return $this;
	}

	public function materialize(IngestItem $item, bool $dryRun = false): ?Post
	{
		$section = $this->resolveSection($item);
		if ($section === null) {
			$this->log(ConsoleStyle::skipped(
				$item->getSource() . "/" . $item->getSourceKey(),
				"no section yet",
			));

			return null;
		}

		if ($item->isInvitation() && !$item->getStartsAt()) {
			$this->log(ConsoleStyle::skipped(
				$item->getSource() . "/" . $item->getSourceKey(),
				"invitation missing startsAt",
			));

			return null;
		}

		$candidates = $this->selectOrderedFiles($item);
		$pattern = $this->resolvePattern($item);
		$plan = $this->planner->plan($item, $candidates);
		$title = $this->resolveTitle($item, $plan);
		if ($title === "") {
			$this->log(ConsoleStyle::skipped(
				$item->getSource() . "/" . $item->getSourceKey(),
				"empty title",
			));

			return null;
		}

		$summary = sprintf("#%s → “%s” [%s] (%d photos)", $item->getId(), $title, $section, count($candidates));

		if ($dryRun) {
			$this->log(ConsoleStyle::step(sprintf(
				"would materialize %s — %d planned parts",
				$summary,
				count($plan["parts"]),
			)));

			return new Post;
		}

		$post = $item->getPost() ?: Post::getByIngestItemId((int)$item->getId());
		$isNew = $post === null;
		if ($isNew) {
			$post = new Post;
			$post->setTimeCreated(new Time);
		}

		$post->setTitle($title);
		$post->setSection($section);
		$post->setStatus(Post::STATUS_PUBLISHED);
		$post->setPublishedAt($item->getPostedAt() ?: new Time);
		$post->setSource($this->mapSource($item));
		$post->setInstagramAccount($item->getInstagramAccount());
		$post->setInstagramShortcode($item->getInstagramShortcode());
		$post->setIngestItemId((int)$item->getId());
		[$startsAt, $endsAt] = $this->resolveTimeframe($item);
		$post->setStartsAt($startsAt);
		$post->setEndsAt($endsAt);
		$post->setPlace($item->getPlace());
		$post->setTagsList($this->publicTags($item));

		$pathSlug = $this->pathSlug($item, $title);
		$post->setPath(Post::pathFor($section, $pathSlug, $post->getId() !== null ? (int)$post->getId() : null));
		$post->persist();

		if ($isNew) {
			$post->setPath(Post::pathFor($section, $pathSlug, (int)$post->getId()));
			$post->persist();
		}

		$usedIndexes = $this->collectFileIndexes($plan["parts"]);
		$post->clearFiles();
		$indexToPostFileId = [];
		$position = 1;
		foreach ($usedIndexes as $fileIndex) {
			$ingestFile = $candidates[$fileIndex] ?? null;
			if (!$ingestFile) {
				continue;
			}
			try {
				$storage = $ingestFile->getStorageFile();
			} catch (\Throwable) {
				continue;
			}
			$postFile = $post->attachStorageFile(
				$storage,
				$position++,
				$ingestFile->getCaption(),
				$ingestFile->getRating(),
				$ingestFile->getRole(),
				$ingestFile->isDemoted(),
			);
			$indexToPostFileId[$fileIndex] = (int)$postFile->getId();
		}

		$parts = $this->mapPlanToParts($plan["parts"], $indexToPostFileId);
		$excerpt = $plan["excerpt"];
		$post->setLayoutJson([
			"pattern" => $pattern,
			"parts" => $parts,
			"plannedBy" => "gemini",
		]);
		$post->setBodyHtml(PostParts::bodyHtmlFromParts($parts));
		$post->setExcerpt($excerpt);
		$post->persist();

		$item->setPostId((int)$post->getId());
		$item->setEventId(null);
		$item->setStatus(IngestItem::STATUS_MATERIALIZED);
		$item->touchUpdated();
		$item->persist();

		$this->log(ConsoleStyle::ok(sprintf(
			"%s post #%s “%s” ← ingest #%s (%d parts, %d photos)",
			$isNew ? "Created" : "Updated",
			$post->getId(),
			$title,
			$item->getId(),
			count($parts),
			count($indexToPostFileId),
		)));

		return $post;
	}

	/**
	 * @param list<array{type: string, html?: string, fileIndex?: int, fileIndexes?: list<int>}> $planParts
	 * @return list<int>
	 */
	private function collectFileIndexes(array $planParts): array
	{
		$ordered = [];
		$seen = [];
		foreach ($planParts as $part) {
			$type = $part["type"] ?? "";
			if ($type === PostParts::TYPE_IMAGE) {
				$idx = (int)($part["fileIndex"] ?? -1);
				if ($idx >= 0 && !isset($seen[$idx])) {
					$seen[$idx] = true;
					$ordered[] = $idx;
				}
				continue;
			}
			if ($type === PostParts::TYPE_GALLERY) {
				foreach ((array)($part["fileIndexes"] ?? []) as $idx) {
					$idx = (int)$idx;
					if ($idx >= 0 && !isset($seen[$idx])) {
						$seen[$idx] = true;
						$ordered[] = $idx;
					}
				}
			}
		}

		return $ordered;
	}

	/**
	 * @param list<array{type: string, html?: string, fileIndex?: int, fileIndexes?: list<int>}> $planParts
	 * @param array<int, int> $indexToPostFileId
	 * @return list<array{type: string, html?: string, postFileId?: int, postFileIds?: list<int>}>
	 */
	private function mapPlanToParts(array $planParts, array $indexToPostFileId): array
	{
		$parts = [];
		foreach ($planParts as $part) {
			$type = $part["type"] ?? "";
			if ($type === PostParts::TYPE_TEXT) {
				$normalized = PostParts::normalizePart([
					"type" => PostParts::TYPE_TEXT,
					"html" => (string)($part["html"] ?? ""),
				]);
				if ($normalized) {
					$plain = trim(html_entity_decode(strip_tags($normalized["html"]), ENT_QUOTES | ENT_HTML5, "UTF-8"));
					if ($plain !== "") {
						$parts[] = $normalized;
					}
				}
				continue;
			}
			if ($type === PostParts::TYPE_IMAGE) {
				$idx = (int)($part["fileIndex"] ?? -1);
				$postFileId = $indexToPostFileId[$idx] ?? 0;
				$normalized = PostParts::normalizePart([
					"type" => PostParts::TYPE_IMAGE,
					"postFileId" => $postFileId,
				]);
				if ($normalized) {
					$parts[] = $normalized;
				}
				continue;
			}
			if ($type === PostParts::TYPE_GALLERY) {
				$ids = [];
				foreach ((array)($part["fileIndexes"] ?? []) as $idx) {
					$id = $indexToPostFileId[(int)$idx] ?? 0;
					if ($id > 0) {
						$ids[] = $id;
					}
				}
				$normalized = PostParts::normalizePart([
					"type" => PostParts::TYPE_GALLERY,
					"postFileIds" => $ids,
				]);
				if ($normalized) {
					$parts[] = $normalized;
				}
			}
		}

		return $parts;
	}

	private function resolveSection(IngestItem $item): ?string
	{
		// Current open calls — /udalosti + homepage hero. Past invitations → lasting deník.
		if ($item->isInvitation()) {
			return $this->isPastInvitation($item) ? Section::DENIK : Section::UDALOSTI;
		}

		// Rituály = weddings / bridal work only.
		if ($item->getKind() === "wedding") {
			return Section::RITUALY;
		}

		// Umění = discrete art objects / commissions only (not portraits or seasonal diary).
		if ($item->getKind() === "installation") {
			return Section::UMENI;
		}

		// Workshops, reports, diary, and the rest → dated journal.
		return Section::DENIK;
	}

	/**
	 * Invitation whose event end (or start day) is already over — archive as lasting deník.
	 */
	private function isPastInvitation(IngestItem $item): bool
	{
		if (!$item->isInvitation() || !$item->getStartsAt()) {
			return false;
		}

		return !$this->isInvitationStillCurrent($item);
	}

	private function isInvitationStillCurrent(IngestItem $item): bool
	{
		if (!$item->isInvitation()) {
			return false;
		}

		$end = $item->getEndsAt();
		if (!$end) {
			$start = $item->getStartsAt();
			if (!$start) {
				return false;
			}
			$end = new Time($start->format("Y-m-d") . " 23:59:59");
		}

		return (int)$end->format("U") >= time();
	}

	/**
	 * Drop the live invitation tag on past invites so they stay publicly visible in deník.
	 *
	 * @return list<string>
	 */
	private function publicTags(IngestItem $item): array
	{
		$tags = $item->getTagsList();
		if (!$this->isPastInvitation($item)) {
			return $tags;
		}

		return array_values(array_filter(
			$tags,
			static fn (string $tag) => $tag !== Tags::INVITATION,
		));
	}

	/**
	 * @param array{title: ?string, excerpt: ?string, parts: list<array<string, mixed>>} $plan
	 */
	private function resolveTitle(IngestItem $item, array $plan): string
	{
		if ($item->getKind() === "wedding") {
			foreach ([
				$plan["title"] ?? null,
				$item->getCouple(),
			] as $candidate) {
				$normalized = $this->normalizeCoupleTitle((string)($candidate ?? ""));
				if ($normalized !== "") {
					return $normalized;
				}
			}
		}

		if ($item->getKind() === "installation") {
			$normalized = $this->normalizeArtTitle((string)($plan["title"] ?? ""));
			if ($normalized !== "") {
				return $normalized;
			}
		}

		$sourceTitle = trim((string)$item->getTitle());
		$sourceIsDate = CaptionDates::isDateOnlyTitle($sourceTitle);

		// Prefer LLM short headline when source title is a caption paste.
		$planned = $this->normalizePublicTitle((string)($plan["title"] ?? ""));
		if ($planned !== "" && !CaptionDates::isDateOnlyTitle($planned)) {
			return $planned;
		}

		// Diary date as source title + short body headline ("Sklizeň") → use the headline.
		if ($sourceIsDate) {
			$fromBody = $this->extractDiaryHeadlineFromBody($item);
			if ($fromBody !== "") {
				return $fromBody;
			}
		}

		$derived = $this->deriveTitle($item);
		if ($derived !== "" && !$this->looksLikeCaptionPaste($derived) && !($sourceIsDate && CaptionDates::isDateOnlyTitle($derived))) {
			return $derived;
		}

		return $this->shortenCaptionTitle($derived !== "" ? $derived : $sourceTitle);
	}

	/**
	 * First non-date body line when it reads as a short diary headline, not a sentence.
	 */
	private function extractDiaryHeadlineFromBody(IngestItem $item): string
	{
		$html = (string)$item->getBodyHtml();
		if (trim($html) === "") {
			return "";
		}

		$withBreaks = preg_replace("/<(br|\\/p|\\/div|\\/h[1-6])\\s*\\/?>/iu", "\n", $html) ?? $html;
		$plain = trim(html_entity_decode(strip_tags($withBreaks), ENT_QUOTES | ENT_HTML5, "UTF-8"));
		$lines = preg_split("/\\R+/u", $plain) ?: [];
		foreach ($lines as $line) {
			$line = trim(preg_replace("/\\s+/u", " ", $line) ?? $line);
			if ($line === "") {
				continue;
			}
			if (CaptionDates::isDateOnlyTitle($line)) {
				continue;
			}
			// Whole line is only a parseable date fragment — skip.
			$parsed = CaptionDates::parse($line);
			if (($parsed["startsAt"] ?? null) && mb_strlen($line) <= 28 && !preg_match("/\\p{L}{4,}/u", preg_replace("/\\d+/u", "", $line) ?? $line)) {
				continue;
			}

			return $this->normalizeDiaryHeadline($line);
		}

		return "";
	}

	private function normalizeDiaryHeadline(string $raw): string
	{
		$title = trim(html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, "UTF-8"));
		$title = preg_replace("/\\s+/u", " ", $title) ?? $title;
		$title = trim($title, " \t\n\r\0\x0B\"'«».,;:");
		if ($title === "" || $this->looksLikeCaptionPaste($title) || CaptionDates::isDateOnlyTitle($title)) {
			return "";
		}

		$len = mb_strlen($title);
		$words = preg_split("/\\s+/u", $title) ?: [];
		if ($len < 2 || $len > 45 || count($words) > 8) {
			return "";
		}
		// Mid-sentence punctuation → prose, not a headline.
		if (preg_match("/[.!?]\\s/u", $title)) {
			return "";
		}
		// Long narrative openers after a diary date.
		if ($len > 28 && preg_match("/^(po |už |ještě |dům |noc |ani |když |prší |vydáváme|přijela|celý )/ui", $title)) {
			return "";
		}

		return $title;
	}

	private function shortenCaptionTitle(string $raw): string
	{
		$title = trim(html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, "UTF-8"));
		$title = preg_replace("/\s+/u", " ", $title) ?? $title;
		$title = preg_replace("/@[\\p{L}\\p{N}._]+/u", "", $title) ?? $title;
		$title = preg_replace("/#\\S+/u", "", $title) ?? $title;
		$title = preg_replace("/\s+/u", " ", $title) ?? $title;
		$title = trim($title, " \t\n\r\0\x0B,\"'«»…");
		if ($title === "") {
			return "";
		}

		// First sentence / clause if it is already short enough.
		if (preg_match("/^(.{8,55}?)(?:[.!?]|\\s+[–—-]\\s+)/u", $title, $match)) {
			$clause = trim($match[1], " \t,&");
			if ($clause !== "" && !$this->looksLikeCaptionPaste($clause)) {
				return $clause;
			}
		}

		if (mb_strlen($title) <= 55 && !$this->looksLikeCaptionPaste($title)) {
			return $title;
		}

		// Word-boundary trim — never leave a mid-word "…".
		$cut = mb_substr($title, 0, 55);
		if (preg_match("/^(.+\\s)\\S*$/u", $cut, $match)) {
			$cut = rtrim($match[1]);
		}
		$cut = trim($cut, " \t,&");

		return $cut;
	}

	private function normalizePublicTitle(string $raw): string
	{
		$title = trim(html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, "UTF-8"));
		$title = preg_replace("/\s+/u", " ", $title) ?? $title;
		$title = trim($title, " \t\n\r\0\x0B\"'«»");
		$title = preg_replace("/@[\\p{L}\\p{N}._]+/u", "", $title) ?? $title;
		$title = preg_replace("/#\\S+/u", "", $title) ?? $title;
		$title = preg_replace("/\s+/u", " ", $title) ?? $title;
		$title = trim($title, " \t\n\r\0\x0B,&");
		if ($title === "" || strtolower($title) === "null") {
			return "";
		}
		if ($this->looksLikeCaptionPaste($title)) {
			return "";
		}
		if (mb_strlen($title) > 55) {
			$title = rtrim(mb_substr($title, 0, 52)) . "…";
		}

		return $title;
	}

	private function looksLikeCaptionPaste(string $title): bool
	{
		if (str_contains($title, "@") || str_contains($title, "#")) {
			return true;
		}
		if (str_ends_with($title, "…") && mb_strlen($title) > 40) {
			return true;
		}
		// Long sentence-like openers (thank-yous, narrative captions).
		if (mb_strlen($title) > 45 && preg_match("/[.!?]\\s|\\b(děkuji|děkujeme|minulý|včera|dnes)\\b/ui", $title)) {
			return true;
		}

		return false;
	}

	private function normalizeArtTitle(string $raw): string
	{
		$title = trim(html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, "UTF-8"));
		$title = preg_replace("/\s+/u", " ", $title) ?? $title;
		$title = trim($title, " \t\n\r\0\x0B\"'«»");
		$title = preg_replace("/@[\\p{L}\\p{N}._]+/u", "", $title) ?? $title;
		$title = preg_replace("/#\\S+/u", "", $title) ?? $title;
		$title = preg_replace("/\s+/u", " ", $title) ?? $title;
		$title = trim($title, " \t\n\r\0\x0B,&");
		if ($title === "" || strtolower($title) === "null") {
			return "";
		}
		// Reject bare dates and long sentence pastes — layout planner should supply a short work title.
		if (preg_match("/^\\d{1,2}\\.?\\s*\\d{1,2}\\.?\\s*\\d{2,4}$/u", $title)) {
			return "";
		}
		if (mb_strlen($title) > 80) {
			return "";
		}
		if (mb_strlen($title) > 55 && preg_match("/[.!?]\\s/u", $title)) {
			return "";
		}

		return $title;
	}

	private function normalizeCoupleTitle(string $raw): string
	{
		$title = trim(html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, "UTF-8"));
		$title = preg_replace("/\s+/u", " ", $title) ?? $title;
		$title = trim($title, " \t\n\r\0\x0B\"'«»");
		// Drop Instagram handles — names only.
		$title = preg_replace("/@[\\p{L}\\p{N}._]+/u", "", $title) ?? $title;
		$title = preg_replace("/\s+/u", " ", $title) ?? $title;
		$title = trim($title, " \t\n\r\0\x0B,&");
		if ($title === "" || strtolower($title) === "null") {
			return "";
		}
		if (mb_strlen($title) > 80) {
			$title = rtrim(mb_substr($title, 0, 77)) . "…";
		}

		return $title;
	}

	private function deriveTitle(IngestItem $item): string
	{
		$raw = trim((string)$item->getTitle());
		if ($raw === "") {
			$raw = trim(strip_tags((string)$item->getBodyHtml()));
		}
		if ($raw === "") {
			return "";
		}

		$first = preg_split("/\R/u", $raw)[0] ?? $raw;
		$first = trim(preg_replace("/\s+/u", " ", $first) ?? $first);
		$first = preg_replace("/^[#@]\\S+\\s*/u", "", $first) ?? $first;

		if (preg_match("/^(\\p{L}[\\p{L}\\d'’.-]{1,40})\\s+(znamená|je|aneb)\\b/ui", $first, $match)) {
			return $match[1];
		}
		if (preg_match("/^(\\p{L}[\\p{L}\\d'’.-]{1,40})\\s*[—–-]\\s+/u", $first, $match)) {
			return $match[1];
		}

		if (mb_strlen($first) > 80) {
			$first = rtrim(mb_substr($first, 0, 77)) . "…";
		}

		return $first;
	}

	/**
	 * Keep ingest timeframe; if missing, parse a diary date used as the source title.
	 *
	 * @return array{0: ?Time, 1: ?Time}
	 */
	private function resolveTimeframe(IngestItem $item): array
	{
		$startsAt = $item->getStartsAt();
		$endsAt = $item->getEndsAt();
		if ($startsAt) {
			return [$startsAt, $endsAt];
		}

		$fromTitle = CaptionDates::parse(trim((string)$item->getTitle()));
		if ($fromTitle["startsAt"] instanceof Time) {
			return [$fromTitle["startsAt"], $endsAt ?: $fromTitle["endsAt"]];
		}

		return [null, $endsAt];
	}

	private function pathSlug(IngestItem $item, string $title): string
	{
		// Current invitations and past invites both date from the event when known.
		if ($item->isInvitation() && $item->getStartsAt()) {
			return trim($item->getStartsAt()->format("Y-m-d") . " " . $title);
		}

		$posted = $item->getPostedAt();
		if ($posted) {
			return trim($posted->format("Y-m-d") . " " . $title);
		}

		return $title;
	}

	/**
	 * @return list<IngestItemFile>
	 */
	private function selectOrderedFiles(IngestItem $item): array
	{
		$cover = $item->getCoverFile();
		$coverId = $cover ? (int)$cover->getId() : null;

		$roleRank = [
			IngestItemFile::ROLE_LEAD => 1,
			IngestItemFile::ROLE_SUPPORT => 2,
			IngestItemFile::ROLE_DETAIL => 3,
			IngestItemFile::ROLE_CONTEXT => 4,
		];

		$candidates = [];
		foreach ($item->getFiles() as $file) {
			if ($file->isDemoted() || $file->getRole() === IngestItemFile::ROLE_SKIP) {
				continue;
			}
			$candidates[] = $file;
		}

		usort($candidates, function (IngestItemFile $a, IngestItemFile $b) use ($coverId, $roleRank) {
			$aCover = $coverId !== null && (int)$a->getId() === $coverId ? 0 : 1;
			$bCover = $coverId !== null && (int)$b->getId() === $coverId ? 0 : 1;
			if ($aCover !== $bCover) {
				return $aCover <=> $bCover;
			}

			$aRole = $roleRank[$a->getRole() ?? ""] ?? 5;
			$bRole = $roleRank[$b->getRole() ?? ""] ?? 5;
			if ($aRole !== $bRole) {
				return $aRole <=> $bRole;
			}

			$aRating = $a->getRating() ?? 0.0;
			$bRating = $b->getRating() ?? 0.0;
			if ($aRating !== $bRating) {
				return $bRating <=> $aRating;
			}

			return ($a->getPosition() ?? 0) <=> ($b->getPosition() ?? 0);
		});

		return array_slice($candidates, 0, self::MAX_PHOTOS);
	}

	private function resolvePattern(IngestItem $item): string
	{
		if ($item->isInvitation() && !$this->isPastInvitation($item)) {
			return "hero_text_gallery";
		}

		$hints = $item->getLayoutHints() ?? [];
		$pattern = is_string($hints["pattern"] ?? null) ? $hints["pattern"] : "gallery_essay";
		$allowed = ["hero_text_gallery", "text_lead_gallery", "gallery_essay", "single"];
		if (!in_array($pattern, $allowed, true)) {
			$pattern = "gallery_essay";
		}

		return $pattern;
	}

	private function mapSource(IngestItem $item): string
	{
		return match ($item->getSource()) {
			IngestItem::SOURCE_INSTAGRAM => Post::SOURCE_INSTAGRAM,
			IngestItem::SOURCE_EFEMER_CZ => Post::SOURCE_EFEMER_CZ,
			default => Post::SOURCE_MANUAL,
		};
	}

	private function log(string $message): void
	{
		foreach ($this->loggers as $logger) {
			$logger($message);
		}
	}
}
