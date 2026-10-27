<?php

namespace App\Classes\Content;

use App\Classes\Html\Excerpt;
use App\Classes\Storage\StorageFile;
use App\Classes\Tools\Slugger;
use Katu\Tools\Calendar\Time;

class Event extends \Katu\Models\Model
{
	const TABLE = "events";

	public int|string|null $id = null;
	public Time|string|null $timeCreated = null;
	public ?string $title = null;
	public ?string $path = null;
	public Time|string|null $startsAt = null;
	public Time|string|null $endsAt = null;
	public ?string $place = null;
	public ?string $bodyHtml = null;
	public ?string $url = null;
	public int|string|null $heroFileId = null;
	public ?string $section = null;
	public int $published = 1;

	public function setTimeCreated(Time $time): Event
	{
		$this->timeCreated = $time;

		return $this;
	}

	public function setTitle(string $title): Event
	{
		$this->title = $title;

		return $this;
	}

	public function getTitle(): ?string
	{
		return $this->title;
	}

	public function setPath(?string $path): Event
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
			return Section::pathPrefix(Section::UDALOSTI);
		}

		return str_starts_with($this->path, "/") ? $this->path : "/" . $this->path;
	}

	public function getPublicUrl(): string
	{
		$publicPath = $this->getPublicPath();
		$base = rtrim((string)\Katu\Tools\Routing\URL::getFor("homepage.index"), "/");

		return $base . $publicPath;
	}

	public function assignPath(): Event
	{
		$start = $this->getStartsAt();
		$date = $start ? $start->format("Y-m-d") : (new Time)->format("Y-m-d");
		$slug = trim($date . " " . (string)$this->getTitle());
		$id = $this->getId() !== null ? (int)$this->getId() : null;
		$this->setPath(static::pathFor($slug, $id));

		return $this;
	}

	public function setStartsAt(Time $time): Event
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

	public function setEndsAt(?Time $time): Event
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

	public function setPlace(?string $place): Event
	{
		$this->place = $place;

		return $this;
	}

	public function getPlace(): ?string
	{
		return $this->place;
	}

	public function setBodyHtml(?string $bodyHtml): Event
	{
		$this->bodyHtml = $bodyHtml;

		return $this;
	}

	public function getBodyHtml(): ?string
	{
		return $this->bodyHtml;
	}

	public function getSummary(): string
	{
		return Excerpt::fromHtml($this->getBodyHtml());
	}

	public function setUrl(?string $url): Event
	{
		$this->url = $url;

		return $this;
	}

	public function getUrl(): ?string
	{
		return $this->url;
	}

	public function isExternal(): bool
	{
		$url = $this->getUrl();

		return is_string($url) && preg_match("#^https?://#i", $url) === 1;
	}

	public function getLinkUrl(): string
	{
		return $this->getPublicUrl();
	}

	public function setSection(?string $section): Event
	{
		$this->section = $section;

		return $this;
	}

	public function getSection(): ?string
	{
		return $this->section;
	}

	public function setPublished(bool $published): Event
	{
		$this->published = $published ? 1 : 0;

		return $this;
	}

	public function isPublished(): bool
	{
		return (int)$this->published === 1;
	}

	public function setHeroFile(?StorageFile $file): Event
	{
		$this->heroFileId = $file ? $file->getId() : null;

		return $this;
	}

	public function getHeroFile(): ?StorageFile
	{
		if (!$this->heroFileId) {
			return null;
		}

		return StorageFile::get($this->heroFileId);
	}

	public function getHeroImage(): ?\Katu\Tools\Images\Image
	{
		$file = $this->getHeroFile();

		return $file ? $file->getImage() : null;
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

	public function isCurrent(): bool
	{
		$end = $this->getEffectiveEnd();

		return $end !== null && (int)$end->format("U") >= time();
	}

	public function isHappening(): bool
	{
		$start = $this->getStartsAt();
		if (!$start || (int)$start->format("U") > time()) {
			return false;
		}

		return $this->isCurrent();
	}

	public function getStartsAtLocal(): string
	{
		$start = $this->getStartsAt();

		return $start ? $start->format("Y-m-d\\TH:i") : "";
	}

	public function getEndsAtLocal(): string
	{
		$end = $this->getEndsAt();

		return $end ? $end->format("Y-m-d\\TH:i") : "";
	}

	public function getDateLabel(): string
	{
		$start = $this->getStartsAt();
		if (!$start) {
			return "";
		}

		$end = $this->getEndsAt();
		if ($end && $end->format("Y-m-d") !== $start->format("Y-m-d")) {
			return $start->format("j. n.") . "–" . $end->format("j. n. Y");
		}

		return $start->format("j. n. Y");
	}

	public function getBannerLabel(): string
	{
		$parts = array_filter([
			$this->getTitle(),
			$this->getPlace(),
			$this->getDateLabel(),
		]);

		return implode(" · ", $parts);
	}

	public static function getByPath(string $path): ?Event
	{
		$slug = Slugger::slugifyPagePath($path);
		if ($slug === "") {
			return null;
		}

		return static::getOneBy(["path" => $slug]);
	}

	public static function pathFor(string $slug, ?int $ignoreId = null): string
	{
		$base = Section::pathPrefix(Section::UDALOSTI) . "/" . ltrim(Slugger::slugifyPagePath($slug), "/");
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

	/**
	 * @return list<Event>
	 */
	public static function getPublished(): array
	{
		$events = static::getBy([
			"published" => 1,
		])->getItems();

		usort($events, function (Event $a, Event $b) {
			return ((int)($b->getStartsAt()?->format("U") ?? 0)) <=> ((int)($a->getStartsAt()?->format("U") ?? 0));
		});

		return $events;
	}

	/**
	 * @return list<Event>
	 */
	public static function getCurrent(): array
	{
		$current = [];
		foreach (static::getPublished() as $event) {
			if ($event->isCurrent()) {
				$current[] = $event;
			}
		}

		usort($current, function (Event $a, Event $b) {
			return ((int)($a->getStartsAt()?->format("U") ?? 0)) <=> ((int)($b->getStartsAt()?->format("U") ?? 0));
		});

		return $current;
	}

	/**
	 * @return list<Event>
	 */
	public static function getPast(): array
	{
		$past = [];
		foreach (static::getPublished() as $event) {
			if (!$event->isCurrent()) {
				$past[] = $event;
			}
		}

		return $past;
	}

	public static function getUpcoming(): ?Event
	{
		$current = static::getCurrent();

		return $current[0] ?? null;
	}
}
