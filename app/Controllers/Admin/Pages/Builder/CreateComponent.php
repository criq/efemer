<?php

namespace App\Controllers\Admin\Pages\Builder;

use App\Classes\Forms\RequestToken;
use App\Classes\Pages\Components\KindCollection;
use App\Classes\Pages\GalleryProperties;
use App\Classes\Pages\Page;
use App\Classes\Pages\PageComponent;
use App\Classes\Views\HTMLEngine;
use Katu\Errors\Error;
use Katu\Errors\ErrorCollection;
use Katu\Tools\Calendar\Time;
use Katu\Tools\Rest\RestResponse;
use Katu\Tools\Strings\Code;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class CreateComponent extends \Katu\Controllers\Controller
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

		$kindCode = trim((string)($parsedBody["kind"] ?? ""));
		$kind = KindCollection::createDefault()->filterByCode(new Code($kindCode))->getFirst();
		if (!$kind) {
			return $this->jsonError($response, "Neznámý typ součásti.", 400);
		}

		$parent = $this->resolveParent($page, (int)($parsedBody["parentId"] ?? 0));
		if ($parent && $kindCode === "COLUMNS") {
			return $this->jsonError($response, "Sloupce nelze vkládat do sloupců.", 400);
		}

		$afterId = (int)($parsedBody["afterId"] ?? 0);
		$siblings = $parent
			? $parent->getChildComponents()->getArrayCopy()
			: $page->getRootPageComponents()->getArrayCopy();

		$insertAt = count($siblings);
		if ($afterId > 0) {
			foreach ($siblings as $index => $sibling) {
				if ((int)$sibling->getId() === $afterId) {
					$insertAt = $index + 1;
					break;
				}
			}
		}

		$pageComponent = new PageComponent;
		$pageComponent->setTimeCreated(new Time);
		$pageComponent->setPage($page);
		$pageComponent->setParent($parent);
		$pageComponent->setKind($kind);
		$pageComponent->setPosition($insertAt + 1);
		$pageComponent->setTemplateCode($kind::getTemplates()->getDefaultCode());

		if ($pageComponent->isGallery()) {
			$pageComponent->setGalleryProperties(GalleryProperties::createDefault());
		}

		$pageComponent->persist();

		array_splice($siblings, $insertAt, 0, [$pageComponent]);
		foreach ($siblings as $index => $sibling) {
			$sibling->setPosition($index + 1)->persist();
		}

		$html = (new HTMLEngine($request))->render("Admin/Pages/Builder/block.twig", [
			"pageComponent" => $pageComponent,
			"page" => $page,
		]);

		return $response
			->withStatus(200)
			->withHeader("Content-Type", "application/json; charset=UTF-8")
			->withBody((new RestResponse([
				"pageComponentId" => (int)$pageComponent->getId(),
				"kind" => $kindCode,
				"parentId" => $parent ? (int)$parent->getId() : null,
				"html" => (string)$html,
			]))->getStream())
			;
	}

	private function resolveParent(Page $page, int $parentId): ?PageComponent
	{
		if ($parentId <= 0) {
			return null;
		}

		$parent = PageComponent::get($parentId);
		if (!$parent || (int)$parent->getPage()->getId() !== (int)$page->getId()) {
			throw new \Katu\Exceptions\ModelNotFoundException;
		}

		if (!$parent->isLayout()) {
			throw new \Katu\Exceptions\ForbiddenException;
		}

		return $parent;
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
