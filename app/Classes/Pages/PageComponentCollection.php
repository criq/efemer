<?php

namespace App\Classes\Pages;

class PageComponentCollection extends \ArrayObject
{
	public function getMaxPosition(): ?int
	{
		$positions = array_map(function (PageComponent $pageComponent) {
			return $pageComponent->getPosition();
		}, $this->getArrayCopy());

		return count($positions) ? max($positions) : null;
	}
}
