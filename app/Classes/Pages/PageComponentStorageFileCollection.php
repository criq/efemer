<?php

namespace App\Classes\Pages;

use App\Classes\Storage\StorageFile;
use Katu\Files\Upload;
use Katu\Files\UploadCollection;

class PageComponentStorageFileCollection extends \ArrayObject
{
	public static function createFromUpload(PageComponent $pageComponent, Upload $upload, int $position): PageComponentStorageFile
	{
		$storageFile = StorageFile::createFromUpload($upload);

		$link = new PageComponentStorageFile;
		$link->setPageComponent($pageComponent);
		$link->setStorageFile($storageFile);
		$link->setPosition($position);
		$link->persist();

		return $link;
	}

	public static function createFromUploads(PageComponent $pageComponent, UploadCollection $uploads): PageComponentStorageFileCollection
	{
		$position = 0;

		foreach ($pageComponent->getStorageFiles() as $link) {
			$position = max($position, $link->getPosition() ?? 0);
		}

		$links = [];

		foreach ($uploads->filterWithoutError()->getArrayCopy() as $upload) {
			$position++;
			$links[] = static::createFromUpload($pageComponent, $upload, $position);
		}

		return new PageComponentStorageFileCollection($links);
	}

	public static function replaceWithUpload(PageComponent $pageComponent, Upload $upload): PageComponentStorageFile
	{
		foreach ($pageComponent->getStorageFiles() as $link) {
			$link->delete();
		}

		return static::createFromUpload($pageComponent, $upload, 1);
	}

	public function getFirst(): ?PageComponentStorageFile
	{
		return array_values($this->getArrayCopy())[0] ?? null;
	}
}
