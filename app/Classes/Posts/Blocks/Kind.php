<?php

namespace App\Classes\Posts\Blocks;

use App\Classes\Posts\PostBlock;
use Katu\Tools\Strings\Code;
use Katu\Tools\Validation\Validation;
use Psr\Http\Message\ServerRequestInterface;

abstract class Kind
{
	abstract public static function getCode(): Code;
	abstract public static function getTitle(): string;
	abstract public static function setFromValidation(PostBlock $postBlock, Validation $validation): PostBlock;
	abstract public static function validate(PostBlock $postBlock, ServerRequestInterface $request): Validation;
}
