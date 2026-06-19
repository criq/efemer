<?php

namespace App\Classes\Pages;

use App\Classes\Storage\StorageFileCollection;
use App\Classes\Tools\Slugger;
use Katu\Tools\Calendar\Time;

class Page extends \Katu\Models\Model
{
	const TABLE = "pages";

	public int|string|null $id = null;
	public Time|string|null $timeCreated = null;
	public ?string $title = null;
	public ?string $path = null;

	public function setTimeCreated(Time $time): Page
	{
		$this->timeCreated = $time;

		return $this;
	}

	public function setTitle(?string $title): Page
	{
		$this->title = $title;

		return $this;
	}

	public function getTitle(): ?string
	{
		return $this->title;
	}

	public function setPath(?string $path): Page
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
			return Slugger::HOMEPAGE_PATH;
		}

		return str_starts_with($this->path, "/") ? $this->path : "/" . $this->path;
	}

	public function getPublicUrl(): string
	{
		if (Slugger::isHomepagePath($this->getPublicPath())) {
			return (string)\Katu\Tools\Routing\URL::getFor("homepage.index");
		}

		return (string)\Katu\Tools\Routing\URL::getFor("pages.view", [
			"path" => Slugger::getRoutePath($this->getPublicPath()),
		]);
	}

	public static function getHomepage(): ?Page
	{
		return static::getOneBy(["path" => Slugger::HOMEPAGE_PATH]);
	}

	public static function getByPath(string $path): ?Page
	{
		$slug = Slugger::slugifyPagePath($path);
		if ($slug === "") {
			return null;
		}

		return static::getOneBy(["path" => $slug]);
	}

	public function getPageComponents(): PageComponentCollection
	{
		$pageComponents = PageComponent::getBy([
			"pageId" => $this->getId(),
		])->getItems();

		usort($pageComponents, function (PageComponent $a, PageComponent $b) {
			return $a->getPosition() <=> $b->getPosition();
		});

		return new PageComponentCollection($pageComponents);
	}

	public function reorderPageComponents(array $orderedIds): void
	{
		$components = $this->getPageComponents()->getArrayCopy();
		$componentsById = [];

		foreach ($components as $component) {
			$componentsById[(int)$component->getId()] = $component;
		}

		if (count($orderedIds) !== count($componentsById)) {
			return;
		}

		foreach ($orderedIds as $id) {
			if (!isset($componentsById[(int)$id])) {
				return;
			}
		}

		foreach ($orderedIds as $position => $id) {
			$componentsById[(int)$id]->setPosition($position + 1)->persist();
		}
	}

	public function getStorageFiles(): StorageFileCollection
	{
		$storageFiles = [];

		foreach ($this->getPageComponents() as $pageComponent) {
			$storageFiles = array_merge($storageFiles, $pageComponent->getStorageFiles()->getArrayCopy());
		}

		return new StorageFileCollection($storageFiles);
	}
}
