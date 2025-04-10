<?php

namespace App\Controllers\Posts;

use App\Classes\Posts\Blocks\KindCollection;
use App\Classes\Posts\Post;
use App\Classes\Posts\PostBlock;
use App\Classes\Posts\PostBlockFile;
use App\Classes\Posts\PostBlockFileCollection;
use App\Classes\Views\HTMLEngine;
use Katu\Files\Upload;
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
			$postBlockFiles = new PostBlockFileCollection(array_map(function (Upload $upload) {
				return PostBlockFile::createFromUpload(PostBlock::get(4), $upload);
			}, UploadCollection::createFromInput($request->getUploadedFiles()["values"][4])->getArrayCopy()));

			var_dump(($postBlockFiles));
			die;
		}

		return $response->withBody((new HTMLEngine($request))->render("Posts/edit.twig", [
			"kinds" => $kinds,
			"post" => $post,
		]));
	}
}
