<?php

namespace App\Controllers\Admin\Pages;

use App\Classes\Forms\RequestToken;
use App\Classes\Pages\PageComponent;
use Katu\Errors\Error;
use Katu\Errors\ErrorCollection;
use Katu\Tools\Rest\RestResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ReorderStorageFiles extends \Katu\Controllers\Controller
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

		if ($pageComponent->getKind()->getCode()->getConstantFormat() !== "IMAGE_GALLERY") {
			throw new \Katu\Exceptions\ForbiddenException;
		}

		$parsedBody = $request->getParsedBody();
		$order = is_array($parsedBody) ? ($parsedBody["storageFileOrder"] ?? "") : "";
		if ($order === "") {
			$order = $_POST["storageFileOrder"] ?? "";
		}

		$orderedIds = array_values(array_filter(array_map("intval", explode(",", (string)$order))));
		if (!count($orderedIds)) {
			return $response
				->withStatus(400)
				->withHeader("Content-Type", "application/json; charset=UTF-8")
				->withBody((new ErrorCollection([
					new Error("Chybí pořadí souborů."),
				]))->getRestResponse()->getStream())
				;
		}

		$pageComponent->reorderStorageFiles($orderedIds);

		return $response
			->withStatus(200)
			->withHeader("Content-Type", "application/json; charset=UTF-8")
			->withBody((new RestResponse([
				"storageFileOrder" => $orderedIds,
			]))->getStream())
			;
	}
}
