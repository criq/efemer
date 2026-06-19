<?php

namespace App\Classes\Pages\PageGallery;

use App\Classes\Pages\PageGallery\Templates\ImageAndTextTemplate;
use App\Classes\Pages\PageGallery\Templates\ImageGalleryTemplate;
use App\Classes\Pages\PageGallery\Templates\ImageOnlyTemplate;
use App\Classes\Pages\PageGallery\Templates\TextOnlyTemplate;
use Katu\Tools\Strings\Code;

class TemplateCollection extends \ArrayObject
{
	public static function createDefault(): TemplateCollection
	{
		return new static([
			new ImageOnlyTemplate,
			new ImageGalleryTemplate,
			new TextOnlyTemplate,
			new ImageAndTextTemplate,
		]);
	}

	public static function getDefaultCode(): string
	{
		return ImageAndTextTemplate::getCode()->getConstantFormat();
	}

	public function filterByCode(Code $code): TemplateCollection
	{
		return new static(array_values(array_filter($this->getArrayCopy(), function (Template $template) use ($code) {
			return $template->getCode() == $code;
		})));
	}

	public function getFirst(): ?Template
	{
		return array_values($this->getArrayCopy())[0] ?? null;
	}
}
