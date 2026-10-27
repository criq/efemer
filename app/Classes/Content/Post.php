<?php

namespace App\Classes\Content;

use App\Classes\Html\Excerpt;
use App\Classes\Storage\StorageFile;
use App\Classes\Tools\Slugger;
use Katu\Tools\Calendar\Time;

class Post extends \Katu\Models\Model
{
	const TABLE = "posts";

	public int|string|null $id = null;
	public Time|string|null $timeCreated = null;
	public Time|string|null $publishedAt = null;
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

	public function setTitle(?string $title): Post
	{
		$this->title = $title;

		return $this;
	}

	public function getTitle(): ?string
	{
		return $this->title;
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
		$posts = static::getBy([
			"section" => $section,
			"status" => self::STATUS_PUBLISHED,
		])->getItems();

		usort($posts, function (Post $a, Post $b) {
			$aTime = (int)($a->getPublishedAt()?->format("U") ?? 0);
			$bTime = (int)($b->getPublishedAt()?->format("U") ?? 0);

			return $bTime <=> $aTime;
		});

		return $posts;
	}

	/**
	 * @return list<Post>
	 */
	public static function getPublished(): array
	{
		$posts = static::getBy([
			"status" => self::STATUS_PUBLISHED,
		])->getItems();

		usort($posts, function (Post $a, Post $b) {
			$aTime = (int)($a->getPublishedAt()?->format("U") ?? 0);
			$bTime = (int)($b->getPublishedAt()?->format("U") ?? 0);

			return $bTime <=> $aTime;
		});

		return $posts;
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

	public function attachStorageFile(StorageFile $storageFile, int $position, ?string $caption = null): PostFile
	{
		$link = new PostFile;
		$link->timeCreated = new Time;
		$link->setPost($this);
		$link->setStorageFile($storageFile);
		$link->setPosition($position);
		$link->setCaption($caption);
		$link->persist();

		return $link;
	}
}
