<?php

namespace App\Classes\Views;

use App\Classes\Intl\Formatter;
use App\Classes\Intl\Locale;
use App\Classes\Time;
use App\Models\Branch;
use App\Models\Campaigns\ActivityCollection;
use App\Models\LandingPages\LandingPage;
use App\Models\Newsletters\NewsletterListCollection;
use Katu\Types\TClass;
use Katu\Types\TIdentifier;
use Sexy\Sexy as SX;

abstract class TwigEngine extends \Katu\Tools\Views\TwigEngine
{
	protected function createTwig(): \Twig\Environment
	{
		$twig = parent::createTwig();

		/***************************************************************************
		 * Cache.
		 */
		// $cacheProvider = new \Twig\CacheExtension\CacheProvider\PsrCacheAdapter(new \Cache\Adapter\Apcu\ApcuCachePool);
		// $cacheStrategy = new \Twig\CacheExtension\CacheStrategy\LifetimeCacheStrategy($cacheProvider);
		// $cacheExtension = new \Twig\CacheExtension\Extension($cacheStrategy);

		// $twig->addExtension($cacheExtension);
		// $twig->addExtension(new \Twig\Extension\DebugExtension);

		$twig->addFunction(new \Twig\TwigFunction("getCacheKey", function () {
			$args = func_get_args();
			if (\Katu\Config\Env::getPlatform() == "dev") {
				$args[] = time();
				$args[] = microtime(true);
			}

			return implode(".", $args);
		}));

		/***************************************************************************
		 * Extensions.
		 */
		$twig->addExtension(new \Twig\Extensions\TextExtension);

		return $twig;
	}

	protected function getTwigConfig(): array
	{
		return array_merge(parent::getTwigConfig(), [
			"auto_reload" => \Katu\Config\Env::getPlatform() == "dev",
			"debug" => \Katu\Config\Env::getPlatform() == "dev",
		]);
	}

	protected function getCommonData(): array
	{
		$data = parent::getCommonData();

		// $data["_user"] = \App\Models\Users\User::getFromRequest($this->getRequest());

		return $data;
	}

	// public function getLocale(): Locale
	// {
	// 	return Locale::createDefault();
	// }

	// public function getFormatter(): Formatter
	// {
	// 	return new Formatter($this->getLocale());
	// }
}
