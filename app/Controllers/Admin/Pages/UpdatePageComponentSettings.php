<?php

namespace App\Controllers\Admin\Pages;

use App\Classes\Forms\RequestToken;
use App\Classes\Pages\GalleryProperties;
use App\Classes\Pages\PageComponent;
use Katu\Errors\Error;
use Katu\Errors\ErrorCollection;
use Katu\Tools\Rest\RestResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class UpdatePageComponentSettings extends \Katu\Controllers\Controller
{
	public function getResponse(ServerRequestInterface $request, ResponseInterface $response, string $pageId, string $pageComponentId)
	{
		if ($request->getMethod() !== "POST") {
			throw new \Katu\Exceptions\NotFoundException;
		}

		if (!RequestToken::validate($request)) {
			throw new \Katu\Exceptions\ForbiddenException;
		}

		$pageComponent = PageComponent::get($pageComponentId);
		if (!$pageComponent || (string)$pageComponent->getPage()->getId() !== $pageId) {
			throw new \Katu\Exceptions\ModelNotFoundException;
		}

		if (!$pageComponent->isGallery()) {
			throw new \Katu\Exceptions\ForbiddenException;
		}

		$parsedBody = $request->getParsedBody();
		if (!is_array($parsedBody)) {
			$parsedBody = $_POST;
		}

		if (!array_key_exists("gallery", $parsedBody)) {
			return $response
				->withStatus(400)
				->withHeader("Content-Type", "application/json; charset=UTF-8")
				->withBody((new ErrorCollection([
					new Error("Chybí vlastnosti galerie."),
				]))->getRestResponse()->getStream())
				;
		}

		$pageComponent->setGalleryProperties(GalleryProperties::fromArray($parsedBody["gallery"] ?? []));

		if (array_key_exists("template", $parsedBody)) {
			$template = trim((string)$parsedBody["template"]);
			$pageComponent->setTemplateCode($template !== "" ? $template : null);
		}

		$pageComponent->persist();

		$template = $pageComponent->getTemplate();

		return $response
			->withStatus(200)
			->withHeader("Content-Type", "application/json; charset=UTF-8")
			->withBody((new RestResponse([
				"gallery" => $pageComponent->getGalleryProperties()->toArray(),
				"template" => $template->getCode()->getConstantFormat(),
				"templateTitle" => $template->getTitle(),
			]))->getStream())
			;
	}
}
