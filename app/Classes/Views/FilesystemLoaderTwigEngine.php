<?php

namespace App\Classes\Views;

class FilesystemLoaderTwigEngine extends TwigEngine
{
	protected static function getTwigLoader(): \Twig\Loader\LoaderInterface
	{
		return \Katu\Tools\Views\FilesystemLoaderTwigEngine::getTwigLoader();
	}
}
