<?php

namespace App\Controllers\Events;

use App\Classes\Content\Post;
use App\Classes\Content\Section;
use App\Classes\Views\HTMLEngine;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Index extends \Katu\Controllers\Controller
{
	public function getResponse(ServerRequestInterface $request, ResponseInterface $response)
	{
		$groups = [];

		$invitations = Post::getCurrentInvitations();
		if ($invitations !== []) {
			$groups[] = [
				"title" => "nadcházející",
				"posts" => $invitations,
			];
		}

		$reports = Post::getPublishedBySection(Section::UDALOSTI);
		if ($reports !== []) {
			$groups[] = [
				"title" => "reportáže",
				"posts" => $reports,
			];
		}

		return $response->withBody((new HTMLEngine($request))->render("Events/index.twig", [
			"title" => Section::title(Section::UDALOSTI),
			"lead" => Section::lead(Section::UDALOSTI),
			"groups" => $groups,
		]));
	}
}
