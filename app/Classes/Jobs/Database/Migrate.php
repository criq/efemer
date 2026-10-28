<?php

namespace App\Classes\Jobs\Database;

use App\Classes\Database\Migrator;
use App\Classes\Jobs\ConsoleStyle;
use App\Classes\Jobs\Job;
use Katu\Tools\Calendar\Timeout;

/**
 * Apply database migrations.
 *
 * docker compose exec -T php php bin/cli.php jobs:run App.Classes.Jobs.Database.Migrate -f
 */
class Migrate extends Job
{
	const DEFAULT_INTERVAL = "1 minute";
	const DEFAULT_TIMEOUT = "30 minutes";

	public function __construct(array $args = [])
	{
		parent::__construct($args);
		$this->setTimeout(new Timeout(static::DEFAULT_TIMEOUT));
		$this->setMaxLoadAverage(null);
	}

	public function getCallback(): callable
	{
		return function () {
			(new Migrator)->run();
			$this->outputLine(ConsoleStyle::done("migrations"));
		};
	}
}
