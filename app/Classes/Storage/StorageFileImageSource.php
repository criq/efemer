<?php

namespace App\Classes\Storage;

use Katu\Tools\Images\Source;
use Katu\Tools\Package\Package;
use Katu\Types\TClass;
use Katu\Types\TURL;

class StorageFileImageSource extends Source
{
	public function __construct(StorageFile $input)
	{
		parent::__construct($input);
	}

	public function getPackage(): Package
	{
		return new Package([
			"class" => (new TClass($this))->getPortableName(),
			"storageFileId" => $this->getInput()->getId(),
		]);
	}

	public static function createFromPackage(Package $package): Source
	{
		$storageFile = StorageFile::get($package->getPayload()["storageFileId"] ?? null);
		if (!$storageFile) {
			throw new \Katu\Exceptions\NotFoundException;
		}

		return new static($storageFile);
	}

	public function getInput(): StorageFile
	{
		return parent::getInput();
	}

	public function getLocalFile(): ?\Katu\Files\File
	{
		return $this->getInput()->getFile();
	}

	public function getExtension(): ?string
	{
		$extension = pathinfo($this->getInput()->getFileName(), PATHINFO_EXTENSION);

		return $extension !== "" ? strtolower($extension) : null;
	}

	public function getURI(): string
	{
		return $this->getInput()->getURI();
	}

	public function getURL(): ?TURL
	{
		return null;
	}
}
