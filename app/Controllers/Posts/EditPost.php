<?php

namespace App\Controllers\Posts;

use App\Classes\Posts\Blocks\KindCollection;
use App\Classes\Posts\Post;
use App\Classes\Views\HTMLEngine;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class EditPost extends \Katu\Controllers\Controller
{
	public function getResponse(ServerRequestInterface $request, ResponseInterface $response, string $postId)
	{
		$post = Post::get($postId);

		$kinds = KindCollection::createDefault();

		return $response->withBody((new HTMLEngine($request))->render("Posts/edit.twig", [
			"kinds" => $kinds,
			"post" => $post,
		]));
	}
}
