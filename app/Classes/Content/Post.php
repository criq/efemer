<?php

namespace App\Classes\Content;

use App\Classes\Html\Excerpt;
use App\Classes\Instagram\CaptionDates;
use App\Classes\Storage\StorageFile;
use App\Classes\Tools\Slugger;
use Katu\Tools\Calendar\Time;

class Post extends \Katu\Models\Model
{
	const TABLE = "posts";

	public int|string|null $id = null;
	public Time|string|null $timeCreated = null;
	public Time|string|null $publishedAt = null;
	public Time|string|null $startsAt = null;
	public Time|string|null $endsAt = null;
	public ?string $place = null;
	public int|string|null $isInvitation = 0;
	public ?string $tags = null;
	public ?string $title = null;
	public ?string $path = null;
	public ?string $section = null;
	public ?string $excerpt = null;
	public ?string $bodyHtml = null;
	public ?string $status = null;
	public ?string $source = null;
	public ?string $instagramAccount = null;
	public ?string $instagramShortcode = null;
	public ?string $classificationConfidence = null;
	public int|string|null $ingestItemId = null;
	public ?string $layoutJson = null;

	public const STATUS_REVIEW = "review";
	public const STATUS_PUBLISHED = "published";
	public const STATUS_HIDDEN = "hidden";

	public const SOURCE_INSTAGRAM = "instagram";
	public const SOURCE_EFEMER_CZ = "efemer_cz";
	public const SOURCE_MANUAL = "manual";

	public function setTimeCreated(Time $time): Post
	{
		$this->timeCreated = $time;

		return $this;
	}

	public function setPublishedAt(?Time $time): Post
	{
		$this->publishedAt = $time;

		return $this;
	}

	public function getPublishedAt(): ?Time
	{
		if ($this->publishedAt instanceof Time) {
			return $this->publishedAt;
		}

		if (is_string($this->publishedAt) && $this->publishedAt !== "") {
			return new Time($this->publishedAt);
		}

		return null;
	}

	public function setStartsAt(?Time $time): Post
	{
		$this->startsAt = $time;

		return $this;
	}

	public function getStartsAt(): ?Time
	{
		if ($this->startsAt instanceof Time) {
			return $this->startsAt;
		}

		if (is_string($this->startsAt) && $this->startsAt !== "") {
			return new Time($this->startsAt);
		}

		return null;
	}

	/**
	 * Chronology for listings: explicit diary/event date first, then publishedAt.
	 * Deník titles like "6. 6. 2026" are parsed when startsAt is empty.
	 */
	public function getExplicitAt(): ?Time
	{
		$startsAt = $this->getStartsAt();
		if ($startsAt) {
			return $startsAt;
		}

		if (Section::isJournal($this->getSection())) {
			$title = trim((string)$this->getTitle());
			if ($title !== "") {
				$fromTitle = CaptionDates::parse($title)["startsAt"] ?? null;
				if ($fromTitle instanceof Time) {
					return $fromTitle;
				}
			}
		}

		return $this->getPublishedAt();
	}

	public function setEndsAt(?Time $time): Post
	{
		$this->endsAt = $time;

		return $this;
	}

	public function getEndsAt(): ?Time
	{
		if ($this->endsAt instanceof Time) {
			return $this->endsAt;
		}

		if (is_string($this->endsAt) && $this->endsAt !== "") {
			return new Time($this->endsAt);
		}

		return null;
	}

	public function setPlace(?string $place): Post
	{
		$this->place = $place !== null && trim($place) !== "" ? trim($place) : null;

		return $this;
	}

	public function getPlace(): ?string
	{
		return $this->place;
	}

	/**
	 * Short venue for hero/meta (drop secondary towns after / or long region after comma).
	 */
	public function getHeroPlaceLabel(): ?string
	{
		$place = trim((string)$this->getPlace());
		if ($place === "") {
			return null;
		}

		if (str_contains($place, "/")) {
			$place = trim(explode("/", $place, 2)[0]);
		}

		if (mb_strlen($place) > 28 && str_contains($place, ",")) {
			$place = trim(explode(",", $place, 2)[0]);
		}

		return $place !== "" ? $place : null;
	}

	public function setIsInvitation(bool $isInvitation): Post
	{
		$this->isInvitation = $isInvitation ? 1 : 0;

		return $this;
	}

	public function isInvitation(): bool
	{
		return Tags::has($this->getTagsList(), Tags::INVITATION) || (bool)(int)$this->isInvitation;
	}

	public function isReport(): bool
	{
		return Tags::has($this->getTagsList(), Tags::REPORT);
	}

	/**
	 * @param list<string>|null $tags
	 */
	public function setTagsList(?array $tags): Post
	{
		$normalized = Tags::normalize($tags ?? []);
		$this->tags = Tags::encode($normalized);
		$this->isInvitation = Tags::has($normalized, Tags::INVITATION) ? 1 : 0;

		return $this;
	}

	/**
	 * @return list<string>
	 */
	public function getTagsList(): array
	{
		return Tags::decode($this->tags);
	}

	public function hasTag(string $tag): bool
	{
		return Tags::has($this->getTagsList(), $tag);
	}

	public function getEffectiveEnd(): ?Time
	{
		$end = $this->getEndsAt();
		if ($end) {
			return $end;
		}

		$start = $this->getStartsAt();
		if (!$start) {
			return null;
		}

		return new Time($start->format("Y-m-d") . " 23:59:59");
	}

	public function isInvitationCurrent(): bool
	{
		if (!$this->isInvitation()) {
			return false;
		}

		$end = $this->getEffectiveEnd();

		return $end !== null && (int)$end->format("U") >= time();
	}

	public function isHappening(): bool
	{
		if (!$this->isInvitation()) {
			return false;
		}

		$start = $this->getStartsAt();
		if (!$start || (int)$start->format("U") > time()) {
			return false;
		}

		return $this->isInvitationCurrent();
	}

	public function isPubliclyVisible(): bool
	{
		if (!$this->isPublished()) {
			return false;
		}

		if ($this->isInvitation()) {
			return $this->isInvitationCurrent();
		}

		return true;
	}

	public function getDateLabel(): string
	{
		$start = $this->getStartsAt();
		if (!$start) {
			$published = $this->getPublishedAt();

			return $published ? $published->format("j. n. Y") : "";
		}

		$end = $this->getEndsAt();
		if ($end && $end->format("Y-m-d") !== $start->format("Y-m-d")) {
			return $start->format("j. n.") . "–" . $end->format("j. n. Y");
		}

		return $start->format("j. n. Y");
	}

	public function getTimeLabel(): string
	{
		$start = $this->getStartsAt();
		if (!$start) {
			return "";
		}

		$hm = $start->format("H:i");
		if ($hm === "00:00") {
			return "";
		}

		return $start->format("G:i");
	}

	public function getInviteKicker(): string
	{
		if ($this->isHappening()) {
			return "právě probíhá";
		}

		return "nadcházející";
	}

	public function setIngestItemId(?int $ingestItemId): Post
	{
		$this->ingestItemId = $ingestItemId;

		return $this;
	}

	public function getIngestItemId(): ?int
	{
		return $this->ingestItemId !== null ? (int)$this->ingestItemId : null;
	}

	public function setLayoutJson(?array $layout): Post
	{
		$this->layoutJson = $layout === null
			? null
			: json_encode($layout, JSON_UNESCAPED_UNICODE);

		return $this;
	}

	public function getLayoutJson(): ?array
	{
		if ($this->layoutJson === null || $this->layoutJson === "") {
			return null;
		}

		$decoded = json_decode($this->layoutJson, true);

		return is_array($decoded) ? $decoded : null;
	}

	/**
	 * Raw parts from layoutJson (normalized).
	 *
	 * @return list<array{type: string, html?: string, postFileId?: int, postFileIds?: list<int>}>
	 */
	public function getParts(): array
	{
		return PostParts::fromLayout($this->getLayoutJson());
	}

	/**
	 * @param list<array{type: string, html?: string, postFileId?: int, postFileIds?: list<int>}> $parts
	 */
	public function setParts(array $parts, ?string $pattern = null): Post
	{
		$layout = $this->getLayoutJson() ?? [];
		if ($pattern !== null) {
			$layout["pattern"] = $pattern;
		} elseif (!isset($layout["pattern"])) {
			$layout["pattern"] = "hero_text_gallery";
		}
		$layout["parts"] = $parts;
		$this->setLayoutJson($layout);
		$this->setBodyHtml(PostParts::bodyHtmlFromParts($parts));
		$this->setExcerpt(PostParts::excerptFromParts($parts));

		return $this;
	}

	/**
	 * Resolved parts for Twig (with PostFile objects).
	 *
	 * @return list<array{type: string, html?: string, file?: PostFile|null, files?: list<PostFile>}>
	 */
	public function getResolvedParts(): array
	{
		return PostParts::resolveForRender($this);
	}

	public function setTitle(?string $title): Post
	{
		$this->title = $title;

		return $this;
	}

	public function getTitle(): ?string
	{
		return $this->title;
	}

	/**
	 * Diary date used as the sole title ("6. 6. 2026", "Květen 2026").
	 * Named event / report titles return false so the date can sit in meta below.
	 */
	public function titleIsDateOnly(): bool
	{
		return CaptionDates::isDateOnlyTitle(trim((string)$this->getTitle()));
	}

	public function setPath(?string $path): Post
	{
		$this->path = $path;

		return $this;
	}

	public function getPath(): ?string
	{
		return $this->path;
	}

	public function getPublicPath(): string
	{
		if ($this->path === null || $this->path === "") {
			return "";
		}

		return str_starts_with($this->path, "/") ? $this->path : "/" . $this->path;
	}

	public function getPublicUrl(): string
	{
		$publicPath = $this->getPublicPath();
		if ($publicPath === "") {
			return "";
		}

		$base = rtrim((string)\Katu\Tools\Routing\URL::getFor("homepage.index"), "/");

		return $base . $publicPath;
	}

	public function setSection(string $section): Post
	{
		$this->section = $section;

		return $this;
	}

	public function getSection(): string
	{
		return (string)$this->section;
	}

	public function getSectionTitle(): string
	{
		return Section::title($this->getSection());
	}

	public function setExcerpt(?string $excerpt): Post
	{
		$this->excerpt = $excerpt;

		return $this;
	}

	public function getExcerpt(): ?string
	{
		return $this->excerpt;
	}

	public function getSummary(): string
	{
		$excerpt = trim((string)$this->getExcerpt());
		if ($excerpt !== "") {
			return Excerpt::fromHtml($excerpt);
		}

		return Excerpt::fromHtml($this->getBodyHtml());
	}

	public function setBodyHtml(?string $bodyHtml): Post
	{
		$this->bodyHtml = $bodyHtml;

		return $this;
	}

	public function getBodyHtml(): ?string
	{
		return $this->bodyHtml;
	}

	public function setStatus(string $status): Post
	{
		$this->status = $status;

		return $this;
	}

	public function getStatus(): string
	{
		return $this->status ?: self::STATUS_REVIEW;
	}

	public function isPublished(): bool
	{
		return $this->getStatus() === self::STATUS_PUBLISHED;
	}

	public function setSource(string $source): Post
	{
		$this->source = $source;

		return $this;
	}

	public function getSource(): string
	{
		return $this->source ?: self::SOURCE_MANUAL;
	}

	public function setInstagramAccount(?string $account): Post
	{
		$this->instagramAccount = $account;

		return $this;
	}

	public function getInstagramAccount(): ?string
	{
		return $this->instagramAccount;
	}

	public function setInstagramShortcode(?string $shortcode): Post
	{
		$this->instagramShortcode = $shortcode;

		return $this;
	}

	public function getInstagramShortcode(): ?string
	{
		return $this->instagramShortcode;
	}

	/**
	 * @return list<string>
	 */
	public static function getInstagramShortcodes(string $account): array
	{
		$shortcodes = [];
		foreach (static::getBy(["instagramAccount" => $account])->getItems() as $post) {
			$code = $post->getInstagramShortcode();
			if ($code) {
				$shortcodes[] = $code;
			}
		}

		return $shortcodes;
	}

	public function setClassificationConfidence(?float $confidence): Post
	{
		$this->classificationConfidence = $confidence === null ? null : sprintf("%.3f", $confidence);

		return $this;
	}

	public function getClassificationConfidence(): ?float
	{
		return $this->classificationConfidence !== null ? (float)$this->classificationConfidence : null;
	}

	public function getFiles(): PostFileCollection
	{
		$files = PostFile::getBy([
			"postId" => $this->getId(),
		])->getItems();

		usort($files, function (PostFile $a, PostFile $b) {
			return ($a->getPosition() ?? 0) <=> ($b->getPosition() ?? 0);
		});

		return new PostFileCollection($files);
	}

	public function getHeroFile(): ?PostFile
	{
		return $this->getFiles()->getFirst();
	}

	public function getHeroImage(): ?\Katu\Tools\Images\Image
	{
		$file = $this->getHeroFile();

		return $file ? $file->getImage() : null;
	}

	/**
	 * Listing image: prefer a high-rated file with the given LLM orientation,
	 * else the best-rated non-demoted file, else the cover/hero.
	 * Avoids getWidth/getHeight (GCS) — uses ingest orientation only.
	 */
	public function getPreferredImage(?string $preferOrientation = null): ?\Katu\Tools\Images\Image
	{
		$preferOrientation = $preferOrientation !== null ? strtolower($preferOrientation) : null;
		$bestPreferred = null;
		$bestPreferredRating = -1.0;
		$bestAny = null;
		$bestAnyRating = -1.0;

		foreach ($this->getFiles() as $file) {
			if ($file->isDemoted() || $file->getRole() === "skip") {
				continue;
			}

			$image = $file->getImage();
			if (!$image) {
				continue;
			}

			$rating = $file->getRating() ?? 0.55;
			if ($rating > $bestAnyRating) {
				$bestAnyRating = $rating;
				$bestAny = $image;
			}

			if ($preferOrientation !== null && $file->getOrientation() === $preferOrientation && $rating > $bestPreferredRating) {
				$bestPreferredRating = $rating;
				$bestPreferred = $image;
			}
		}

		return $bestPreferred ?? $bestAny ?? $this->getHeroImage();
	}

	/**
	 * Ranked listing images for collage panels (unique storage files).
	 *
	 * @return list<\Katu\Tools\Images\Image>
	 */
	public function getPreferredImages(?string $preferOrientation = null, int $limit = 2): array
	{
		if ($limit < 1) {
			return [];
		}

		$preferOrientation = $preferOrientation !== null ? strtolower($preferOrientation) : null;
		$ranked = [];

		foreach ($this->getFiles() as $file) {
			if ($file->isDemoted() || $file->getRole() === "skip") {
				continue;
			}

			$image = $file->getImage();
			if (!$image) {
				continue;
			}

			$rating = $file->getRating() ?? 0.55;
			$matchesPrefer = $preferOrientation !== null && $file->getOrientation() === $preferOrientation;
			$ranked[] = [
				"image" => $image,
				"key" => (int)$file->storageFileId,
				"score" => ($matchesPrefer ? 1000.0 : 0.0) + $rating,
			];
		}

		usort($ranked, static fn (array $a, array $b): int => $b["score"] <=> $a["score"]);

		$picked = [];
		$seen = [];
		foreach ($ranked as $row) {
			$key = $row["key"];
			if ($key > 0 && isset($seen[$key])) {
				continue;
			}
			if ($key > 0) {
				$seen[$key] = true;
			}
			$picked[] = $row["image"];
			if (count($picked) >= $limit) {
				break;
			}
		}

		if ($picked === []) {
			$hero = $this->getHeroImage();
			if ($hero) {
				$picked[] = $hero;
			}
		}

		return $picked;
	}

	public function clearFiles(): void
	{
		foreach ($this->getFiles() as $file) {
			$file->delete();
		}
	}

	public static function getByPath(string $path): ?Post
	{
		$slug = Slugger::slugifyPagePath($path);
		if ($slug === "") {
			return null;
		}

		return static::getOneBy(["path" => $slug]);
	}

	public static function getByInstagram(string $account, string $shortcode): ?Post
	{
		return static::getOneBy([
			"instagramAccount" => $account,
			"instagramShortcode" => $shortcode,
		]);
	}

	/**
	 * @return list<Post>
	 */
	public static function getPublishedBySection(string $section): array
	{
		$posts = [];
		foreach (static::getBy([
			"section" => $section,
			"status" => self::STATUS_PUBLISHED,
		])->getItems() as $post) {
			if (!$post->isPubliclyVisible()) {
				continue;
			}
			// Události listing/plot = lasting reports only; invitations live on the homepage hero.
			if ($section === Section::UDALOSTI && $post->isInvitation()) {
				continue;
			}
			$posts[] = $post;
		}

		usort($posts, function (Post $a, Post $b) {
			$aTime = (int)($a->getExplicitAt()?->format("U") ?? 0);
			$bTime = (int)($b->getExplicitAt()?->format("U") ?? 0);

			return $bTime <=> $aTime;
		});

		return $posts;
	}

	/**
	 * Published lasting work posts (excludes invitations).
	 *
	 * @return list<Post>
	 */
	public static function getPublished(): array
	{
		$posts = [];
		foreach (static::getBy([
			"status" => self::STATUS_PUBLISHED,
		])->getItems() as $post) {
			if ($post->isInvitation() || !$post->isPubliclyVisible()) {
				continue;
			}
			if (!Section::isWork($post->getSection())) {
				continue;
			}
			$posts[] = $post;
		}

		usort($posts, function (Post $a, Post $b) {
			$aTime = (int)($a->getPublishedAt()?->format("U") ?? 0);
			$bTime = (int)($b->getPublishedAt()?->format("U") ?? 0);

			return $bTime <=> $aTime;
		});

		return $posts;
	}

	/**
	 * Current invitation posts for /udalosti and homepage hero.
	 *
	 * @return list<Post>
	 */
	public static function getCurrentInvitations(): array
	{
		$posts = [];
		foreach (static::getBy([
			"isInvitation" => 1,
			"status" => self::STATUS_PUBLISHED,
		])->getItems() as $post) {
			if ($post->isInvitationCurrent()) {
				$posts[] = $post;
			}
		}

		usort($posts, function (Post $a, Post $b) {
			return ((int)($a->getStartsAt()?->format("U") ?? 0)) <=> ((int)($b->getStartsAt()?->format("U") ?? 0));
		});

		return $posts;
	}

	public static function getUpcomingInvitation(): ?Post
	{
		$current = static::getCurrentInvitations();

		return $current[0] ?? null;
	}

	public static function getByIngestItemId(int $ingestItemId): ?Post
	{
		return static::getOneBy([
			"ingestItemId" => $ingestItemId,
		]);
	}

	public static function pathFor(string $section, string $slug, ?int $ignoreId = null): string
	{
		$base = Section::pathPrefix($section) . "/" . ltrim(Slugger::slugifyPagePath($slug), "/");
		$path = $base;
		$n = 2;

		while (true) {
			$existing = static::getOneBy(["path" => $path]);
			if (!$existing || ($ignoreId !== null && (int)$existing->getId() === $ignoreId)) {
				return $path;
			}

			$path = $base . "-" . $n;
			$n++;
		}
	}

	public function attachStorageFile(
		StorageFile $storageFile,
		int $position,
		?string $caption = null,
		?float $rating = null,
		?string $role = null,
		bool $demote = false,
	): PostFile {
		$link = new PostFile;
		$link->timeCreated = new Time;
		$link->setPost($this);
		$link->setStorageFile($storageFile);
		$link->setPosition($position);
		$link->setCaption($caption);
		$link->setRating($rating);
		$link->setRole($role);
		$link->setDemote($demote);
		$link->persist();

		return $link;
	}

	/**
	 * Best photos from this post for the homepage collage (same post only).
	 * Cover/first stays lead; remaining slots by LLM rating, then role, then position.
	 *
	 * @return list<PostFile>
	 */
	public function getCollageFiles(int $limit = 3): array
	{
		$candidates = [];
		foreach ($this->getFiles() as $file) {
			if ($file->isDemoted() || $file->getRole() === "skip") {
				continue;
			}
			if (!$file->getImage()) {
				continue;
			}
			$candidates[] = $file;
		}

		if ($candidates === []) {
			return [];
		}

		$lead = $candidates[0];
		$rest = array_slice($candidates, 1);
		$roleRank = [
			"lead" => 1,
			"support" => 2,
			"detail" => 3,
			"context" => 4,
		];
		usort($rest, function (PostFile $a, PostFile $b) use ($roleRank) {
			$aRating = $a->getRating() ?? -1.0;
			$bRating = $b->getRating() ?? -1.0;
			if ($aRating !== $bRating) {
				return $bRating <=> $aRating;
			}

			$aRole = $roleRank[$a->getRole() ?? ""] ?? 5;
			$bRole = $roleRank[$b->getRole() ?? ""] ?? 5;
			if ($aRole !== $bRole) {
				return $aRole <=> $bRole;
			}

			return ($a->getPosition() ?? 0) <=> ($b->getPosition() ?? 0);
		});

		$picked = array_merge([$lead], $rest);

		return array_slice($picked, 0, max(1, $limit));
	}
}
