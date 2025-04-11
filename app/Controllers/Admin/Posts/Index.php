<?php

namespace App\Controllers\Admin\Posts;

use App\Classes\Posts\Post;
use App\Classes\Views\HTMLEngine;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Index extends \Katu\Controllers\Controller
{
	public function getResponse(ServerRequestInterface $request, ResponseInterface $response)
	{
		$posts = Post::getAll();

		return $response->withBody((new HTMLEngine($request))->render("Admin/Posts/index.twig", [
			"posts" => $posts,
		]));
	}
}
