<?php

namespace App\Classes\Pages;

class PageComponentGalleryItemCollection extends \ArrayObject
{
	public static function syncFromComponentIds(PageComponent $galleryPageComponent, array $childComponentIds): PageComponentGalleryItemCollection
	{
		foreach (PageComponentGalleryItem::getBy([
			"galleryPageComponentId" => $galleryPageComponent->getId(),
		])->getItems() as $item) {
			$item->delete();
		}

		$items = [];
		$seenIds = [];

		foreach (array_values($childComponentIds) as $position => $childComponentId) {
			$childComponentId = (int)$childComponentId;
			if ($childComponentId <= 0 || isset($seenIds[$childComponentId])) {
				continue;
			}

			if ($childComponentId === (int)$galleryPageComponent->getId()) {
				continue;
			}

			$childPageComponent = PageComponent::get($childComponentId);
			if (!$childPageComponent || (int)$childPageComponent->getPage()->getId() !== (int)$galleryPageComponent->getPage()->getId()) {
				continue;
			}

			if ($childPageComponent->isLayoutGallery()) {
				continue;
			}

			$seenIds[$childComponentId] = true;

			$item = new PageComponentGalleryItem;
			$item->setGalleryPageComponent($galleryPageComponent);
			$item->setChildPageComponent($childPageComponent);
			$item->setPosition($position + 1);
			$item->persist();

			$items[] = $item;
		}

		return new static($items);
	}

	public function getPageComponents(): PageComponentCollection
	{
		$pageComponents = [];

		foreach ($this->getArrayCopy() as $item) {
			$pageComponents[] = $item->getChildPageComponent();
		}

		return new PageComponentCollection($pageComponents);
	}

	public static function getAssignedChildIdsForPage(Page $page): array
	{
		$ids = [];

		foreach ($page->getPageComponents() as $pageComponent) {
			if (!$pageComponent->isLayoutGallery()) {
				continue;
			}

			foreach ($pageComponent->getGalleryChildItems()->getArrayCopy() as $item) {
				$ids[] = (int)$item->childPageComponentId;
			}
		}

		return array_values(array_unique($ids));
	}
}
