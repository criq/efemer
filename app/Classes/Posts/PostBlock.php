<?php

namespace App\Classes\Posts;

use App\Classes\Posts\Blocks\Kind;
use App\Classes\Posts\Blocks\KindCollection;
use Katu\Tools\Calendar\Time;
use Katu\Tools\Strings\Code;

class PostBlock extends \Katu\Models\Model
{
	const TABLE = "post_blocks";

	public $id;
	public $kind;
	public $postId;
	public $timeCreated;

	public function setTimeCreated(Time $time): PostBlock
	{
		$this->timeCreated = $time;

		return $this;
	}

	public function setPost(Post $post): PostBlock
	{
		$this->postId = $post->getId();

		return $this;
	}

	public function setKind(Kind $kind): PostBlock
	{
		$this->kind = $kind->getCode()->getConstantFormat();

		return $this;
	}

	public function getKind(): ?Kind
	{
		return KindCollection::createDefault()->filterByCode(new Code($this->kind))->getFirst();
	}
}
