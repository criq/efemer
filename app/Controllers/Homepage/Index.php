<?php

namespace App\Controllers\Homepage;

use App\Classes\Views\HTMLEngine;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Index extends \Katu\Controllers\Controller
{
	public function getResponse(ServerRequestInterface $request, ResponseInterface $response)
	{
		return $response->withBody((new HTMLEngine($request))->render("Homepage/index.twig"));
	}
}
