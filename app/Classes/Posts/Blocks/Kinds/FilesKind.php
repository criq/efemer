<?php

namespace App\Classes\Posts\Blocks\Kinds;

use App\Classes\Posts\Blocks\Kind;
use App\Classes\Posts\PostBlock;
use App\Classes\Posts\PostBlockFileCollection;
use Katu\Files\UploadCollection;
use Katu\Tools\Strings\Code;
use Psr\Http\Message\ServerRequestInterface;
use Katu\Tools\Validation\Validation;

class FilesKind extends Kind
{
	public static function getCode(): Code
	{
		return new Code("FILES");
	}

	public static function getTitle(): string
	{
		return "Soubory";
	}

	public static function validate(PostBlock $postBlock, ServerRequestInterface $request): Validation
	{
		$output = UploadCollection::createFromInput($request->getUploadedFiles()["values"][$postBlock->getId()]);

		return (new Validation)->setResponse($output);
	}

	public static function setFromValidation(PostBlock $postBlock, Validation $validation): PostBlock
	{
		PostBlockFileCollection::createFromUploads($postBlock, $validation->getResponse());

		return $postBlock;
	}
}
