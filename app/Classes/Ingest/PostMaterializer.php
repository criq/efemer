<?php

namespace App\Classes\Ingest;

use App\Classes\Content\Post;
use App\Classes\Content\Section;
use App\Classes\Html\Excerpt;
use App\Classes\Html\Sanitizer;
use App\Classes\Jobs\ConsoleStyle;
use Katu\Tools\Calendar\Time;

/**
 * Stage 3: materialize keyworded ingest items into public posts.
 */
class PostMaterializer
{
	/** @var list<callable(string): void> */
	private array $loggers = [];

	public function onLog(callable $logger): self
	{
		$this->loggers[] = $logger;

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

		$bodyHtml = Sanitizer::clean($item->getBodyHtml()) ?: null;
		$excerpt = Excerpt::fromHtml($bodyHtml);
		$files = $this->orderedFiles($item);
		$layout = $this->buildLayout($item, $files);
		$summary = sprintf("#%s → “%s” [%s]", $item->getId(), $title, $section);

		if ($dryRun) {
			$this->log(ConsoleStyle::step("would materialize " . $summary));

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
		$post->setExcerpt($excerpt !== "" ? $excerpt : null);
		$post->setBodyHtml($bodyHtml);
		$post->setStatus(Post::STATUS_PUBLISHED);
		$post->setPublishedAt($item->getPostedAt() ?: new Time);
		$post->setSource($this->mapSource($item));
		$post->setInstagramAccount($item->getInstagramAccount());
		$post->setInstagramShortcode($item->getInstagramShortcode());
		$post->setIngestItemId((int)$item->getId());
		$post->setStartsAt($item->getStartsAt());
		$post->setEndsAt($item->getEndsAt());
		$post->setPlace($item->getPlace());
		$post->setIsInvitation($item->isInvitation());
		$post->setLayoutJson($layout);

		$pathSlug = $this->pathSlug($item, $title);
		$post->setPath(Post::pathFor($section, $pathSlug, $post->getId() !== null ? (int)$post->getId() : null));
		$post->persist();

		// Path uniqueness needs id; rewrite once after first persist on create.
		if ($isNew) {
			$post->setPath(Post::pathFor($section, $pathSlug, (int)$post->getId()));
			$post->persist();
		}

		$post->clearFiles();
		$position = 1;
		foreach ($files as $ingestFile) {
			try {
				$storage = $ingestFile->getStorageFile();
			} catch (\Throwable) {
				continue;
			}
			$post->attachStorageFile(
				$storage,
				$position++,
				$ingestFile->getCaption(),
				$ingestFile->getRating(),
				$ingestFile->getRole(),
				$ingestFile->isDemoted(),
			);
		}

		$item->setPostId((int)$post->getId());
		$item->setEventId(null);
		$item->setStatus(IngestItem::STATUS_MATERIALIZED);
		$item->touchUpdated();
		$item->persist();

		$this->log(ConsoleStyle::ok(sprintf(
			"%s post #%s “%s” ← ingest #%s",
			$isNew ? "Created" : "Updated",
			$post->getId(),
			$title,
			$item->getId(),
		)));

		return $post;
	}

	private function resolveSection(IngestItem $item): ?string
	{
		if ($item->isInvitation()) {
			return Section::UDALOSTI;
		}

		if ($item->getInstagramAccount() === "himmlhaus") {
			return Section::HIMMELHAUS;
		}

		// Soft map until taxonomy; efemer.cz diary-like stays out unless kind hints.
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
	private function orderedFiles(IngestItem $item): array
	{
		$files = [];
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

			return ($a->getPosition() ?? 0) <=> ($b->getPosition() ?? 0);
		});

		return $candidates;
	}

	/**
	 * @param list<IngestItemFile> $files
	 * @return array{pattern: string, blocks: list<array<string, mixed>>}
	 */
	private function buildLayout(IngestItem $item, array $files): array
	{
		$hints = $item->getLayoutHints() ?? [];
		$pattern = is_string($hints["pattern"] ?? null) ? $hints["pattern"] : "gallery_essay";
		$allowed = ["hero_text_gallery", "text_lead_gallery", "gallery_essay", "single"];
		if (!in_array($pattern, $allowed, true)) {
			$pattern = "gallery_essay";
		}

		$blocks = [];
		$leadIds = [];
		foreach ($files as $index => $file) {
			if ($index === 0 || $file->getRole() === IngestItemFile::ROLE_LEAD) {
				$leadIds[] = (int)$file->getId();
			}
		}
		$leadIds = array_values(array_unique($leadIds));

		if ($pattern === "text_lead_gallery") {
			$blocks[] = ["type" => "body"];
			foreach ($leadIds as $id) {
				$blocks[] = ["type" => "image", "ingestFileId" => $id];
			}
			$blocks[] = ["type" => "gallery", "excludeIngestFileIds" => $leadIds];
		} elseif ($pattern === "single") {
			if ($leadIds) {
				$blocks[] = ["type" => "image", "ingestFileId" => $leadIds[0]];
			}
			$blocks[] = ["type" => "body"];
		} elseif ($pattern === "gallery_essay") {
			$blocks[] = ["type" => "gallery"];
			$blocks[] = ["type" => "body"];
		} else {
			// hero_text_gallery
			if ($leadIds) {
				$blocks[] = ["type" => "image", "ingestFileId" => $leadIds[0]];
			}
			$blocks[] = ["type" => "body"];
			$blocks[] = ["type" => "gallery", "excludeIngestFileIds" => $leadIds ? [$leadIds[0]] : []];
		}

		return [
			"pattern" => $pattern,
			"blocks" => $blocks,
		];
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
