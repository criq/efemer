<?php

namespace App\Classes\Posts\Blocks;

use Katu\Tools\Strings\Code;

abstract class Kind
{
	abstract public static function getCode(): Code;
	abstract public static function getTitle(): string;
}
