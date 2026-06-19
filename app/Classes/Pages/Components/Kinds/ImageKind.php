<?php

namespace App\Classes\Pages\Components\Kinds;

use App\Classes\Pages\Components\Kind;
use App\Classes\Pages\PageComponent;
use App\Classes\Storage\StorageFileCollection;
use Katu\Files\UploadCollection;
use Katu\Tools\Strings\Code;
use Psr\Http\Message\ServerRequestInterface;
use Katu\Tools\Validation\Validation;

class ImageKind extends Kind
{
	public static function getCode(): Code
	{
		return new Code("IMAGE");
	}

	public static function getTitle(): string
	{
		return "Obrázek";
	}

	public static function validate(PageComponent $pageComponent, ServerRequestInterface $request): Validation
	{
		$output = UploadCollection::createFromInput($request->getUploadedFiles()["values"][$pageComponent->getId()] ?? null)->filterWithoutError();

		return (new Validation)->setResponse($output);
	}

	public static function setFromValidation(PageComponent $pageComponent, Validation $validation): PageComponent
	{
		$upload = $validation->getResponse()->getFirst();
		if (!$upload) {
			return $pageComponent;
		}

		StorageFileCollection::replaceWithUpload($pageComponent, $upload);

		return $pageComponent;
	}
}
