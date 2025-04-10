<?php

namespace App\Classes\Posts;

use App\Classes\Posts\Blocks\Kind;
use Katu\Tools\Calendar\Time;

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
}
