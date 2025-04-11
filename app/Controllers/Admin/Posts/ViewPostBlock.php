<?php

namespace App\Controllers\Admin\Posts;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ViewPostBlock extends \Katu\Controllers\Controller
{
	public function getResponse(ServerRequestInterface $request, ResponseInterface $response, string $postId, string $kindCode)
	{
	}
}
