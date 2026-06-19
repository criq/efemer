<?php

namespace App\Controllers\Pages;

use App\Classes\Pages\Page;
use App\Classes\Views\HTMLEngine;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class View extends \Katu\Controllers\Controller
{
	public function getHomepage(ServerRequestInterface $request, ResponseInterface $response)
	{
		return $this->render($request, $response, Page::getHomepage());
	}

	public function getByPath(ServerRequestInterface $request, ResponseInterface $response, string $path)
	{
		return $this->render($request, $response, Page::getByPath($path));
	}

	private function render(ServerRequestInterface $request, ResponseInterface $response, ?Page $page): ResponseInterface
	{
		if (!$page) {
			throw new \Katu\Exceptions\ModelNotFoundException;
		}

		return $response->withBody((new HTMLEngine($request))->render("Pages/view.twig", [
			"page" => $page,
		]));
	}
}
