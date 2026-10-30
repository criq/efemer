<?php

namespace App\Controllers\Site;

use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Robots extends \Katu\Controllers\Controller
{
	public function getResponse(ServerRequestInterface $request, ResponseInterface $response)
	{
		$sitemap = (string)\Katu\Tools\Routing\URL::getFor("site.sitemap");
		$body = "User-agent: *\nAllow: /\n\nSitemap: {$sitemap}\n";

		return $response
			->withHeader("Content-Type", "text/plain; charset=UTF-8")
			->withHeader("Cache-Control", "public, max-age=86400")
			->withBody(Utils::streamFor($body));
	}
}
