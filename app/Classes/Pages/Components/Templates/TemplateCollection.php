<?php

namespace App\Classes\Pages\Components\Templates;

use Katu\Tools\Strings\Code;

class TemplateCollection extends \ArrayObject
{
	public function filterByCode(Code $code): TemplateCollection
	{
		return new static(array_values(array_filter($this->getArrayCopy(), function (Template $template) use ($code) {
			return $template->getCode() == $code;
		})));
	}

	public function getFirst(): ?Template
	{
		return array_values($this->getArrayCopy())[0] ?? null;
	}

	public function getDefaultCode(): string
	{
		$first = $this->getFirst();

		return $first ? $first->getCode()->getConstantFormat() : "DEFAULT";
	}
}
