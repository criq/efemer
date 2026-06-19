<?php

namespace App\Classes\Pages;

use App\Classes\Pages\PageGallery\Template;
use App\Classes\Pages\PageGallery\TemplateCollection;
use Katu\Tools\Strings\Code;

class PageComponentPage extends \Katu\Models\Model
{
	const TABLE = "page_component_pages";

	public int|string|null $id = null;
	public int|string|null $pageComponentId = null;
	public int|string|null $pageId = null;
	public int $position = 0;
	public ?string $template = null;

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

	public function setTemplate(Template $template): PageComponentPage
	{
		$this->template = $template->getCode()->getConstantFormat();

		return $this;
	}

	public function getTemplate(): Template
	{
		$code = new Code($this->template ?: TemplateCollection::getDefaultCode());
		$template = TemplateCollection::createDefault()->filterByCode($code)->getFirst();

		if ($template) {
			return $template;
		}

		return TemplateCollection::createDefault()->filterByCode(new Code(TemplateCollection::getDefaultCode()))->getFirst();
	}

	public function getPage(): Page
	{
		return Page::get($this->pageId);
	}
}
