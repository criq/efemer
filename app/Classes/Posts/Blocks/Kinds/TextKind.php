<?php

namespace App\Classes\Posts\Blocks\Kinds;

use App\Classes\Posts\Blocks\Kind;
use App\Classes\Posts\PostBlock;
use Katu\Tools\Strings\Code;
use Psr\Http\Message\ServerRequestInterface;
use Katu\Tools\Validation\Validation;

class TextKind extends Kind
{
	public static function getCode(): Code
	{
		return new Code("TEXT");
	}

	public static function getTitle(): string
	{
		return "Text";
	}

	public static function validate(PostBlock $postBlock, ServerRequestInterface $request): Validation
	{
	}

	public static function setFromValidation(PostBlock $postBlock, Validation $validation): PostBlock
	{
	}
}
