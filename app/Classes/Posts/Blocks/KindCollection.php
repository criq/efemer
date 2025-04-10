<?php

namespace App\Classes\Posts\Blocks;

use Katu\Tools\Strings\Code;

class KindCollection extends \ArrayObject
{
	public static function createDefault(): KindCollection
	{
		return new static([
			new \App\Classes\Posts\Blocks\Kinds\FilesKind,
			new \App\Classes\Posts\Blocks\Kinds\TextKind,
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
