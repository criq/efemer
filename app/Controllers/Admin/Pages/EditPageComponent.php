<?php

namespace App\Controllers\Admin\Pages;

use App\Classes\Pages\PageComponent;
use App\Classes\Views\HTMLEngine;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class EditPageComponent extends \Katu\Controllers\Controller
{
	public function getResponse(ServerRequestInterface $request, ResponseInterface $response, string $pageId, string $pageComponentId)
	{
		$pageComponent = PageComponent::get($pageComponentId);
		if (!$pageComponent) {
			throw new \Katu\Exceptions\ModelNotFoundException;
		}

		if ($this->isSubmittedWithToken($request)) {
			$validation = $pageComponent->getKind()->validate($pageComponent, $request);
			if (!$validation->hasErrors()) {
				$pageComponent->getKind()->setFromValidation($pageComponent, $validation);

				return $response
					->withStatus(302)
					->withHeader("Location", (string)\Katu\Tools\Routing\URL::getFor("admin.pages.viewPage", [
						"pageId" => $pageComponent->getPage()->getId(),
					]))
					;
			}
		}

		return $response->withBody((new HTMLEngine($request))->render("Admin/Pages/Components/edit.twig", [
			"pageComponent" => $pageComponent,
		]));
	}
}
