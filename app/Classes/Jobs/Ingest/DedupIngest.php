<?php

namespace App\Classes\Jobs\Ingest;

use App\Classes\Ingest\Deduper;
use App\Classes\Jobs\ConsoleStyle;
use App\Classes\Jobs\Job;
use Katu\Tools\Calendar\Timeout;

/**
 * Soft near-duplicate linking via ingest_items.duplicateOfId (cosine on embeddings).
 *
 * ./bin/job jobs:run App.Classes.Jobs.Ingest.DedupIngest -f
 * ./bin/job jobs:run App.Classes.Jobs.Ingest.DedupIngest --args='{"threshold":0.92}' -f
 * ./bin/job jobs:run App.Classes.Jobs.Ingest.DedupIngest --args='{"dryRun":true}' -f
 */
class DedupIngest extends Job
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
			$threshold = isset($args["threshold"])
				? (float)$args["threshold"]
				: Deduper::DEFAULT_THRESHOLD;
			$dryRun = !empty($args["dryRun"]);

			$this->outputLine(ConsoleStyle::step(sprintf(
				"DedupIngest: threshold %.2f%s.",
				$threshold,
				$dryRun ? ", dryRun" : "",
			)));

			$result = (new Deduper)
				->onLog(fn (string $message) => $this->outputLine($message))
				->run($threshold, $dryRun);

			$this->outputLine(sprintf(
				"%s DedupIngest: linked %s, cleared %s, compared %s pairs.",
				ConsoleStyle::ok("Done"),
				ConsoleStyle::count($result["linked"]),
				ConsoleStyle::count($result["cleared"]),
				ConsoleStyle::count($result["compared"]),
			));
		};
	}
}
