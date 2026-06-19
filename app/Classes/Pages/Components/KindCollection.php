<?php

namespace App\Classes\Pages\Components;

use Katu\Tools\Strings\Code;

class KindCollection extends \ArrayObject
{
	public static function createDefault(): KindCollection
	{
		return new static([
			new \App\Classes\Pages\Components\Kinds\FilesKind,
			new \App\Classes\Pages\Components\Kinds\TextKind,
		]);
	}

	public function filterByCode(Code $code): ?KindCollection
	{
		return new static(array_values(array_filter($this->getArrayCopy(), function (Kind $kind) use ($code) {
			return $kind->getCode() == $code;
		})));
	}

	public function getFirst(): ?Kind
	{
		return array_values($this->getArrayCopy())[0] ?? null;
	}
}
