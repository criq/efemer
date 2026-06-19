<?php

namespace App\Classes\Pages\Components\Templates\ImageGallery;

use App\Classes\Pages\Components\Templates\Template;
use Katu\Tools\Strings\Code;

class NaturalTemplate extends Template
{
	public static function getKindCode(): Code
	{
		return new Code("IMAGE_GALLERY");
	}

	public static function getCode(): Code
	{
		return new Code("NATURAL");
	}

	public static function getTitle(): string
	{
		return "Původní poměr stran";
	}
}
