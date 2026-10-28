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
				"id" => (int)$file->getId(),
				"position" => $file->getPosition(),
				"caption" => $file->getCaption(),
				"keywords" => $file->getKeywords(),
				"sourceSlot" => $file->getSourceSlot(),
				"sourceSlotLabel" => $file->getSourceSlotLabel(),
				"galleryIndex" => $file->getGalleryIndex(),
				"role" => $file->getRole(),
				"roleLabel" => $file->getRoleLabel(),
				"roleRationale" => $file->getRoleRationale(),
				"roleLocked" => $file->isRoleLocked(),
				"rating" => $file->getRating(),
				"isCover" => $file->isCover(),
				"altText" => $file->getAltText(),
				"orientation" => $file->getOrientation(),
				"focalCrop" => $file->getFocalCrop()
					? json_encode($file->getFocalCrop(), JSON_UNESCAPED_UNICODE)
					: null,
				"demote" => $file->isDemoted(),
				"fileName" => $storage->getFileName(),
				"uri" => $storage->getURI(),
				"thumbnailUrl" => $image ? (string)$image->getImageVersion("THUMBNAIL")->getURL() : null,
				"previewUrl" => $image ? (string)$image->getImageVersion("GALLERY")->getURL() : null,
			];
		}

		$layoutHints = $item->getLayoutHints();
		$classificationRaw = $item->getClassificationRaw();
		$classificationPretty = null;
		if (is_string($classificationRaw) && $classificationRaw !== "") {
			$decoded = json_decode($classificationRaw, true);
			$classificationPretty = is_array($decoded)
				? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
				: $classificationRaw;
		}

		return $response->withBody((new HTMLEngine($request))->render("Admin/Ingest/view.twig", [
			"item" => $item,
			"files" => $files,
			"coverFileId" => $item->getCoverFileId(),
			"coverRationale" => $item->getCoverRationale(),
			"coverLocked" => $item->isCoverLocked(),
			"enrichmentVersion" => $item->getEnrichmentVersion(),
			"enrichedAt" => $item->getEnrichedAt(),
			"entities" => $item->getEntitiesList(),
			"kind" => $item->getKind(),
			"palette" => $item->getPalette(),
			"seriesKey" => $item->getSeriesKey(),
			"layoutHints" => $layoutHints,
			"layoutHintsPretty" => $layoutHints
				? json_encode($layoutHints, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
				: null,
			"classificationPretty" => $classificationPretty,
		]));
	}
}
