<?php

namespace App\Classes\Storage;

use App\Classes\Pages\PageComponent;
use Katu\Files\Upload;
use Katu\Files\UploadCollection;

class StorageFileCollection extends \ArrayObject
{
	public static function createFromUploads(PageComponent $pageComponent, UploadCollection $uploads): StorageFileCollection
	{
		$position = 0;

		foreach ($pageComponent->getStorageFiles() as $storageFile) {
			$position = max($position, $storageFile->getPosition() ?? 0);
		}

		$files = [];

		foreach ($uploads->filterWithoutError()->getArrayCopy() as $upload) {
			$position++;
			$files[] = StorageFile::createFromUpload($pageComponent, $upload, $position);
		}

		return new StorageFileCollection($files);
	}

	public static function replaceWithUpload(PageComponent $pageComponent, Upload $upload): StorageFile
	{
		foreach ($pageComponent->getStorageFiles() as $storageFile) {
			$storageFile->delete();
		}

		return StorageFile::createFromUpload($pageComponent, $upload, 1);
	}

	public function getFirst(): ?StorageFile
	{
		return array_values($this->getArrayCopy())[0] ?? null;
	}
}
