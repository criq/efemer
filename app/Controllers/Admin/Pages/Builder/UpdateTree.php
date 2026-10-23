<?php

namespace App\Controllers\Admin\Pages\Builder;

use App\Classes\Forms\RequestToken;
use App\Classes\Pages\Page;
use App\Classes\Pages\PageComponent;
use Katu\Errors\Error;
use Katu\Errors\ErrorCollection;
use Katu\Tools\Rest\RestResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class UpdateTree extends \Katu\Controllers\Controller
{
	public function getResponse(ServerRequestInterface $request, ResponseInterface $response, string $pageId)
	{
		if ($request->getMethod() !== "POST") {
			throw new \Katu\Exceptions\NotFoundException;
		}

		if (!RequestToken::validate($request)) {
			throw new \Katu\Exceptions\ForbiddenException;
		}

		$page = Page::get($pageId);
		if (!$page) {
			throw new \Katu\Exceptions\ModelNotFoundException;
		}

		$parsedBody = $request->getParsedBody();
		if (!is_array($parsedBody)) {
			$parsedBody = $_POST;
		}

		$items = $parsedBody["items"] ?? null;
		if (is_string($items)) {
			$decoded = json_decode($items, true);
			$items = is_array($decoded) ? $decoded : null;
		}

		if (!is_array($items) || count($items) === 0) {
			return $this->jsonError($response, "Chybí pořadí součástí.", 400);
		}

		$components = [];
		foreach ($page->getPageComponents() as $component) {
			$components[(int)$component->getId()] = $component;
		}

		if (count($items) !== count($components)) {
			return $this->jsonError($response, "Pořadí neodpovídá součástem stránky.", 400);
		}

		$seen = [];
		$normalized = [];

		foreach ($items as $item) {
			$id = (int)($item["id"] ?? 0);
			$parentId = isset($item["parentId"]) && $item["parentId"] !== "" && $item["parentId"] !== null
				? (int)$item["parentId"]
				: null;

			if ($id <= 0 || !isset($components[$id]) || isset($seen[$id])) {
				return $this->jsonError($response, "Neplatná součást v pořadí.", 400);
			}

			if ($parentId !== null) {
				if (!isset($components[$parentId])) {
					return $this->jsonError($response, "Neplatný nadřazený blok.", 400);
				}

				if (!$components[$parentId]->isLayout()) {
					return $this->jsonError($response, "Vkládat lze jen do sloupců.", 400);
				}

				if ($components[$id]->isLayout()) {
					return $this->jsonError($response, "Sloupce nelze vkládat do sloupců.", 400);
				}

				if ($this->wouldCreateCycle($id, $parentId, $items)) {
					return $this->jsonError($response, "Nelze vytvořit cyklus ve stromu.", 400);
				}
			}

			$seen[$id] = true;
			$normalized[] = [
				"id" => $id,
				"parentId" => $parentId,
			];
		}

		$positionsByParent = [];
		foreach ($normalized as $item) {
			$key = $item["parentId"] === null ? "root" : (string)$item["parentId"];
			$positionsByParent[$key] = ($positionsByParent[$key] ?? 0) + 1;
			$component = $components[$item["id"]];
			$parent = $item["parentId"] !== null ? $components[$item["parentId"]] : null;
			$component->setParent($parent);
			$component->setPosition($positionsByParent[$key]);
			$component->persist();
		}

		return $response
			->withStatus(200)
			->withHeader("Content-Type", "application/json; charset=UTF-8")
			->withBody((new RestResponse([
				"ok" => true,
			]))->getStream())
			;
	}

	private function wouldCreateCycle(int $id, int $parentId, array $items): bool
	{
		$parentById = [];
		foreach ($items as $item) {
			$itemId = (int)($item["id"] ?? 0);
			$itemParent = isset($item["parentId"]) && $item["parentId"] !== "" && $item["parentId"] !== null
				? (int)$item["parentId"]
				: null;
			$parentById[$itemId] = $itemParent;
		}

		$cursor = $parentId;
		$guard = 0;
		while ($cursor !== null && $guard < 100) {
			if ($cursor === $id) {
				return true;
			}
			$cursor = $parentById[$cursor] ?? null;
			$guard++;
		}

		return false;
	}

	private function jsonError(ResponseInterface $response, string $message, int $status): ResponseInterface
	{
		return $response
			->withStatus($status)
			->withHeader("Content-Type", "application/json; charset=UTF-8")
			->withBody((new ErrorCollection([
				new Error($message),
			]))->getRestResponse()->getStream())
			;
	}
}
