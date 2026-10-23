<?php

namespace App\Controllers\Admin\Pages\Builder;

use App\Classes\Pages\PageComponent;
use App\Classes\Views\HTMLEngine;
use Katu\Tools\Rest\RestResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Fragment extends \Katu\Controllers\Controller
{
	public function getResponse(ServerRequestInterface $request, ResponseInterface $response, string $pageId, string $pageComponentId)
	{
		$pageComponent = PageComponent::get($pageComponentId);
		if (!$pageComponent || (string)$pageComponent->getPage()->getId() !== $pageId) {
			throw new \Katu\Exceptions\ModelNotFoundException;
		}

		$html = (new HTMLEngine($request))->render("Admin/Pages/Builder/block.twig", [
			"pageComponent" => $pageComponent,
			"page" => $pageComponent->getPage(),
		]);

		return $response
			->withStatus(200)
			->withHeader("Content-Type", "application/json; charset=UTF-8")
			->withBody((new RestResponse([
				"pageComponentId" => (int)$pageComponent->getId(),
				"html" => (string)$html,
			]))->getStream())
			;
	}
}
