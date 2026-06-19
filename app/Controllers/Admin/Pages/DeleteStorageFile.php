<?php

namespace App\Controllers\Admin\Pages;

use App\Classes\Forms\RequestToken;
use App\Classes\Storage\StorageFile;
use Katu\Tools\Rest\RestResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class DeleteStorageFile extends \Katu\Controllers\Controller
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

		$storageFile->delete();

		return $response
			->withStatus(200)
			->withHeader("Content-Type", "application/json; charset=UTF-8")
			->withBody((new RestResponse([
				"id" => (int)$storageFileId,
			]))->getStream())
			;
	}
}
