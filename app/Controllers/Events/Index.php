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
		$invitations = Post::getCurrentInvitations();
		$groups = [];
		if ($invitations) {
			$groups[] = [
				"title" => "nadcházející",
				"posts" => $invitations,
			];
		}

		return $response->withBody((new HTMLEngine($request))->render("Events/index.twig", [
			"title" => Section::title(Section::UDALOSTI),
			"lead" => Section::lead(Section::UDALOSTI),
			"groups" => $groups,
		]));
	}
}
