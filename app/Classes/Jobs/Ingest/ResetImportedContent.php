<?php

namespace App\Classes\Jobs\Ingest;

use App\Classes\Ingest\Reset;
use App\Classes\Jobs\Job;
use Katu\Tools\Calendar\Timeout;

/**
 * Wipe posts, ingest, legacy pages, related GCS, and purge unreferenced bucket objects.
 *
 * docker compose exec -T php php bin/cli.php jobs:run App.Classes.Jobs.Ingest.ResetImportedContent --args='{"yes":true}' -f
 */
class ResetImportedContent extends Job
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
			$args = $this->getArgs();
			if (empty($args["yes"])) {
				throw new \RuntimeException("Refusing to reset without args.yes=true.");
			}

			$reset = (new Reset)->onLog(fn (string $message) => $this->outputLine($message));
			$reset->run();
		};
	}
}
