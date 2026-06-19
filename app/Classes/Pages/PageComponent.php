<?php

namespace App\Classes\Pages;

use App\Classes\Pages\Components\Kind;
use App\Classes\Pages\Components\KindCollection;
use Katu\Tools\Calendar\Time;
use Katu\Tools\Strings\Code;

class PageComponent extends \Katu\Models\Model
{
	const TABLE = "page_components";

	public int|string|null $id = null;
	public ?string $kind = null;
	public int $position = 0;
	public int|string|null $pageId = null;
	public Time|string|null $timeCreated = null;
	public ?string $value = null;

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

	public function setKind(Kind $kind): PageComponent
	{
		$this->kind = $kind->getCode()->getConstantFormat();

		return $this;
	}

	public function getKind(): ?Kind
	{
		return KindCollection::createDefault()->filterByCode(new Code($this->kind))->getFirst();
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

	public function getPageComponentFiles(): PageComponentFileCollection
	{
		return new PageComponentFileCollection(PageComponentFile::getBy([
			"pageComponentId" => $this->getId(),
		])->getItems());
	}
}
