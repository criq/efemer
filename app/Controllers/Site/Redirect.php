<?php

namespace App\Controllers\Site;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Redirect extends \Katu\Controllers\Controller
{
	public function toDenik(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		return $response
			->withStatus(301)
			->withHeader("Location", (string)\Katu\Tools\Routing\URL::getFor("sections.denik"));
	}

	public function toDenikPost(ServerRequestInterface $request, ResponseInterface $response, string $slug): ResponseInterface
	{
		$base = rtrim((string)\Katu\Tools\Routing\URL::getFor("sections.denik"), "/");

		return $response
			->withStatus(301)
			->withHeader("Location", $base . "/" . ltrim($slug, "/"));
	}
}
