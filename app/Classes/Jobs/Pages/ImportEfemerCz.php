<?php

namespace App\Classes\Jobs\Pages;

use App\Classes\Import\EfemerCzImporter;
use App\Classes\Jobs\Job;
use Katu\Tools\Calendar\Timeout;

/**
 * Legacy page-builder import from efemer.cz (wipes pages, rebuilds homepage + diary pages).
 *
 * Prefer ingest RefreshEfemerCz for the content-model pipeline.
 *
 * docker compose exec -T php php bin/cli.php jobs:run App.Classes.Jobs.Pages.ImportEfemerCz -f
 */
class ImportEfemerCz extends Job
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
			$importer = (new EfemerCzImporter)->onLog(fn (string $message) => $this->outputLine($message));
			$importer->run();
		};
	}
}
