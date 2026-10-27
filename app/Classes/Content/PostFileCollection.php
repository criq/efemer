<?php

namespace App\Classes\Content;

class PostFileCollection extends \ArrayObject
{
	public function getFirst(): ?PostFile
	{
		return array_values($this->getArrayCopy())[0] ?? null;
	}

	/**
	 * @return list<PostFile>
	 */
	public function getImages(int $limit = 0): array
	{
		$items = array_values($this->getArrayCopy());
		if ($limit > 0) {
			$items = array_slice($items, 0, $limit);
		}

		return $items;
	}
}
