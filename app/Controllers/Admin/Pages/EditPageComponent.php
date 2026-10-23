<?php

namespace App\Controllers\Admin\Pages;

use App\Classes\Pages\Components\KindCollection;
use App\Classes\Pages\Page;
use App\Classes\Pages\PageGallery\TemplateCollection;
use App\Classes\Pages\PageComponent;
use App\Classes\Views\HTMLEngine;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class EditPageComponent extends \Katu\Controllers\Controller
{
	public function getResponse(ServerRequestInterface $request, ResponseInterface $response, string $pageId, string $pageComponentId)
	{
		$pageComponent = PageComponent::get($pageComponentId);
		if (!$pageComponent || (string)$pageComponent->getPage()->getId() !== $pageId) {
			throw new \Katu\Exceptions\ModelNotFoundException;
		}

		if ($this->isSubmittedWithToken($request)) {
			$validation = $pageComponent->getKind()->validate($pageComponent, $request);
			if (!$validation->hasErrors()) {
				$pageComponent->getKind()->setFromValidation($pageComponent, $validation);

				$parent = $pageComponent->getParent();
				if ($parent) {
					return $response
						->withStatus(302)
						->withHeader("Location", (string)\Katu\Tools\Routing\URL::getFor("admin.pages.editPageComponent", [
							"pageId" => $pageComponent->getPage()->getId(),
							"pageComponentId" => $parent->getId(),
						]))
						;
				}

				return $response
					->withStatus(302)
					->withHeader("Location", (string)\Katu\Tools\Routing\URL::getFor("admin.pages.viewPage", [
						"pageId" => $pageComponent->getPage()->getId(),
					]))
					;
			}
		}

		$childKinds = KindCollection::createDefault();
		if ($pageComponent->isLayout()) {
			$childKinds = new KindCollection(array_values(array_filter(
				$childKinds->getArrayCopy(),
				fn ($kind) => $kind::getCode()->getConstantFormat() !== "COLUMNS",
			)));
		}

		return $response->withBody((new HTMLEngine($request))->render("Admin/Pages/Components/edit.twig", [
			"pageComponent" => $pageComponent,
			"pages" => Page::getAll(),
			"pageGalleryTemplates" => TemplateCollection::createDefault(),
			"childKinds" => $childKinds,
		]));
	}
}
