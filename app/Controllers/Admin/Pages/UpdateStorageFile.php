<?php

namespace App\Controllers\Admin\Pages;

use App\Classes\Forms\RequestToken;
use App\Classes\Pages\PageComponentStorageFile;
use Katu\Errors\Error;
use Katu\Errors\ErrorCollection;
use Katu\Tools\Rest\RestResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class UpdateStorageFile extends \Katu\Controllers\Controller
{
	public function getResponse(ServerRequestInterface $request, ResponseInterface $response, string $pageId, string $pageComponentId, string $storageFileId)
	{
		if ($request->getMethod() !== "POST") {
			throw new \Katu\Exceptions\NotFoundException;
		}

		if (!RequestToken::validate($request)) {
			throw new \Katu\Exceptions\ForbiddenException;
		}

		$link = PageComponentStorageFile::get($storageFileId);
		if (!$link) {
			throw new \Katu\Exceptions\ModelNotFoundException;
		}

		$pageComponent = $link->getPageComponent();
		if ((string)$pageComponent->getId() !== $pageComponentId || (string)$pageComponent->getPage()->getId() !== $pageId) {
			throw new \Katu\Exceptions\ForbiddenException;
		}

		$parsedBody = $request->getParsedBody();
		if (!is_array($parsedBody)) {
			$parsedBody = $_POST;
		}

		if (!array_key_exists("caption", $parsedBody)) {
			return $response
				->withStatus(400)
				->withHeader("Content-Type", "application/json; charset=UTF-8")
				->withBody((new ErrorCollection([
					new Error("Chybí popisek."),
				]))->getRestResponse()->getStream())
				;
		}

		$caption = trim((string)$parsedBody["caption"]);
		$link->setCaption($caption !== "" ? $caption : null)->persist();

		return $response
			->withStatus(200)
			->withHeader("Content-Type", "application/json; charset=UTF-8")
			->withBody((new RestResponse($link->getAdminPayload()))->getStream())
			;
	}
}
