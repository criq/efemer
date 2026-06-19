<?php

namespace App\Classes\Pages\PageGallery\Templates;

use App\Classes\Pages\PageGallery\Template;
use Katu\Tools\Strings\Code;

class ImageAndTextTemplate extends Template
{
	public static function getCode(): Code
	{
		return new Code("IMAGE_AND_TEXT");
	}

	public static function getTitle(): string
	{
		return "Obrázek a text";
	}
}
