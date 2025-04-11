<?php

namespace App\Controllers\Admin\Posts;

use App\Classes\Posts\Blocks\KindCollection;
use App\Classes\Posts\Post;
use App\Classes\Views\HTMLEngine;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ViewPost extends \Katu\Controllers\Controller
{
	public function getResponse(ServerRequestInterface $request, ResponseInterface $response, string $postId)
	{
		$post = Post::get($postId);
		if (!$post) {
			throw new \Katu\Exceptions\ModelNotFoundException;
		}

		$kinds = KindCollection::createDefault();

		return $response->withBody((new HTMLEngine($request))->render("Admin/Posts/view.twig", [
			"kinds" => $kinds,
			"post" => $post,
		]));
	}
}
