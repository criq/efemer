<?php

namespace App\Classes\Pages\Components\Kinds;

use App\Classes\Pages\Components\Kind;
use App\Classes\Pages\Components\Templates\Gallery\DefaultTemplate;
use App\Classes\Pages\Components\Templates\TemplateCollection;
use App\Classes\Pages\GalleryProperties;
use App\Classes\Pages\PageComponent;
use App\Classes\Pages\PageComponentGalleryItemCollection;
use Katu\Tools\Strings\Code;
use Psr\Http\Message\ServerRequestInterface;
use Katu\Tools\Validation\Validation;

class GalleryKind extends Kind
{
	public static function getCode(): Code
	{
		return new Code("GALLERY");
	}

	public static function getTitle(): string
	{
		return "Galerie";
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
		$componentOrder = trim((string)($output["componentOrder"] ?? ""));
		$childComponentIds = array_values(array_filter(array_map("intval", explode(",", $componentOrder))));

		return (new Validation)->setResponse([
			"childComponentIds" => $childComponentIds,
			"gallery" => GalleryProperties::fromArray($output["gallery"] ?? []),
			"template" => $output["template"] ?? null,
		]);
	}

	public static function setFromValidation(PageComponent $pageComponent, Validation $validation): PageComponent
	{
		$response = $validation->getResponse();
		$pageComponent->setGalleryProperties($response["gallery"]);
		static::applyTemplateFromResponse($pageComponent, $response);
		$pageComponent->persist();
		PageComponentGalleryItemCollection::syncFromComponentIds($pageComponent, $response["childComponentIds"]);

		return $pageComponent;
	}
}
