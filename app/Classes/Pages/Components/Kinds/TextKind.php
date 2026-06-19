<?php

namespace App\Classes\Pages\Components\Kinds;

use App\Classes\Pages\Components\Kind;
use App\Classes\Pages\PageComponent;
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

	public static function validate(PageComponent $pageComponent, ServerRequestInterface $request): Validation
	{
		$output = $request->getParsedBody()["values"][$pageComponent->getId()];

		return (new Validation)->setResponse($output);
	}

	public static function setFromValidation(PageComponent $pageComponent, Validation $validation): PageComponent
	{
		$pageComponent->setValue($validation->getResponse());
		$pageComponent->persist();

		return $pageComponent;
	}
}
