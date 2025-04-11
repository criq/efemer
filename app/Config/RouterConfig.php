<?php

namespace App\Config;

use Katu\Tools\Routing\Route;
use Katu\Tools\Routing\RouteCollection;

class RouterConfig extends \Katu\Config\RouterConfig
{
	public function getRoutes(): RouteCollection
	{
		return new RouteCollection([
			"admin.postBlocks.create" => new Route("/posts/{postId}/blocks/create/{kindCode}", [\App\Controllers\Admin\Posts\CreatePostBlock::class, "getResponse"]),
			"admin.postBlocks.delete" => new Route("/posts/{postId}/blocks/{postBlockId}/delete", [\App\Controllers\Admin\Posts\EditPostBlock::class, "getResponse"]),
			"admin.postBlocks.edit" => new Route("/posts/{postId}/blocks/{postBlockId}/edit", [\App\Controllers\Admin\Posts\EditPostBlock::class, "getResponse"]),
			"admin.postBlocks.view" => new Route("/posts/{postId}/blocks/{postBlockId}", [\App\Controllers\Admin\Posts\ViewPostBlock::class, "getResponse"]),
			"admin.posts.create" => new Route("/posts/create", [\App\Controllers\Admin\Posts\CreatePost::class, "getResponse"]),
			"admin.posts.delete" => new Route("/posts/{postId}/delete", [\App\Controllers\Admin\Posts\DeletePost::class, "getResponse"]),
			"admin.posts.edit" => new Route("/posts/{postId}/edit", [\App\Controllers\Admin\Posts\EditPost::class, "getResponse"]),
			"admin.posts.view" => new Route("/posts/{postId}", [\App\Controllers\Admin\Posts\ViewPost::class, "getResponse"]),
			"admin.posts" => new Route("/posts", [\App\Controllers\Admin\Posts\Index::class, "getResponse"]),
			"homepage" => new Route("/", [\App\Controllers\Homepage\Index::class, "getResponse"]),
			"images.getVersion" => new Route("/images/{imagePackage}/{versionCode}.{extension}", [\App\Controllers\Images::class, "getVersion"]),
		]);
	}
}
