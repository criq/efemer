<?php

namespace App\Classes\Posts;

use Katu\Tools\Calendar\Time;

class Post extends \Katu\Models\Model
{
	const TABLE = "posts";

	public $id;
	public $timeCreated;
	public $title;

	public function setTimeCreated(Time $time): Post
	{
		$this->timeCreated = $time;

		return $this;
	}

	public function getPostBlocks(): PostBlockCollection
	{
		return new PostBlockCollection(PostBlock::getBy([
			"postId" => $this->getId(),
		])->getItems());
	}

	public function getPostBlockFiles(): PostBlockFileCollection
	{
		return new PostBlockFileCollection(array_merge(...array_map(function (PostBlock $postBlock) {
			return $postBlock->getPostBlockFiles()->getArrayCopy();
		}, $this->getPostBlocks()->getArrayCopy())));
	}
}
