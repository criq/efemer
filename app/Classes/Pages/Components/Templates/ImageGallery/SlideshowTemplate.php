<?php

namespace App\Classes\Pages\Components\Templates\ImageGallery;

use App\Classes\Pages\Components\Templates\Template;
use Katu\Tools\Strings\Code;

class SlideshowTemplate extends Template
{
	public static function getKindCode(): Code
	{
		return new Code("IMAGE_GALLERY");
	}

	public static function getCode(): Code
	{
		return new Code("SLIDESHOW");
	}

	public static function getTitle(): string
	{
		return "Slideshow";
	}
}
