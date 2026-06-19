<?php

namespace App\Classes\Pages\Components\Kinds;

use App\Classes\Pages\Components\Kind;
use App\Classes\Pages\Components\Templates\TemplateCollection;
use App\Classes\Pages\Components\Templates\Text\DefaultTemplate;
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

	public static function getTemplates(): TemplateCollection
	{
		return new TemplateCollection([
			new DefaultTemplate,
		]);
	}

	public static function validate(PageComponent $pageComponent, ServerRequestInterface $request): Validation
	{
		$output = static::getComponentFormValues($pageComponent, $request);

		return (new Validation)->setResponse([
			"value" => $output["value"] ?? "",
			"template" => $output["template"] ?? null,
		]);
	}

	public static function setFromValidation(PageComponent $pageComponent, Validation $validation): PageComponent
	{
		$response = $validation->getResponse();
		$pageComponent->setValue($response["value"]);
		static::applyTemplateFromResponse($pageComponent, $response);
		$pageComponent->persist();

		return $pageComponent;
	}
}
