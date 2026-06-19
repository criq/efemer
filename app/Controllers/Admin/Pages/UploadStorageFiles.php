<?php

namespace App\Controllers\Admin\Pages;

use App\Classes\Forms\RequestToken;
use App\Classes\Pages\PageComponent;
use App\Classes\Storage\StorageFileCollection;
use Katu\Errors\Error;
use Katu\Errors\ErrorCollection;
use Katu\Files\UploadCollection;
use Katu\Tools\Rest\RestResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class UploadStorageFiles extends \Katu\Controllers\Controller
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

		$kindCode = $pageComponent->getKind()->getCode()->getConstantFormat();
		if (!in_array($kindCode, ["IMAGE", "IMAGE_GALLERY"], true)) {
			throw new \Katu\Exceptions\ForbiddenException;
		}

		$uploads = UploadCollection::createFromInput($request->getUploadedFiles()["files"] ?? [])->filterWithoutError();
		if (!count($uploads)) {
			return $response
				->withStatus(400)
				->withHeader("Content-Type", "application/json; charset=UTF-8")
				->withBody((new ErrorCollection([
					new Error("Nebyl vybrán žádný soubor."),
				]))->getRestResponse()->getStream())
				;
		}

		if ($kindCode === "IMAGE") {
			StorageFileCollection::replaceWithUpload($pageComponent, $uploads->getFirst());
			$storageFiles = array_filter([$pageComponent->getStorageFiles()->getFirst()]);
		} else {
			$storageFiles = StorageFileCollection::createFromUploads($pageComponent, $uploads)->getArrayCopy();
		}

		return $this->jsonResponse($response, [
			"files" => array_map(function ($storageFile) {
				return $storageFile->getAdminPayload();
			}, $storageFiles),
		]);
	}

	private function jsonResponse(ResponseInterface $response, array $payload, int $status = 200): ResponseInterface
	{
		return $response
			->withStatus($status)
			->withHeader("Content-Type", "application/json; charset=UTF-8")
			->withBody((new RestResponse($payload))->getStream())
			;
	}
}
