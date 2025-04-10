<?php

namespace App\Controllers\Posts;

use App\Classes\Posts\Blocks\KindCollection;
use App\Classes\Posts\Post;
use App\Classes\Posts\PostBlock;
use Katu\Tools\Calendar\Time;
use Katu\Tools\Strings\Code;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class CreatePostBlock extends \Katu\Controllers\Controller
{
	public function getResponse(ServerRequestInterface $request, ResponseInterface $response, string $postId, string $kindCode)
	{
		$post = Post::get($postId);
		$kind = KindCollection::createDefault()->filterByCode(new Code($kindCode))->getFirst();

		$postBlock = new PostBlock;
		$postBlock->setTimeCreated(new Time);
		$postBlock->setPost($post);
		$postBlock->setKind($kind);
		$postBlock->setPosition($post->getPostBlocks()->getMaxPosition() + 1);
		$postBlock->persist();

		return $response
			->withStatus(302)
			->withHeader("Location", (string)\Katu\Tools\Routing\URL::getFor("posts.edit", [
				"postId" => $post->getId(),
			]))
			;
	}
}
