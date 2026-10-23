<?php

namespace App\Controllers\Admin\Pages;

use App\Classes\Forms\RequestToken;
use App\Classes\Pages\Components\Kinds\ImageKind;
use App\Classes\Pages\Page;
use App\Classes\Pages\PageComponent;
use App\Classes\Pages\PageComponentStorageFile;
use App\Classes\Views\HTMLEngine;
use Katu\Errors\Error;
use Katu\Errors\ErrorCollection;
use Katu\Tools\Calendar\Time;
use Katu\Tools\Rest\RestResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class MoveStorageFile extends \Katu\Controllers\Controller
{
	public function getResponse(ServerRequestInterface $request, ResponseInterface $response, string $pageId, string $storageFileId)
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

		$link = PageComponentStorageFile::get($storageFileId);
		if (!$link) {
			throw new \Katu\Exceptions\ModelNotFoundException;
		}

		$source = $link->getPageComponent();
		if ((int)$source->getPage()->getId() !== (int)$page->getId()) {
			throw new \Katu\Exceptions\ForbiddenException;
		}

		$parsedBody = $request->getParsedBody();
		if (!is_array($parsedBody)) {
			$parsedBody = $_POST;
		}

		$createImage = !empty($parsedBody["createImage"]);
		$created = false;
		$target = null;

		if ($createImage) {
			$target = $this->createImageComponent($page, $parsedBody);
			$created = true;
		} else {
			$targetId = (int)($parsedBody["targetPageComponentId"] ?? 0);
			$target = PageComponent::get($targetId);
			if (!$target || (int)$target->getPage()->getId() !== (int)$page->getId()) {
				return $this->jsonError($response, "Cílová součást neexistuje.", 404);
			}

			$kindCode = $target->getKind()?->getCode()->getConstantFormat();
			if (!in_array($kindCode, ["IMAGE", "IMAGE_GALLERY"], true)) {
				return $this->jsonError($response, "Fotku lze přesunout jen do fotky nebo galerie.", 400);
			}
		}

		$position = (int)($parsedBody["position"] ?? 0);
		$this->moveLink($link, $source, $target, $position);

		$htmlEngine = new HTMLEngine($request);
		$payload = [
			"sourcePageComponentId" => (int)$source->getId(),
			"targetPageComponentId" => (int)$target->getId(),
			"created" => $created,
			"sourceHtml" => (string)$htmlEngine->render("Admin/Pages/Builder/block.twig", [
				"pageComponent" => PageComponent::get($source->getId()),
				"page" => $page,
			]),
			"targetHtml" => (string)$htmlEngine->render("Admin/Pages/Builder/block.twig", [
				"pageComponent" => PageComponent::get($target->getId()),
				"page" => $page,
			]),
		];

		return $response
			->withStatus(200)
			->withHeader("Content-Type", "application/json; charset=UTF-8")
			->withBody((new RestResponse($payload))->getStream())
			;
	}

	private function createImageComponent(Page $page, array $parsedBody): PageComponent
	{
		$afterId = (int)($parsedBody["afterId"] ?? 0);
		$siblings = $page->getRootPageComponents()->getArrayCopy();

		$insertAt = 0;
		if ($afterId > 0) {
			$insertAt = count($siblings);
			foreach ($siblings as $index => $sibling) {
				if ((int)$sibling->getId() === $afterId) {
					$insertAt = $index + 1;
					break;
				}
			}
		}

		$component = new PageComponent;
		$component->setTimeCreated(new Time);
		$component->setPage($page);
		$component->setParent(null);
		$component->setKind(new ImageKind);
		$component->setPosition($insertAt + 1);
		$component->setTemplateCode(ImageKind::getTemplates()->getDefaultCode());
		$component->persist();

		array_splice($siblings, $insertAt, 0, [$component]);
		foreach ($siblings as $index => $sibling) {
			$sibling->setPosition($index + 1)->persist();
		}

		return $component;
	}

	private function moveLink(
		PageComponentStorageFile $link,
		PageComponent $source,
		PageComponent $target,
		int $position,
	): void {
		$kindCode = $target->getKind()?->getCode()->getConstantFormat();

		if ($kindCode === "IMAGE") {
			foreach ($target->getStorageFiles() as $existing) {
				if ((int)$existing->getId() !== (int)$link->getId()) {
					$existing->delete();
				}
			}
			$link->setPageComponent($target)->setPosition(1)->persist();
		} else {
			$files = [];
			foreach ($target->getStorageFiles() as $existing) {
				if ((int)$existing->getId() !== (int)$link->getId()) {
					$files[] = $existing;
				}
			}

			$insertAt = $position > 0
				? max(0, min(count($files), $position - 1))
				: count($files);

			$link->setPageComponent($target)->persist();
			array_splice($files, $insertAt, 0, [$link]);

			foreach ($files as $index => $file) {
				$file->setPosition($index + 1)->persist();
			}
		}

		if ((int)$source->getId() !== (int)$target->getId()) {
			$index = 1;
			foreach ($source->getStorageFiles() as $remaining) {
				$remaining->setPosition($index)->persist();
				$index++;
			}
		}
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
