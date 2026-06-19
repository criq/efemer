<?php

namespace App\Classes\Views;

class HTMLEngine extends \Katu\Tools\Views\FilesystemLoaderTwigEngine
{
	protected function createTwig(): \Twig\Environment
	{
		$twig = parent::createTwig();

		$twig->addFunction(new \Twig\TwigFunction("getCacheKey", function () {
			$args = func_get_args();
			if (\App\App::getAppConfig()->getIsEnvironment("DEVELOPMENT")) {
				$args[] = time();
				$args[] = microtime(true);
			}

			return implode(".", $args);
		}));

		$twig->addExtension(new \Twig\Extensions\TextExtension);

		return $twig;
	}
}
