<?php

namespace App\Classes\Pages;

use App\Classes\Pages\PageGallery\TemplateCollection;
use Katu\Tools\Strings\Code;

class PageComponentPageCollection extends \ArrayObject
{
	public static function syncFromPageEntries(PageComponent $pageComponent, array $entries): PageComponentPageCollection
	{
		foreach (PageComponentPage::getBy([
			"pageComponentId" => $pageComponent->getId(),
		])->getItems() as $pageComponentPage) {
			$pageComponentPage->delete();
		}

		$items = [];
		$seenPageIds = [];
		$templates = TemplateCollection::createDefault();

		foreach (array_values($entries) as $position => $entry) {
			$pageId = (int)($entry["pageId"] ?? 0);
			if ($pageId <= 0 || isset($seenPageIds[$pageId])) {
				continue;
			}

			$page = Page::get($pageId);
			if (!$page) {
				continue;
			}

			$seenPageIds[$pageId] = true;

			$templateCode = new Code((string)($entry["template"] ?? TemplateCollection::getDefaultCode()));
			$template = $templates->filterByCode($templateCode)->getFirst();
			if (!$template) {
				$template = $templates->filterByCode(new Code(TemplateCollection::getDefaultCode()))->getFirst();
			}

			$pageComponentPage = new PageComponentPage;
			$pageComponentPage->setPageComponent($pageComponent);
			$pageComponentPage->setPage($page);
			$pageComponentPage->setPosition($position + 1);
			$pageComponentPage->setTemplate($template);
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
