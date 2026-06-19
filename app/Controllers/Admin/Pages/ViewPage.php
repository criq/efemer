<?php

namespace App\Controllers\Admin\Pages;

use App\Classes\Pages\Components\KindCollection;
use App\Classes\Pages\Page;
use App\Classes\Views\HTMLEngine;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ViewPage extends \Katu\Controllers\Controller
{
	public function getResponse(ServerRequestInterface $request, ResponseInterface $response, string $pageId)
	{
		$page = Page::get($pageId);
		if (!$page) {
			throw new \Katu\Exceptions\ModelNotFoundException;
		}

		if ($this->isSubmittedWithToken($request)) {
			$page->persist();

			return $response
				->withStatus(302)
				->withHeader("Location", (string)\Katu\Tools\Routing\URL::getFor("admin.pages.index"))
				;
		}

		$kinds = KindCollection::createDefault();

		return $response->withBody((new HTMLEngine($request))->render("Admin/Pages/view.twig", [
			"kinds" => $kinds,
			"page" => $page,
		]));
	}
}
