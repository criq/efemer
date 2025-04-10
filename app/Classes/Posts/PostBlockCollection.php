<?php

namespace App\Classes\Posts;

class PostBlockCollection extends \ArrayObject
{
	public function getMaxPosition(): ?int
	{
		$positions = array_map(function (PostBlock $postBlock) {
			return $postBlock->getPosition();
		}, $this->getArrayCopy());

		return count($positions) ? max($positions) : null;
	}
}
