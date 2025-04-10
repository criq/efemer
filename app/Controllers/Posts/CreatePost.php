<?php

namespace App\Controllers\Posts;

use App\Classes\Posts\Post;
use Katu\Tools\Calendar\Time;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class CreatePost extends \Katu\Controllers\Controller
{
	public function getResponse(ServerRequestInterface $request, ResponseInterface $response)
	{
		$post = new Post;
		$post->setTimeCreated(new Time);
		$post->persist();

		return $response
			->withStatus(302)
			->withHeader("Location", (string)\Katu\Tools\Routing\URL::getFor("posts.edit", [
				"postId" => $post->getId(),
			]))
			;
	}
}
