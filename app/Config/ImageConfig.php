<?php

namespace App\Config;

use Katu\Tools\Images\FilterCollection;
use Katu\Tools\Images\Filters\ResizeFilter;
use Katu\Tools\Images\Version;
use Katu\Tools\Images\VersionCollection;

class ImageConfig extends \Katu\Config\ImageConfig
{
	public function getVersions(): VersionCollection
	{
		return new VersionCollection([
			new Version("THUMBNAIL", "webp", 100, new FilterCollection([
				new ResizeFilter([
					"width" => 400,
					"height" => 400,
					"dontUpsize" => true,
				]),
			])),
			new Version("GALLERY", "webp", 90, new FilterCollection([
				new ResizeFilter([
					"width" => 1200,
					"height" => 1200,
					"dontUpsize" => true,
				]),
			])),
		]);
	}
}
