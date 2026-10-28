<?php

namespace App\Classes\Jobs\Ingest;

use App\Classes\Ingest\InstagramDumpRefresh;
use App\Classes\Jobs\Job;
use Katu\Tools\Calendar\Timeout;

/**
 * Load offline dumps from data/instagram into ingest_items.
 *
 * docker compose exec -T php php bin/cli.php jobs:run App.Classes.Jobs.Ingest.ImportInstagramDump -f
 */
class ImportInstagramDump extends Job
{
	const DEFAULT_INTERVAL = "100 years";
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
			$root = isset($this->getArgs()["root"]) ? (string)$this->getArgs()["root"] : null;
			$refresh = (new InstagramDumpRefresh)->onLog(fn (string $message) => $this->outputLine($message));
			$refresh->run($root);
		};
	}
}
