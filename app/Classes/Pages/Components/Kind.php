<?php

namespace App\Classes\Pages\Components;

use App\Classes\Pages\Components\Templates\TemplateCollection;
use App\Classes\Pages\PageComponent;
use Katu\Tools\Strings\Code;
use Katu\Tools\Validation\Validation;
use Psr\Http\Message\ServerRequestInterface;

abstract class Kind
{
	abstract public static function getCode(): Code;
	abstract public static function getTitle(): string;
	abstract public static function getTemplates(): TemplateCollection;
	abstract public static function setFromValidation(PageComponent $pageComponent, Validation $validation): PageComponent;
	abstract public static function validate(PageComponent $pageComponent, ServerRequestInterface $request): Validation;

	public static function resolveTemplateCode(?string $code): string
	{
		$collection = static::getTemplates();

		if ($code) {
			$template = $collection->filterByCode(new Code($code))->getFirst();
			if ($template) {
				return $template->getCode()->getConstantFormat();
			}
		}

		return $collection->getDefaultCode();
	}

	protected static function applyTemplateFromResponse(PageComponent $pageComponent, array $response): void
	{
		if (array_key_exists("template", $response)) {
			$pageComponent->setTemplateCode($response["template"]);
		}
	}
}
