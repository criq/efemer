<?php

namespace App\Classes\Pages;

use Katu\Tools\Calendar\Time;

class Page extends \Katu\Models\Model
{
	const TABLE = "pages";

	public int|string|null $id = null;
	public Time|string|null $timeCreated = null;
	public ?string $title = null;

	public function setTimeCreated(Time $time): Page
	{
		$this->timeCreated = $time;

		return $this;
	}

	public function getPageComponents(): PageComponentCollection
	{
		return new PageComponentCollection(PageComponent::getBy([
			"pageId" => $this->getId(),
		])->getItems());
	}

	public function getPageComponentFiles(): PageComponentFileCollection
	{
		return new PageComponentFileCollection(array_merge(...array_map(function (PageComponent $pageComponent) {
			return $pageComponent->getPageComponentFiles()->getArrayCopy();
		}, $this->getPageComponents()->getArrayCopy())));
	}
}
