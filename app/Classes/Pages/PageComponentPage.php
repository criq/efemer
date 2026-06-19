<?php

namespace App\Classes\Pages;

class PageComponentPage extends \Katu\Models\Model
{
	const TABLE = "page_component_pages";

	public int|string|null $id = null;
	public int|string|null $pageComponentId = null;
	public int|string|null $pageId = null;
	public int $position = 0;

	public function setPageComponent(PageComponent $pageComponent): PageComponentPage
	{
		$this->pageComponentId = $pageComponent->getId();

		return $this;
	}

	public function setPage(Page $page): PageComponentPage
	{
		$this->pageId = $page->getId();

		return $this;
	}

	public function setPosition(int $position): PageComponentPage
	{
		$this->position = $position;

		return $this;
	}

	public function getPosition(): int
	{
		return $this->position;
	}

	public function getPage(): Page
	{
		return Page::get($this->pageId);
	}
}
