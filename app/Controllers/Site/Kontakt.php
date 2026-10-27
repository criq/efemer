<?php

namespace App\Controllers\Site;

use App\Classes\Views\HTMLEngine;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Kontakt extends \Katu\Controllers\Controller
{
	public function getResponse(ServerRequestInterface $request, ResponseInterface $response)
	{
		return $response->withBody((new HTMLEngine($request))->render("Site/kontakt.twig"));
	}
}
