<?php

namespace App\Classes\Pages\PageGallery;

use Katu\Tools\Strings\Code;

abstract class Template
{
	abstract public static function getCode(): Code;

	abstract public static function getTitle(): string;

	public static function getViewPath(): string
	{
		return "Pages/Components/Kinds/PAGE_GALLERY/Templates/" . static::getCode()->getConstantFormat() . "/view.twig";
	}
}
