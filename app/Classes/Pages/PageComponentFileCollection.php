<?php

namespace App\Classes\Pages;

use Katu\Files\Upload;
use Katu\Files\UploadCollection;

class PageComponentFileCollection extends \ArrayObject
{
	public static function createFromUploads(PageComponent $pageComponent, UploadCollection $uploads): PageComponentFileCollection
	{
		return new PageComponentFileCollection(array_map(function (Upload $upload) use ($pageComponent) {
			return PageComponentFile::createFromUpload($pageComponent, $upload);
		}, $uploads->filterWithoutError()->getArrayCopy()));
	}

	public function getSlice(int $length): PageComponentFileCollection
	{
		return new PageComponentFileCollection(array_slice($this->getArrayCopy(), 0, $length));
	}

	public function getFirst(): ?PageComponentFile
	{
		return array_values($this->getArrayCopy())[0] ?? null;
	}
}
