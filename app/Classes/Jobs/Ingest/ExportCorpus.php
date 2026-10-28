<?php

namespace App\Classes\Jobs\Ingest;

use App\Classes\Ingest\CorpusExport;
use App\Classes\Ingest\IngestItem;
use App\Classes\Jobs\ConsoleStyle;
use App\Classes\Jobs\Job;
use Katu\Tools\Calendar\Timeout;

/**
 * Export keyworded corpus for taxonomy discovery (CSV/JSON + co-occurrence).
 *
 * ./bin/job jobs:run App.Classes.Jobs.Ingest.ExportCorpus -f
 * ./bin/job jobs:run App.Classes.Jobs.Ingest.ExportCorpus --args='{"status":"keyworded"}' -f
 * ./bin/job jobs:run App.Classes.Jobs.Ingest.ExportCorpus --args='{"status":""}' -f   # all statuses
 */
class ExportCorpus extends Job
{
	const DEFAULT_INTERVAL = "1 day";
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
			$args = $this->getArgs();
			$status = array_key_exists("status", $args)
				? (string)$args["status"]
				: IngestItem::STATUS_KEYWORDED;
			if ($status === "") {
				$status = null;
			}

			$export = (new CorpusExport)->onLog(fn (string $message) => $this->outputLine($message));
			$result = $export->run($status);

			$this->outputLine(sprintf(
				"%s %s items, %s keywords → %s",
				ConsoleStyle::ok("Done"),
				ConsoleStyle::count($result["itemCount"]),
				ConsoleStyle::count($result["keywordCount"]),
				ConsoleStyle::path($result["dir"]),
			));
		};
	}
}
