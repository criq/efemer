<?php

namespace App\Controllers\Admin\StorageFiles;

use App\Classes\Pages\PageComponentStorageFile;
use App\Classes\Views\HTMLEngine;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Index extends \Katu\Controllers\Controller
{
	public function getResponse(ServerRequestInterface $request, ResponseInterface $response)
	{
		$links = PageComponentStorageFile::getAll()->getItems();

		usort($links, function (PageComponentStorageFile $a, PageComponentStorageFile $b) {
			return $b->getId() <=> $a->getId();
		});

		return $response->withBody((new HTMLEngine($request))->render("Admin/StorageFiles/index.twig", [
			"storageFiles" => $links,
		]));
	}
}
