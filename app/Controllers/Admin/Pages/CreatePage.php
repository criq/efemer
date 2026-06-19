<?php

namespace App\Controllers\Admin\Pages;

use App\Classes\Pages\Page;
use Katu\Tools\Calendar\Time;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class CreatePage extends \Katu\Controllers\Controller
{
	public function getResponse(ServerRequestInterface $request, ResponseInterface $response)
	{
		$page = new Page;
		$page->setTimeCreated(new Time);
		$page->persist();

		return $response
			->withStatus(302)
			->withHeader("Location", (string)\Katu\Tools\Routing\URL::getFor("admin.pages.viewPage", [
				"pageId" => $page->getId(),
			]))
			;
	}
}
