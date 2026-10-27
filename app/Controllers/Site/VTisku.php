<?php

namespace App\Controllers\Site;

use App\Classes\Content\Appearance;
use App\Classes\Views\HTMLEngine;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class VTisku extends \Katu\Controllers\Controller
{
	public function getResponse(ServerRequestInterface $request, ResponseInterface $response)
	{
		return $response->withBody((new HTMLEngine($request))->render("Site/v-tisku.twig", [
			"appearances" => Appearance::getOrdered(),
		]));
	}
}
