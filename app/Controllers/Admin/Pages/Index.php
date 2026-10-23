<?php

namespace App\Controllers\Admin\Pages;

use App\Classes\Pages\Page;
use App\Classes\Views\HTMLEngine;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Index extends \Katu\Controllers\Controller
{
	public function getResponse(ServerRequestInterface $request, ResponseInterface $response)
	{
		$pages = Page::getAll()->getItems();
		usort($pages, function (Page $a, Page $b) {
			return strcasecmp((string)$a->getPublicPath(), (string)$b->getPublicPath());
		});

		return $response->withBody((new HTMLEngine($request))->render("Admin/Pages/index.twig", [
			"pages" => $pages,
		]));
	}
}
