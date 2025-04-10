<?php

namespace App\Controllers\Posts;

use App\Classes\Posts\Blocks\KindCollection;
use App\Classes\Posts\Post;
use App\Classes\Posts\PostBlock;
use App\Classes\Posts\PostBlockFileCollection;
use App\Classes\Views\HTMLEngine;
use Katu\Files\UploadCollection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class EditPost extends \Katu\Controllers\Controller
{
	public function getResponse(ServerRequestInterface $request, ResponseInterface $response, string $postId)
	{
		$post = Post::get($postId);
		if (!$post) {
			throw new \Katu\Exceptions\ModelNotFoundException;
		}

		$kinds = KindCollection::createDefault();

		if ($this->isSubmittedWithToken($request)) {
			$array = array_values($request->getUploadedFiles()["values"])[0];
			$postBlockFiles = PostBlockFileCollection::createFromUploads(PostBlock::get(6), UploadCollection::createFromInput($array));

			return $response
				->withStatus(302)
				->withHeader("Location", (string)\Katu\Tools\Routing\URL::getFor("posts.edit", [
					"postId" => $post->getId(),
				]))
				;
		}

		return $response->withBody((new HTMLEngine($request))->render("Posts/edit.twig", [
			"kinds" => $kinds,
			"post" => $post,
		]));
	}
}
