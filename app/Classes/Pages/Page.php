<?php

namespace App\Classes\Pages;

use App\Classes\Pages\PageComponentStorageFileCollection;
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
		if ($path === "" || Slugger::isHomepagePath($path)) {
			$this->path = Slugger::HOMEPAGE_PATH;
		} else {
			$this->path = $path;
		}

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

		if (Slugger::isHomepagePath($publicPath)) {
			return (string)\Katu\Tools\Routing\URL::getFor("homepage.index");
		}

		return (string)\Katu\Tools\Routing\URL::getFor("pages.view", [
			"path" => Slugger::getRoutePath($publicPath),
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

	public function getRootPageComponents(): PageComponentCollection
	{
		$assignedChildIds = array_flip(PageComponentGalleryItemCollection::getAssignedChildIdsForPage($this));
		$rootComponents = [];

		foreach ($this->getPageComponents() as $pageComponent) {
			if (isset($assignedChildIds[(int)$pageComponent->getId()])) {
				continue;
			}

			$rootComponents[] = $pageComponent;
		}

		return new PageComponentCollection($rootComponents);
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

	public function getStorageFiles(): PageComponentStorageFileCollection
	{
		$links = [];

		foreach ($this->getPageComponents() as $pageComponent) {
			$links = array_merge($links, $pageComponent->getStorageFiles()->getArrayCopy());
		}

		return new PageComponentStorageFileCollection($links);
	}

	public function persist(): static
	{
		$needsIdBasedPath = $this->path === null || $this->path === "";

		if ($needsIdBasedPath && $this->id !== null) {
			$this->path = "/stranka-" . $this->id;
		} elseif ($needsIdBasedPath) {
			$this->path = "/stranka-pending-" . uniqid("", true);
		}

		$result = parent::persist();

		if ($needsIdBasedPath && str_starts_with((string)$this->path, "/stranka-pending-") && $this->id !== null) {
			$this->path = "/stranka-" . $this->id;
			parent::persist();
		}

		return $result;
	}
}
