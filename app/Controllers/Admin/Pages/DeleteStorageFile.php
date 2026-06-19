<?php

namespace App\Controllers\Admin\Pages;

use App\Classes\Forms\RequestToken;
use App\Classes\Pages\PageComponentStorageFile;
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

		$link = PageComponentStorageFile::get($storageFileId);
		if (!$link) {
			throw new \Katu\Exceptions\ModelNotFoundException;
		}

		$pageComponent = $link->getPageComponent();
		if ((string)$pageComponent->getId() !== $pageComponentId || (string)$pageComponent->getPage()->getId() !== $pageId) {
			throw new \Katu\Exceptions\ForbiddenException;
		}

		$link->delete();

		return $response
			->withStatus(200)
			->withHeader("Content-Type", "application/json; charset=UTF-8")
			->withBody((new RestResponse([
				"id" => (int)$storageFileId,
			]))->getStream())
			;
	}
}
