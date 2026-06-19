<?php

namespace App\Classes\Pages\PageGallery\Templates;

use App\Classes\Pages\PageGallery\Template;
use Katu\Tools\Strings\Code;

class TextOnlyTemplate extends Template
{
	public static function getCode(): Code
	{
		return new Code("TEXT_ONLY");
	}

	public static function getTitle(): string
	{
		return "Pouze text";
	}
}
