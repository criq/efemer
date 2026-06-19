<?php

namespace App\Controllers\Admin\Pages;

use App\Classes\Pages\Page;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class DeletePage extends \Katu\Controllers\Controller
{
	public function getResponse(ServerRequestInterface $request, ResponseInterface $response, string $pageId)
	{
		$page = Page::get($pageId);
		if (!$page) {
			throw new \Katu\Exceptions\ModelNotFoundException;
		}

		$page->delete();

		return $response
			->withStatus(302)
			->withHeader("Location", (string)\Katu\Tools\Routing\URL::getFor("admin.pages.index"))
			;
	}
}
