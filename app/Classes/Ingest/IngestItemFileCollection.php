<?php

namespace App\Classes\Ingest;

class IngestItemFileCollection extends \ArrayObject
{
	public function getFirst(): ?IngestItemFile
	{
		return array_values($this->getArrayCopy())[0] ?? null;
	}
}
