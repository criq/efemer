<?php

namespace App\Controllers\Admin\StorageFiles;

use App\Classes\Storage\StorageFile;
use App\Classes\Views\HTMLEngine;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Index extends \Katu\Controllers\Controller
{
	public function getResponse(ServerRequestInterface $request, ResponseInterface $response)
	{
		$storageFiles = StorageFile::getAll()->getItems();

		usort($storageFiles, function (StorageFile $a, StorageFile $b) {
			return $b->getId() <=> $a->getId();
		});

		return $response->withBody((new HTMLEngine($request))->render("Admin/StorageFiles/index.twig", [
			"storageFiles" => $storageFiles,
		]));
	}
}
