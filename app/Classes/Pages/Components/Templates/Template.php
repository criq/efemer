<?php

namespace App\Classes\Pages\Components\Templates;

use Katu\Tools\Strings\Code;

abstract class Template
{
	abstract public static function getKindCode(): Code;

	abstract public static function getCode(): Code;

	abstract public static function getTitle(): string;

	public static function getViewPath(): string
	{
		return sprintf(
			"Pages/Components/Kinds/%s/Templates/%s/view.twig",
			static::getKindCode()->getConstantFormat(),
			static::getCode()->getConstantFormat(),
		);
	}
}
