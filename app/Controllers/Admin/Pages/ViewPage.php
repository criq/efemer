<?php

namespace App\Controllers\Admin\Pages;

use App\Classes\Pages\Components\KindCollection;
use App\Classes\Pages\Page;
use App\Classes\Tools\Slugger;
use App\Classes\Views\HTMLEngine;
use Katu\Errors\Error;
use Katu\Errors\ErrorCollection;
use Katu\Tools\Validation\Validation;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ViewPage extends \Katu\Controllers\Controller
{
	public function getResponse(ServerRequestInterface $request, ResponseInterface $response, string $pageId)
	{
		$page = Page::get($pageId);
		if (!$page) {
			throw new \Katu\Exceptions\ModelNotFoundException;
		}

		$errors = new ErrorCollection;

		if ($this->isSubmittedWithToken($request)) {
			$validation = $this->validatePage($page, $request);
			$errors = $validation->getErrors();

			$page->setTitle($this->parseTitle($request));
			$page->setPath($validation->getResponse());

			if (!$validation->hasErrors()) {
				$page->persist();
				$this->reorderPageComponents($page, $request);

				return $response
					->withStatus(302)
					->withHeader("Location", (string)\Katu\Tools\Routing\URL::getFor("admin.pages.index"))
					;
			}
		}

		$kinds = KindCollection::createDefault();

		return $response->withBody((new HTMLEngine($request))->render("Admin/Pages/view.twig", [
			"errors" => $errors,
			"kinds" => $kinds,
			"page" => $page,
		]));
	}

	private function parseTitle(ServerRequestInterface $request): ?string
	{
		$title = trim((string)($request->getParsedBody()["title"] ?? ""));

		return $title !== "" ? $title : null;
	}

	private function validatePage(Page $page, ServerRequestInterface $request): Validation
	{
		$rawPath = $request->getParsedBody()["path"] ?? "";
		$path = Slugger::slugifyPagePath($rawPath);

		$validation = (new Validation)->setResponse($path === "" ? null : $path);

		$title = $this->parseTitle($request);
		if ($title !== null && mb_strlen($title) > 200) {
			$validation->addError(new Error("Název může mít nejvýše 200 znaků."));
		}

		if (!Slugger::isHomepageInput($rawPath) && $path === "") {
			$validation->addError(new Error("Cesta musí obsahovat alespoň jedno písmeno nebo číslici."));
		}

		if ($path !== "") {
			$existing = Page::getOneBy(["path" => $path]);
			if ($existing && (int)$existing->getId() !== (int)$page->getId()) {
				$validation->addError(new Error(Slugger::isHomepagePath($path) ? "Domovská stránka je již nastavena." : "Cesta je již použita."));
			}
		}

		return $validation;
	}

	private function reorderPageComponents(Page $page, ServerRequestInterface $request): void
	{
		$order = trim((string)($request->getParsedBody()["componentOrder"] ?? ""));
		if ($order === "") {
			return;
		}

		$orderedIds = array_values(array_filter(array_map("intval", explode(",", $order))));
		$page->reorderPageComponents($orderedIds);
	}
}
