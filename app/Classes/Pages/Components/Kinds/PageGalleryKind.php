<?php

namespace App\Classes\Pages\Components\Kinds;

use App\Classes\Pages\Components\Kind;
use App\Classes\Pages\PageComponent;
use App\Classes\Pages\PageComponentPageCollection;
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

	public static function validate(PageComponent $pageComponent, ServerRequestInterface $request): Validation
	{
		$output = $request->getParsedBody()["values"][$pageComponent->getId()] ?? [];
		$pageOrder = trim((string)($output["pageOrder"] ?? ""));
		$pageIds = array_values(array_filter(array_map("intval", explode(",", $pageOrder))));

		return (new Validation)->setResponse($pageIds);
	}

	public static function setFromValidation(PageComponent $pageComponent, Validation $validation): PageComponent
	{
		PageComponentPageCollection::syncFromPageIds($pageComponent, $validation->getResponse());

		return $pageComponent;
	}
}
