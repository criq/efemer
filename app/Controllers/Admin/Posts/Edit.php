<?php

namespace App\Controllers\Admin\Posts;

use App\Classes\Content\Post;
use App\Classes\Content\Section;
use App\Classes\Html\Sanitizer;
use App\Classes\Views\HTMLEngine;
use Katu\Errors\Error;
use Katu\Errors\ErrorCollection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Edit extends \Katu\Controllers\Controller
{
	public function getResponse(ServerRequestInterface $request, ResponseInterface $response, string $postId)
	{
		$post = Post::get($postId);
		if (!$post) {
			throw new \Katu\Exceptions\ModelNotFoundException;
		}

		$errors = new ErrorCollection;

		if ($this->isSubmittedWithToken($request)) {
			$body = $request->getParsedBody() ?? [];
			$title = trim((string)($body["title"] ?? ""));
			$section = (string)($body["section"] ?? "");
			$status = (string)($body["status"] ?? Post::STATUS_REVIEW);
			$excerpt = trim((string)($body["excerpt"] ?? ""));
			$html = Sanitizer::clean((string)($body["bodyHtml"] ?? ""));

			if ($title === "") {
				$errors->addError(new Error("Zadejte název."));
			}
			if (!Section::isWork($section)) {
				$errors->addError(new Error("Vyberte sekci."));
			}
			if (!in_array($status, [Post::STATUS_REVIEW, Post::STATUS_PUBLISHED, Post::STATUS_HIDDEN], true)) {
				$errors->addError(new Error("Neplatný stav."));
			}

			$post->setTitle($title !== "" ? $title : null);
			$post->setSection(Section::isWork($section) ? $section : $post->getSection());
			$post->setStatus($status);
			$post->setExcerpt($excerpt !== "" ? $excerpt : null);
			$post->setBodyHtml($html);

			if (!$errors->hasErrors()) {
				$post->persist();

				return $response
					->withStatus(302)
					->withHeader("Location", (string)\Katu\Tools\Routing\URL::getFor("admin.posts.index"))
					;
			}
		}

		return $response->withBody((new HTMLEngine($request))->render("Admin/Posts/edit.twig", [
			"errors" => $errors,
			"post" => $post,
			"sections" => Section::work(),
		]));
	}
}
