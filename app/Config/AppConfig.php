<?php

namespace App\Config;

class AppConfig extends \Katu\Config\AppConfig
{
	public function getDIDefinitions(): array
	{
		return array_merge(parent::getDIDefinitions(), [
			\Katu\Config\ImageConfig::class => \App\Config\ImageConfig::class,
			\Katu\Models\Presets\File::class => \App\Classes\Files\File::class,
		]);
	}
}
