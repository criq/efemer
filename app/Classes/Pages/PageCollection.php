<?php

namespace App\Classes\Pages;

class PageCollection extends \ArrayObject
{
	public function getFirst(): ?Page
	{
		return array_values($this->getArrayCopy())[0] ?? null;
	}
}
