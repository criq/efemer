<?php

namespace App\Controllers\Admin\Ingest;

use App\Classes\Ingest\IngestItem;
use App\Classes\Views\HTMLEngine;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class View extends \Katu\Controllers\Controller
{
	public function getResponse(ServerRequestInterface $request, ResponseInterface $response, string $ingestItemId)
	{
		$item = IngestItem::get((int)$ingestItemId);
		if (!$item) {
			throw new \Katu\Exceptions\ModelNotFoundException;
		}

		$files = [];
		foreach ($item->getFiles() as $file) {
			$storage = $file->getStorageFile();
			$image = null;
			try {
				$image = $storage->getImage();
			} catch (\Throwable) {
			}

			$files[] = [
				"position" => $file->getPosition(),
				"caption" => $file->getCaption(),
				"keywords" => $file->getKeywords(),
				"fileName" => $storage->getFileName(),
				"uri" => $storage->getURI(),
				"thumbnailUrl" => $image ? (string)$image->getImageVersion("THUMBNAIL")->getURL() : null,
				"previewUrl" => $image ? (string)$image->getImageVersion("GALLERY")->getURL() : null,
			];
		}

		return $response->withBody((new HTMLEngine($request))->render("Admin/Ingest/view.twig", [
			"item" => $item,
			"files" => $files,
		]));
	}
}
