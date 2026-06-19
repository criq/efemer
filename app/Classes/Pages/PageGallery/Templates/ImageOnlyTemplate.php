<?php

namespace App\Classes\Pages\PageGallery\Templates;

use App\Classes\Pages\PageGallery\Template;
use Katu\Tools\Strings\Code;

class ImageOnlyTemplate extends Template
{
	public static function getCode(): Code
	{
		return new Code("IMAGE_ONLY");
	}

	public static function getTitle(): string
	{
		return "Pouze obrázek";
	}
}
