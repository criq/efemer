<?php

namespace App\Controllers\Admin;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Index extends \Katu\Controllers\Controller
{
	public function getResponse(ServerRequestInterface $request, ResponseInterface $response)
	{
		return $response
			->withStatus(302)
			->withHeader("Location", (string)\Katu\Tools\Routing\URL::getFor("admin.pages.index"))
			;
	}
}
