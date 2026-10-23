<?php

namespace App\Controllers\Admin\Pages\Builder;

use App\Classes\Forms\RequestToken;
use App\Classes\Pages\Page;
use App\Classes\Pages\PageComponent;
use App\Classes\Pages\PageComponentPageCollection;
use App\Classes\Pages\PageGallery\TemplateCollection;
use Katu\Errors\Error;
use Katu\Errors\ErrorCollection;
use Katu\Tools\Rest\RestResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class UpdatePageLinks extends \Katu\Controllers\Controller
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

		$kindCode = $pageComponent->getKind()?->getCode()->getConstantFormat();
		if ($kindCode !== "PAGE_GALLERY") {
			return $this->jsonError($response, "Stránky lze vazbit jen u galerie stránek.", 400);
		}

		$parsedBody = $request->getParsedBody();
		if (!is_array($parsedBody)) {
			$parsedBody = $_POST;
		}

		$entries = $parsedBody["entries"] ?? null;
		if (is_string($entries)) {
			$decoded = json_decode($entries, true);
			$entries = is_array($decoded) ? $decoded : null;
		}

		if (!is_array($entries)) {
			return $this->jsonError($response, "Chybí seznam stránek.", 400);
		}

		$defaultTemplate = TemplateCollection::getDefaultCode();
		$normalized = [];
		$seen = [];

		foreach ($entries as $entry) {
			$linkedPageId = (int)($entry["pageId"] ?? 0);
			if ($linkedPageId <= 0 || isset($seen[$linkedPageId]) || $linkedPageId === (int)$pageId) {
				continue;
			}

			$linkedPage = Page::get($linkedPageId);
			if (!$linkedPage) {
				continue;
			}

			$seen[$linkedPageId] = true;
			$template = trim((string)($entry["template"] ?? $defaultTemplate));
			if ($template === "") {
				$template = $defaultTemplate;
			}

			$normalized[] = [
				"pageId" => $linkedPageId,
				"template" => $template,
			];
		}

		PageComponentPageCollection::syncFromPageEntries($pageComponent, $normalized);

		$payload = [];
		foreach ($pageComponent->getPageComponentPages() as $link) {
			$linked = $link->getPage();
			$payload[] = [
				"pageId" => (int)$linked->getId(),
				"title" => $linked->getTitle(),
				"path" => $linked->getPublicPath(),
				"template" => $link->getTemplate()->getCode()->getConstantFormat(),
			];
		}

		return $response
			->withStatus(200)
			->withHeader("Content-Type", "application/json; charset=UTF-8")
			->withBody((new RestResponse([
				"entries" => $payload,
			]))->getStream())
			;
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
