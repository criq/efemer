<?php

namespace App\Controllers\Admin\Posts;

use App\Classes\Posts\PostBlock;
use App\Classes\Views\HTMLEngine;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class EditPostBlock extends \Katu\Controllers\Controller
{
	public function getResponse(ServerRequestInterface $request, ResponseInterface $response, string $postId, string $postBlockId)
	{
		$postBlock = PostBlock::get($postBlockId);
		if (!$postBlock) {
			throw new \Katu\Exceptions\ModelNotFoundException;
		}

		if ($this->isSubmittedWithToken($request)) {
			$validation = $postBlock->getKind()->validate($postBlock, $request);
			if (!$validation->hasErrors()) {
				$postBlock->getKind()->setFromValidation($postBlock, $validation);

				return $response
					->withStatus(302)
					->withHeader("Location", (string)\Katu\Tools\Routing\URL::getFor("admin.posts.view", [
						"postId" => $postBlock->getPost()->getId(),
					]))
					;
			}
		}

		return $response->withBody((new HTMLEngine($request))->render("Admin/Posts/Blocks/edit.twig", [
			"postBlock" => $postBlock,
		]));
	}
}
