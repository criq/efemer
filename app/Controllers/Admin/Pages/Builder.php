<?php

namespace App\Controllers\Admin\Pages;

use App\Classes\Pages\Components\KindCollection;
use App\Classes\Pages\Page;
use App\Classes\Pages\PageGallery\TemplateCollection;
use App\Classes\Tools\Slugger;
use App\Classes\Views\HTMLEngine;
use Katu\Errors\Error;
use Katu\Errors\ErrorCollection;
use Katu\Tools\Validation\Validation;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Builder extends \Katu\Controllers\Controller
{
	public function getResponse(ServerRequestInterface $request, ResponseInterface $response, string $pageId)
	{
		$page = Page::get($pageId);
		if (!$page) {
			throw new \Katu\Exceptions\ModelNotFoundException;
		}

		$errors = new ErrorCollection;

		if ($this->isSubmittedWithToken($request)) {
			$parsedBody = $request->getParsedBody();
			if (!is_array($parsedBody)) {
				$parsedBody = $_POST;
			}

			$action = (string)($parsedBody["builderAction"] ?? "");
			if ($action === "settings") {
				$validation = $this->validatePageSettings($page, $request);
				$errors = $validation->getErrors();
				$page->setTitle($this->parseTitle($request));
				$page->setPath($validation->getResponse());

				if (!$validation->hasErrors()) {
					$page->persist();

					return $response
						->withStatus(302)
						->withHeader("Location", (string)\Katu\Tools\Routing\URL::getFor("admin.pages.builder", [
							"pageId" => $page->getId(),
						]))
						;
				}
			}
		}

		$candidatePages = [];
		foreach (Page::getAll()->getItems() as $candidatePage) {
			if ((int)$candidatePage->getId() === (int)$page->getId()) {
				continue;
			}
			$candidatePages[] = [
				"pageId" => (int)$candidatePage->getId(),
				"title" => $candidatePage->getTitle() ?: ("Stránka " . $candidatePage->getId()),
				"path" => $candidatePage->getPublicPath(),
			];
		}

		$pageGalleryTemplates = [];
		foreach (TemplateCollection::createDefault() as $template) {
			$pageGalleryTemplates[] = [
				"value" => $template->getCode()->getConstantFormat(),
				"label" => $template->getTitle(),
			];
		}

		return $response->withBody((new HTMLEngine($request))->render("Admin/Pages/builder.twig", [
			"errors" => $errors,
			"kinds" => KindCollection::createDefault(),
			"page" => $page,
			"candidatePages" => $candidatePages,
			"pageGalleryTemplates" => $pageGalleryTemplates,
		]));
	}

	private function parseTitle(ServerRequestInterface $request): ?string
	{
		$parsedBody = $request->getParsedBody();
		if (!is_array($parsedBody)) {
			$parsedBody = $_POST;
		}

		$title = trim((string)($parsedBody["title"] ?? ""));

		return $title !== "" ? $title : null;
	}

	private function validatePageSettings(Page $page, ServerRequestInterface $request): Validation
	{
		$parsedBody = $request->getParsedBody();
		if (!is_array($parsedBody)) {
			$parsedBody = $_POST;
		}

		$rawPath = (string)($parsedBody["path"] ?? "");
		$path = Slugger::slugifyPagePath($rawPath);
		$storedPath = Slugger::isHomepagePath($path) ? Slugger::HOMEPAGE_PATH : ($path === "" ? null : $path);
		$validation = (new Validation)->setResponse($storedPath);

		$title = $this->parseTitle($request);
		if ($title !== null && mb_strlen($title) > 200) {
			$validation->addError(new Error("Název může mít nejvýše 200 znaků."));
		}

		if (!Slugger::isHomepageInput($rawPath) && $path === "") {
			$validation->addError(new Error("Cesta musí obsahovat alespoň jedno písmeno nebo číslici."));
		}

		if (Slugger::isHomepagePath($path)) {
			$existingHomepage = Page::getHomepage();
			if ($existingHomepage && (int)$existingHomepage->getId() !== (int)$page->getId()) {
				$validation->addError(new Error("Domovská stránka je již nastavena."));
			}
		} elseif ($path !== "") {
			$existing = Page::getOneBy(["path" => $path]);
			if ($existing && (int)$existing->getId() !== (int)$page->getId()) {
				$validation->addError(new Error("Cesta je již použita."));
			}
		}

		return $validation;
	}
}
