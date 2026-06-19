<?php

namespace App\Classes\Pages\PageGallery\Templates;

use App\Classes\Pages\PageGallery\Template;
use Katu\Tools\Strings\Code;

class ImageGalleryTemplate extends Template
{
	public static function getCode(): Code
	{
		return new Code("IMAGE_GALLERY");
	}

	public static function getTitle(): string
	{
		return "Galerie obrázků";
	}
}
