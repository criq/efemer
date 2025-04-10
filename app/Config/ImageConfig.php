<?php

namespace App\Config;

use Katu\Tools\Images\FilterCollection;
use Katu\Tools\Images\Filters\FitFilter;
use Katu\Tools\Images\Version;
use Katu\Tools\Images\VersionCollection;

class ImageConfig extends \Katu\Config\ImageConfig
{
	public function getVersions(): VersionCollection
	{
		return new VersionCollection([
			new Version("THUMBNAIL", "webp", 100, new FilterCollection([
				new FitFilter([
					"width" => 100,
					"height" => 100,
				]),
			])),
		]);
	}
}
