<?php

namespace App\Classes\Pages\Components\Kinds;

use App\Classes\Pages\Components\Kind;
use App\Classes\Pages\Components\Templates\PageGallery\DefaultTemplate;
use App\Classes\Pages\Components\Templates\TemplateCollection;
use App\Classes\Pages\GalleryProperties;
use App\Classes\Pages\PageComponent;
use App\Classes\Pages\PageComponentPageCollection;
use App\Classes\Pages\PageGallery\TemplateCollection as PageGalleryTemplateCollection;
use Katu\Tools\Strings\Code;
use Psr\Http\Message\ServerRequestInterface;
use Katu\Tools\Validation\Validation;

class PageGalleryKind extends Kind
{
	public static function getCode(): Code
	{
		return new Code("PAGE_GALLERY");
	}

	public static function getTitle(): string
	{
		return "Galerie stránek";
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
		$pageOrder = trim((string)($output["pageOrder"] ?? ""));
		$pageIds = array_values(array_filter(array_map("intval", explode(",", $pageOrder))));

		$pageTemplates = $output["pageTemplates"] ?? [];
		if (is_string($pageTemplates)) {
			$pageTemplates = json_decode($pageTemplates, true) ?: [];
		}

		$entries = [];

		foreach ($pageIds as $pageId) {
			$template = (string)($pageTemplates[(string)$pageId] ?? $pageTemplates[$pageId] ?? PageGalleryTemplateCollection::getDefaultCode());
			$entries[] = [
				"pageId" => $pageId,
				"template" => $template,
			];
		}

		return (new Validation)->setResponse([
			"entries" => $entries,
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
		PageComponentPageCollection::syncFromPageEntries($pageComponent, $response["entries"]);

		return $pageComponent;
	}
}
