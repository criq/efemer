<?php

namespace App\Controllers\Admin\Pages;

use App\Classes\Pages\PostScaffolder;
use App\Classes\Views\HTMLEngine;
use Katu\Errors\Error;
use Katu\Errors\ErrorCollection;
use Katu\Files\UploadCollection;
use Katu\Tools\Calendar\Time;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class CreatePost extends \Katu\Controllers\Controller
{
	public function getResponse(ServerRequestInterface $request, ResponseInterface $response)
	{
		$errors = new ErrorCollection;
		$title = "";
		$dateValue = (new Time)->format("Y-m-d");

		if ($this->isSubmittedWithToken($request)) {
			$parsedBody = $request->getParsedBody();
			if (!is_array($parsedBody)) {
				$parsedBody = $_POST;
			}

			$title = trim((string)($parsedBody["title"] ?? ""));
			$dateRaw = trim((string)($parsedBody["date"] ?? ""));
			$dateValue = $dateRaw !== "" ? $dateRaw : $dateValue;

			try {
				$date = new Time($dateValue . " 12:00:00");
			} catch (\Throwable $e) {
				$date = null;
				$errors[] = new Error("Zadejte platné datum.");
			}

			if ($title === "") {
				$errors[] = new Error("Zadejte název příspěvku.");
			}

			$uploads = UploadCollection::createFromInput($request->getUploadedFiles()["hero"] ?? [])->filterWithoutError();
			$hero = $uploads->getFirst();

			if (!$errors->hasErrors() && $date) {
				try {
					$page = (new PostScaffolder)->create($title, $date, $hero);

					return $response
						->withStatus(302)
						->withHeader("Location", (string)\Katu\Tools\Routing\URL::getFor("admin.pages.builder", [
							"pageId" => $page->getId(),
						]))
						;
				} catch (\Throwable $e) {
					$errors[] = new Error($e->getMessage() ?: "Příspěvek se nepodařilo vytvořit.");
				}
			}
		}

		return $response->withBody((new HTMLEngine($request))->render("Admin/Pages/createPost.twig", [
			"errors" => $errors,
			"title" => $title,
			"date" => $dateValue,
		]));
	}
}
