<?php

namespace App\Controllers\Events;

use App\Classes\Content\Post;
use App\Classes\Content\Section;
use App\Classes\Views\HTMLEngine;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class View extends \Katu\Controllers\Controller
{
	public function getResponse(ServerRequestInterface $request, ResponseInterface $response, string $slug)
	{
		$path = Section::pathPrefix(Section::UDALOSTI) . "/" . ltrim($slug, "/");
		$post = Post::getByPath($path);
		if (!$post || !$post->isInvitation() || !$post->isPubliclyVisible()) {
			throw new \Katu\Exceptions\ModelNotFoundException;
		}

		return $response->withBody((new HTMLEngine($request))->render("Events/invitation.twig", [
			"post" => $post,
			"sectionTitle" => Section::title(Section::UDALOSTI),
			"sectionPath" => Section::pathPrefix(Section::UDALOSTI),
		]));
	}
}
