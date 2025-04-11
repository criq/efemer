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
			"images.getVersion" => new Route("/images/{imagePackage}/{versionCode}.{extension}", [new \App\Controllers\Images, "getVersion"]),
			"admin.postBlocks.create" => new Route("/posts/{postId}/blocks/create/{kindCode}", [new \App\Controllers\Admin\Posts\CreatePostBlock, "getResponse"]),
			"admin.postBlocks.edit" => new Route("/posts/{postId}/blocks/{postBlockId}/edit", [new \App\Controllers\Admin\Posts\EditPostBlock, "getResponse"]),
			"admin.posts.create" => new Route("/posts/create", [new \App\Controllers\Admin\Posts\CreatePost, "getResponse"]),
			"admin.posts.edit" => new Route("/posts/{postId}/edit", [new \App\Controllers\Admin\Posts\EditPost, "getResponse"]),
			"admin.posts" => new Route("/posts", [new \App\Controllers\Admin\Posts\Index, "getResponse"]),
		]);
	}
}
