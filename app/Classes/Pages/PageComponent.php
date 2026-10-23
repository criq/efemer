<?php

namespace App\Classes\Pages;

use App\Classes\Pages\Components\Kind;
use App\Classes\Pages\Components\KindCollection;
use App\Classes\Pages\Components\Templates\Template;
use App\Classes\Pages\Components\Templates\TemplateCollection;
use Katu\Tools\Calendar\Time;
use Katu\Tools\Strings\Code;

class PageComponent extends \Katu\Models\Model
{
	const TABLE = "page_components";

	public const LAYOUT_KINDS = ["COLUMNS"];
	public const GALLERY_PROPERTY_KINDS = ["IMAGE_GALLERY", "PAGE_GALLERY", "COLUMNS"];

	public int|string|null $id = null;
	public ?string $kind = null;
	public int $position = 0;
	public int|string|null $pageId = null;
	public int|string|null $parentId = null;
	public Time|string|null $timeCreated = null;
	public ?string $value = null;
	public ?string $template = null;

	public function setTimeCreated(Time $time): PageComponent
	{
		$this->timeCreated = $time;

		return $this;
	}

	public function setPage(Page $page): PageComponent
	{
		$this->pageId = $page->getId();

		return $this;
	}

	public function getPage(): Page
	{
		return Page::get($this->pageId);
	}

	public function setParent(?PageComponent $parent): PageComponent
	{
		$this->parentId = $parent ? $parent->getId() : null;

		return $this;
	}

	public function getParentId(): ?int
	{
		return $this->parentId !== null ? (int)$this->parentId : null;
	}

	public function getParent(): ?PageComponent
	{
		if ($this->parentId === null) {
			return null;
		}

		return static::get($this->parentId);
	}

	public function isRoot(): bool
	{
		return $this->parentId === null;
	}

	public function setKind(Kind $kind): PageComponent
	{
		$this->kind = $kind->getCode()->getConstantFormat();

		return $this;
	}

	public function getKind(): ?Kind
	{
		return KindCollection::createDefault()->filterByCode(new Code($this->kind))->getFirst();
	}

	public function getKindTemplates(): TemplateCollection
	{
		$kind = $this->getKind();

		if (!$kind) {
			return new TemplateCollection([]);
		}

		return $kind::getTemplates();
	}

	public function setTemplateCode(?string $code): PageComponent
	{
		$kind = $this->getKind();
		$this->template = $kind ? $kind::resolveTemplateCode($code) : $code;

		return $this;
	}

	public function getTemplate(): Template
	{
		$collection = $this->getKindTemplates();
		$code = $this->template;

		if (!$code && $this->kind === "IMAGE_GALLERY" && $this->value) {
			$data = json_decode($this->value, true);
			if (is_array($data) && !empty($data["display"])) {
				$code = (string)$data["display"];
				if ($code === "GRID") {
					$code = "NATURAL";
				}
			}
		}

		if (!$code) {
			$code = $collection->getDefaultCode();
		}

		if ($code === "GRID") {
			$code = "NATURAL";
		}

		$template = $collection->filterByCode(new Code($code))->getFirst();

		if ($template) {
			return $template;
		}

		return $collection->getFirst();
	}

	public function isSlideshow(): bool
	{
		return $this->getTemplate()->getCode()->getConstantFormat() === "SLIDESHOW";
	}

	public function setPosition(int $position): PageComponent
	{
		$this->position = $position;

		return $this;
	}

	public function getPosition(): int
	{
		return $this->position;
	}

	public function setValue(?string $value): PageComponent
	{
		$this->value = $value;

		return $this;
	}

	public function getValue(): ?string
	{
		return $this->value;
	}

	public function isGallery(): bool
	{
		return in_array($this->kind, self::GALLERY_PROPERTY_KINDS, true);
	}

	public function isLayout(): bool
	{
		return in_array($this->kind, self::LAYOUT_KINDS, true);
	}

	public function getGalleryProperties(): GalleryProperties
	{
		if (!$this->isGallery()) {
			return GalleryProperties::createDefault();
		}

		return GalleryProperties::fromJson($this->value);
	}

	public function setGalleryProperties(GalleryProperties $properties): PageComponent
	{
		$this->value = $properties->toJson();

		return $this;
	}

	public function getGalleryStyleAttribute(): string
	{
		return $this->getGalleryProperties()->getStyleAttribute();
	}

	public function getChildComponents(): PageComponentCollection
	{
		$children = static::getBy([
			"parentId" => $this->getId(),
		])->getItems();

		usort($children, function (PageComponent $a, PageComponent $b) {
			return $a->getPosition() <=> $b->getPosition();
		});

		return new PageComponentCollection($children);
	}

	public function reorderChildComponents(array $orderedIds): void
	{
		$children = $this->getChildComponents()->getArrayCopy();
		$childrenById = [];

		foreach ($children as $child) {
			$childrenById[(int)$child->getId()] = $child;
		}

		if (count($orderedIds) !== count($childrenById)) {
			return;
		}

		foreach ($orderedIds as $id) {
			if (!isset($childrenById[(int)$id])) {
				return;
			}
		}

		foreach ($orderedIds as $position => $id) {
			$childrenById[(int)$id]->setPosition($position + 1)->persist();
		}
	}

	public function getStorageFiles(): PageComponentStorageFileCollection
	{
		$links = PageComponentStorageFile::getBy([
			"pageComponentId" => $this->getId(),
		])->getItems();

		usort($links, function (PageComponentStorageFile $a, PageComponentStorageFile $b) {
			return ($a->getPosition() ?? 0) <=> ($b->getPosition() ?? 0);
		});

		return new PageComponentStorageFileCollection($links);
	}

	public function reorderStorageFiles(array $orderedIds): void
	{
		$links = $this->getStorageFiles()->getArrayCopy();
		$linksById = [];

		foreach ($links as $link) {
			$linksById[(int)$link->getId()] = $link;
		}

		if (count($orderedIds) !== count($linksById)) {
			return;
		}

		foreach ($orderedIds as $id) {
			if (!isset($linksById[(int)$id])) {
				return;
			}
		}

		foreach ($orderedIds as $position => $id) {
			$linksById[(int)$id]->setPosition($position + 1)->persist();
		}
	}

	public function getPageComponentPages(): PageComponentPageCollection
	{
		$pageComponentPages = PageComponentPage::getBy([
			"pageComponentId" => $this->getId(),
		])->getItems();

		usort($pageComponentPages, function (PageComponentPage $a, PageComponentPage $b) {
			return $a->getPosition() <=> $b->getPosition();
		});

		return new PageComponentPageCollection($pageComponentPages);
	}

	public function getLinkedPages(): PageCollection
	{
		return $this->getPageComponentPages()->getPages();
	}

	public function delete(): bool
	{
		foreach ($this->getChildComponents() as $child) {
			$child->delete();
		}

		foreach ($this->getStorageFiles() as $link) {
			$link->delete();
		}

		foreach ($this->getPageComponentPages() as $pageComponentPage) {
			$pageComponentPage->delete();
		}

		return parent::delete();
	}
}
