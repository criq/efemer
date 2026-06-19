<?php

namespace App\Classes\Pages\Components;

use App\Classes\Pages\PageComponent;
use Katu\Tools\Strings\Code;
use Katu\Tools\Validation\Validation;
use Psr\Http\Message\ServerRequestInterface;

abstract class Kind
{
	abstract public static function getCode(): Code;
	abstract public static function getTitle(): string;
	abstract public static function setFromValidation(PageComponent $pageComponent, Validation $validation): PageComponent;
	abstract public static function validate(PageComponent $pageComponent, ServerRequestInterface $request): Validation;
}
