<?php

namespace App\Classes\Ingest;

use App\Classes\Content\Post;
use App\Classes\Content\PostParts;
use App\Classes\Content\Section;
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
		$title = $this->deriveTitle($item);
		if ($title === "") {
			$this->log(ConsoleStyle::skipped(
				$item->getSource() . "/" . $item->getSourceKey(),
				"empty title",
			));

			return null;
		}

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
		$summary = sprintf("#%s → “%s” [%s] (%d photos)", $item->getId(), $title, $section, count($candidates));

		if ($dryRun) {
			$plan = $this->planner->plan($item, $candidates);
			$this->log(ConsoleStyle::step(sprintf(
				"would materialize %s — %d planned parts",
				$summary,
				count($plan["parts"]),
			)));

			return new Post;
		}

		$plan = $this->planner->plan($item, $candidates);

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
		$post->setStartsAt($item->getStartsAt());
		$post->setEndsAt($item->getEndsAt());
		$post->setPlace($item->getPlace());
		$post->setTagsList($item->getTagsList());

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
		if ($item->isInvitation()) {
			return Section::UDALOSTI;
		}

		if ($item->getInstagramAccount() === "himmlhaus") {
			return Section::HIMMELHAUS;
		}

		$kind = $item->getKind();
		if ($kind === "wedding") {
			return Section::RITUALY;
		}
		if (in_array($kind, ["installation", "portrait"], true)) {
			return Section::UMENI;
		}
		if ($kind === "diary" && $item->getSource() === IngestItem::SOURCE_EFEMER_CZ) {
			return Section::HIMMELHAUS;
		}
		// Event reportáž (workshop recap etc.) — lasting Himmelhaus until taxonomy settles.
		if ($kind === "workshop" && $item->isReport()) {
			return Section::HIMMELHAUS;
		}

		return null;
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

	private function pathSlug(IngestItem $item, string $title): string
	{
		if ($item->isInvitation()) {
			$start = $item->getStartsAt();
			$date = $start ? $start->format("Y-m-d") : (new Time)->format("Y-m-d");

			return trim($date . " " . $title);
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
		if ($item->isInvitation()) {
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
