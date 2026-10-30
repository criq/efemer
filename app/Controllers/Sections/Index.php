<?php

namespace App\Controllers\Sections;

use App\Classes\Content\Post;
use App\Classes\Content\Section;
use App\Classes\Views\HTMLEngine;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Index extends \Katu\Controllers\Controller
{
	public function ritualy(ServerRequestInterface $request, ResponseInterface $response)
	{
		return $this->render($request, $response, Section::RITUALY);
	}

	public function umeni(ServerRequestInterface $request, ResponseInterface $response)
	{
		return $this->render($request, $response, Section::UMENI);
	}

	public function denik(ServerRequestInterface $request, ResponseInterface $response)
	{
		return $this->render($request, $response, Section::DENIK);
	}

	private function render(ServerRequestInterface $request, ResponseInterface $response, string $section): ResponseInterface
	{
		return $response->withBody((new HTMLEngine($request))->render("Sections/index.twig", [
			"section" => $section,
			"title" => Section::title($section),
			"lead" => Section::lead($section),
			"posts" => Post::getPublishedBySection($section),
		]));
	}
}
