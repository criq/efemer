<?php

namespace App\Config;

class AppConfig extends \Katu\Config\AppConfig
{
	public function getDIDefinitions(): array
	{
		return [
			\Katu\Config\ImageConfig::class => \App\Config\ImageConfig::class,
		];
	}
}
