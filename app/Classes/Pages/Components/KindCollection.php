<?php

namespace App\Classes\Pages\Components;

use Katu\Tools\Strings\Code;

class KindCollection extends \ArrayObject
{
	public static function createDefault(): KindCollection
	{
		return new static([
			new \App\Classes\Pages\Components\Kinds\TextKind,
			new \App\Classes\Pages\Components\Kinds\ImageKind,
			new \App\Classes\Pages\Components\Kinds\ImageGalleryKind,
			new \App\Classes\Pages\Components\Kinds\PageGalleryKind,
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
