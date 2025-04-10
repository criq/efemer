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
}
