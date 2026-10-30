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
		return $response->withBody((new HTMLEngine($request))->render("Sections/ritualy.twig", [
			"section" => Section::RITUALY,
			"title" => Section::title(Section::RITUALY),
			"lead" => Section::lead(Section::RITUALY),
			"blurb" => Section::blurb(Section::RITUALY),
			"posts" => Post::getPublishedBySection(Section::RITUALY),
		]));
	}

	public function umeni(ServerRequestInterface $request, ResponseInterface $response)
	{
		return $response->withBody((new HTMLEngine($request))->render("Sections/umeni.twig", [
			"section" => Section::UMENI,
			"title" => Section::title(Section::UMENI),
			"lead" => Section::lead(Section::UMENI),
			"posts" => Post::getPublishedBySection(Section::UMENI),
		]));
	}

	public function denik(ServerRequestInterface $request, ResponseInterface $response)
	{
		return $response->withBody((new HTMLEngine($request))->render("Sections/denik.twig", [
			"section" => Section::DENIK,
			"title" => Section::title(Section::DENIK),
			"lead" => Section::lead(Section::DENIK),
			"posts" => Post::getPublishedBySection(Section::DENIK),
		]));
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
