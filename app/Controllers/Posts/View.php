<?php

namespace App\Controllers\Posts;

use App\Classes\Content\Post;
use App\Classes\Content\Section;
use App\Classes\Views\HTMLEngine;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class View extends \Katu\Controllers\Controller
{
	public function ritualy(ServerRequestInterface $request, ResponseInterface $response, string $slug)
	{
		return $this->render($request, $response, Section::RITUALY, $slug);
	}

	public function himmelhaus(ServerRequestInterface $request, ResponseInterface $response, string $slug)
	{
		return $this->render($request, $response, Section::HIMMELHAUS, $slug);
	}

	public function workshopy(ServerRequestInterface $request, ResponseInterface $response, string $slug)
	{
		return $this->render($request, $response, Section::WORKSHOPY, $slug);
	}

	public function umeni(ServerRequestInterface $request, ResponseInterface $response, string $slug)
	{
		return $this->render($request, $response, Section::UMENI, $slug);
	}

	private function render(ServerRequestInterface $request, ResponseInterface $response, string $section, string $slug): ResponseInterface
	{
		$path = Section::pathPrefix($section) . "/" . ltrim($slug, "/");
		$post = Post::getByPath($path);
		if (!$post || !$post->isPubliclyVisible() || $post->isInvitation()) {
			throw new \Katu\Exceptions\ModelNotFoundException;
		}

		return $response->withBody((new HTMLEngine($request))->render("Posts/article.twig", [
			"post" => $post,
			"sectionTitle" => Section::title($post->getSection()),
			"sectionPath" => Section::pathPrefix($post->getSection()),
		]));
	}
}
