<?php

namespace App\Classes\Jobs\Ingest;

use App\Classes\Ingest\EfemerCzRefresh;
use App\Classes\Jobs\Job;
use Katu\Tools\Calendar\Timeout;

/**
 * Stage 1: load efemer.cz diary posts into ingest_items.
 *
 * docker compose exec -T php php bin/cli.php jobs:run App.Classes.Jobs.Ingest.RefreshEfemerCz
 */
class RefreshEfemerCz extends Job
{
	const DEFAULT_INTERVAL = "1 day";
	const DEFAULT_TIMEOUT = "2 hours";

	public function __construct(array $args = [])
	{
		parent::__construct($args);
		$this->setTimeout(new Timeout(static::DEFAULT_TIMEOUT));
		$this->setMaxLoadAverage(null);
	}

	public function getCallback(): callable
	{
		return function () {
			$refresh = (new EfemerCzRefresh)->onLog(fn (string $message) => $this->outputLine($message));
			$refresh->run();
		};
	}
}
