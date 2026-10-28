<?php

namespace App\Classes\Jobs\Ingest;

use App\Classes\Ingest\InstagramRefresh;
use App\Classes\Jobs\Job;
use Katu\Tools\Calendar\Timeout;

/**
 * Stage 1: refresh both Instagram accounts into ingest_items.
 *
 * docker compose exec -T php php bin/cli.php jobs:run App.Classes.Jobs.Ingest.RefreshInstagramAccounts --args='{"full":true}' -f
 * docker compose exec -T php php bin/cli.php jobs:loop App.Classes.Jobs.Ingest.RefreshInstagramAccounts --interval=21600 -f
 */
class RefreshInstagramAccounts extends Job
{
	const DEFAULT_INTERVAL = "6 hours";
	const DEFAULT_TIMEOUT = "6 hours";

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
			$full = !empty($args["full"]);
			$maxNew = isset($args["maxNew"]) ? (int)$args["maxNew"] : null;
			if ($this->getLimit()) {
				$maxNew = $this->getLimit();
			}

			$refresh = (new InstagramRefresh)->onLog(fn (string $message) => $this->outputLine($message));
			foreach (InstagramRefresh::ACCOUNTS as $account) {
				$refresh->run($account, $full, $maxNew);
			}
		};
	}
}
