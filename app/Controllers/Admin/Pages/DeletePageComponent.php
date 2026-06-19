<?php

namespace App\Controllers\Admin\Pages;

use App\Classes\Pages\PageComponent;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class DeletePageComponent extends \Katu\Controllers\Controller
{
	public function getResponse(ServerRequestInterface $request, ResponseInterface $response, string $pageId, string $pageComponentId)
	{
		$pageComponent = PageComponent::get($pageComponentId);
		if (!$pageComponent) {
			throw new \Katu\Exceptions\ModelNotFoundException;
		}

		$pageComponent->delete();

		return $response
			->withStatus(302)
			->withHeader("Location", (string)\Katu\Tools\Routing\URL::getFor("admin.pages.viewPage", [
				"pageId" => $pageId,
			]))
			;
	}
}
