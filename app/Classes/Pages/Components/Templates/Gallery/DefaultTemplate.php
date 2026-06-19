<?php

namespace App\Classes\Pages\Components\Templates\Gallery;

use App\Classes\Pages\Components\Templates\Template;
use Katu\Tools\Strings\Code;

class DefaultTemplate extends Template
{
	public static function getKindCode(): Code
	{
		return new Code("GALLERY");
	}

	public static function getCode(): Code
	{
		return new Code("DEFAULT");
	}

	public static function getTitle(): string
	{
		return "Galerie součástí";
	}
}
