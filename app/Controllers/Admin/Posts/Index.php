<?php

namespace App\Controllers\Admin\Posts;

use App\Classes\Content\Post;
use App\Classes\Views\HTMLEngine;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Index extends \Katu\Controllers\Controller
{
	public function getResponse(ServerRequestInterface $request, ResponseInterface $response)
	{
		$posts = Post::getAll()->getItems();
		usort($posts, function (Post $a, Post $b) {
			return ((int)($b->getPublishedAt()?->format("U") ?? 0)) <=> ((int)($a->getPublishedAt()?->format("U") ?? 0));
		});

		$filter = (string)($request->getQueryParams()["status"] ?? "");
		if ($filter !== "") {
			$posts = array_values(array_filter($posts, function (Post $post) use ($filter) {
				return $post->getStatus() === $filter;
			}));
		}

		return $response->withBody((new HTMLEngine($request))->render("Admin/Posts/index.twig", [
			"posts" => $posts,
			"filter" => $filter,
		]));
	}
}
