<?php

namespace App\Classes\Views;

use Katu\Config\EnvConfig;

class HTMLEngine extends \Katu\Tools\Views\FilesystemLoaderTwigEngine
{
	private const DEFAULT_LEADHUB_TRACKING_ID = "bQVrGwsJSGSpLS5";

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

		$trackingId = trim((string)((new EnvConfig)->getVariable("LEADHUB_TRACKING_ID") ?: self::DEFAULT_LEADHUB_TRACKING_ID));
		$twig->addGlobal("leadhubTrackingId", $trackingId !== "" ? $trackingId : null);

		return $twig;
	}
}
