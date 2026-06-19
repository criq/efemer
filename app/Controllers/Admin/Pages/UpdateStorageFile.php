<?php

namespace App\Controllers\Admin\Pages;

use App\Classes\Forms\RequestToken;
use App\Classes\Storage\StorageFile;
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

		$storageFile = StorageFile::get($storageFileId);
		if (!$storageFile) {
			throw new \Katu\Exceptions\ModelNotFoundException;
		}

		$pageComponent = $storageFile->getPageComponent();
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
		$storageFile->setCaption($caption !== "" ? $caption : null)->persist();

		return $response
			->withStatus(200)
			->withHeader("Content-Type", "application/json; charset=UTF-8")
			->withBody((new RestResponse($storageFile->getAdminPayload()))->getStream())
			;
	}
}
