<?php

namespace App\Config;

use Katu\Tools\Routing\Route;
use Katu\Tools\Routing\RouteCollection;

class RouterConfig extends \Katu\Config\RouterConfig
{
	public function getRoutes(): RouteCollection
	{
		return new RouteCollection([
			"homepage" => new Route("/", [new \App\Controllers\Homepage\Index, "getResponse"]),
			"postBlocks.create" => new Route("/posts/{postId}/blocks/create/{kindCode}", [new \App\Controllers\Posts\CreatePostBlock, "getResponse"]),
			"postBlocks.edit" => new Route("/posts/{postId}/blocks/{postBlockId}/edit", [new \App\Controllers\Posts\EditPostBlock, "getResponse"]),
			"posts.create" => new Route("/posts/create", [new \App\Controllers\Posts\CreatePost, "getResponse"]),
			"posts.edit" => new Route("/posts/{postId}/edit", [new \App\Controllers\Posts\EditPost, "getResponse"]),
			"posts" => new Route("/posts", [new \App\Controllers\Posts\Index, "getResponse"]),
		]);
	}
}
