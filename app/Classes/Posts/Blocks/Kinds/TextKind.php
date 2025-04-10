<?php

namespace App\Classes\Posts\Blocks\Kinds;

use App\Classes\Posts\Blocks\Kind;
use Katu\Tools\Strings\Code;

class TextKind extends Kind
{
	public static function getCode(): Code
	{
		return new Code("TEXT");
	}

	public static function getTitle(): string
	{
		return "Text";
	}
}
