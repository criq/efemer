<?php

namespace App\Classes\Content;

use Katu\Tools\Calendar\Time;

class Appearance extends \Katu\Models\Model
{
	const TABLE = "appearances";

	public int|string|null $id = null;
	public Time|string|null $timeCreated = null;
	public ?string $title = null;
	public ?string $outlet = null;
	public ?string $url = null;
	public ?string $publishedAt = null;

	public function setTimeCreated(Time $time): Appearance
	{
		$this->timeCreated = $time;

		return $this;
	}

	public function setTitle(string $title): Appearance
	{
		$this->title = $title;

		return $this;
	}

	public function getTitle(): ?string
	{
		return $this->title;
	}

	public function setOutlet(?string $outlet): Appearance
	{
		$this->outlet = $outlet;

		return $this;
	}

	public function getOutlet(): ?string
	{
		return $this->outlet;
	}

	public function setUrl(?string $url): Appearance
	{
		$this->url = $url;

		return $this;
	}

	public function getUrl(): ?string
	{
		return $this->url;
	}

	public function setPublishedAt(?string $date): Appearance
	{
		$this->publishedAt = $date;

		return $this;
	}

	public function getPublishedAt(): ?string
	{
		return $this->publishedAt;
	}

	public function getDateLabel(): string
	{
		if (!$this->publishedAt) {
			return "";
		}

		try {
			return (new Time($this->publishedAt))->format("Y");
		} catch (\Throwable) {
			return $this->publishedAt;
		}
	}

	/**
	 * @return list<Appearance>
	 */
	public static function getOrdered(): array
	{
		$items = static::getAll()->getItems();
		usort($items, function (Appearance $a, Appearance $b) {
			return strcmp((string)$b->publishedAt, (string)$a->publishedAt);
		});

		return $items;
	}
}
