<?php

namespace App\Controllers\Admin\Ingest;

use App\Classes\Ingest\IngestItem;
use App\Classes\Views\HTMLEngine;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Index extends \Katu\Controllers\Controller
{
	public function getResponse(ServerRequestInterface $request, ResponseInterface $response)
	{
		$params = $request->getQueryParams();
		$sourceFilter = (string)($params["source"] ?? "");
		$statusFilter = (string)($params["status"] ?? "");
		$accountFilter = (string)($params["account"] ?? "");

		$items = IngestItem::getAll()->getItems();
		usort($items, function (IngestItem $a, IngestItem $b) {
			return ((int)($b->getPostedAt()?->format("U") ?? 0)) <=> ((int)($a->getPostedAt()?->format("U") ?? 0));
		});

		if ($sourceFilter !== "") {
			$items = array_values(array_filter($items, fn (IngestItem $item) => $item->getSource() === $sourceFilter));
		}
		if ($statusFilter !== "") {
			$items = array_values(array_filter($items, fn (IngestItem $item) => $item->getStatus() === $statusFilter));
		}
		if ($accountFilter !== "") {
			$items = array_values(array_filter($items, fn (IngestItem $item) => $item->getInstagramAccount() === $accountFilter));
		}

		return $response->withBody((new HTMLEngine($request))->render("Admin/Ingest/index.twig", [
			"items" => $items,
			"sourceFilter" => $sourceFilter,
			"statusFilter" => $statusFilter,
			"accountFilter" => $accountFilter,
		]));
	}
}
