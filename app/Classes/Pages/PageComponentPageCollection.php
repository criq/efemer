<?php

namespace App\Classes\Pages;

class PageComponentPageCollection extends \ArrayObject
{
	public static function syncFromPageIds(PageComponent $pageComponent, array $pageIds): PageComponentPageCollection
	{
		foreach (PageComponentPage::getBy([
			"pageComponentId" => $pageComponent->getId(),
		])->getItems() as $pageComponentPage) {
			$pageComponentPage->delete();
		}

		$items = [];
		$seenPageIds = [];

		foreach (array_values($pageIds) as $position => $pageId) {
			$pageId = (int)$pageId;
			if ($pageId <= 0 || isset($seenPageIds[$pageId])) {
				continue;
			}

			$page = Page::get($pageId);
			if (!$page) {
				continue;
			}

			$seenPageIds[$pageId] = true;

			$pageComponentPage = new PageComponentPage;
			$pageComponentPage->setPageComponent($pageComponent);
			$pageComponentPage->setPage($page);
			$pageComponentPage->setPosition($position + 1);
			$pageComponentPage->persist();

			$items[] = $pageComponentPage;
		}

		return new static($items);
	}

	public function getPages(): PageCollection
	{
		$pages = [];

		foreach ($this->getArrayCopy() as $pageComponentPage) {
			$pages[] = $pageComponentPage->getPage();
		}

		return new PageCollection($pages);
	}
}
