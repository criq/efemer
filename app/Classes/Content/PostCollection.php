<?php

namespace App\Classes\Content;

class PostCollection extends \ArrayObject
{
	public function getFirst(): ?Post
	{
		return array_values($this->getArrayCopy())[0] ?? null;
	}
}
