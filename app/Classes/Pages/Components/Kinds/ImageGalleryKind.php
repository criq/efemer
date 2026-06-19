<?php

namespace App\Classes\Pages\Components\Kinds;

use App\Classes\Pages\Components\Kind;
use App\Classes\Pages\Components\Templates\ImageGallery\NaturalTemplate;
use App\Classes\Pages\Components\Templates\ImageGallery\SlideshowTemplate;
use App\Classes\Pages\Components\Templates\ImageGallery\SquareTemplate;
use App\Classes\Pages\Components\Templates\TemplateCollection;
use App\Classes\Pages\GalleryProperties;
use App\Classes\Pages\PageComponent;
use App\Classes\Pages\PageComponentStorageFileCollection;
use Katu\Files\UploadCollection;
use Katu\Tools\Strings\Code;
use Psr\Http\Message\ServerRequestInterface;
use Katu\Tools\Validation\Validation;

class ImageGalleryKind extends Kind
{
	public static function getCode(): Code
	{
		return new Code("IMAGE_GALLERY");
	}

	public static function getTitle(): string
	{
		return "Galerie obrázků";
	}

	public static function getTemplates(): TemplateCollection
	{
		return new TemplateCollection([
			new NaturalTemplate,
			new SquareTemplate,
			new SlideshowTemplate,
		]);
	}

	public static function validate(PageComponent $pageComponent, ServerRequestInterface $request): Validation
	{
		$output = $request->getParsedBody()["values"][$pageComponent->getId()] ?? [];
		$uploads = UploadCollection::createFromInput($request->getUploadedFiles()["values"][$pageComponent->getId()] ?? [])->filterWithoutError();

		return (new Validation)->setResponse([
			"gallery" => GalleryProperties::fromArray($output["gallery"] ?? []),
			"template" => $output["template"] ?? null,
			"uploads" => $uploads,
		]);
	}

	public static function setFromValidation(PageComponent $pageComponent, Validation $validation): PageComponent
	{
		$response = $validation->getResponse();
		$pageComponent->setGalleryProperties($response["gallery"]);
		static::applyTemplateFromResponse($pageComponent, $response);
		$pageComponent->persist();

		if (count($response["uploads"])) {
			PageComponentStorageFileCollection::createFromUploads($pageComponent, $response["uploads"]);
		}

		return $pageComponent;
	}
}
