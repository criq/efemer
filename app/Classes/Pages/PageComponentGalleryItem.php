<?php

namespace App\Classes\Pages;

class PageComponentGalleryItem extends \Katu\Models\Model
{
	const TABLE = "page_component_gallery_items";

	public int|string|null $id = null;
	public int|string|null $galleryPageComponentId = null;
	public int|string|null $childPageComponentId = null;
	public int $position = 0;

	public function setGalleryPageComponent(PageComponent $pageComponent): PageComponentGalleryItem
	{
		$this->galleryPageComponentId = $pageComponent->getId();

		return $this;
	}

	public function setChildPageComponent(PageComponent $pageComponent): PageComponentGalleryItem
	{
		$this->childPageComponentId = $pageComponent->getId();

		return $this;
	}

	public function setPosition(int $position): PageComponentGalleryItem
	{
		$this->position = $position;

		return $this;
	}

	public function getPosition(): int
	{
		return $this->position;
	}

	public function getChildPageComponent(): PageComponent
	{
		return PageComponent::get($this->childPageComponentId);
	}
}
