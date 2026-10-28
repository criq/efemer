<?php

namespace App\Classes\Jobs\Ingest;

use App\Classes\Ingest\IngestItem;
use App\Classes\Jobs\ConsoleStyle;
use App\Classes\Jobs\Job;
use App\Classes\Jobs\Signals;
use Katu\Tools\Calendar\Timeout;

/**
 * Clear LLM enrichment fields; keep stage-1 ingest rows/files. Status → loaded.
 *
 * ./bin/job jobs:run App.Classes.Jobs.Ingest.ResetKeywordEnrichment --args='{"yes":true}' -f
 * ./bin/job jobs:run App.Classes.Jobs.Ingest.ResetKeywordEnrichment --args='{"yes":true,"forceLocks":true}' -f
 */
class ResetKeywordEnrichment extends Job
{
	const DEFAULT_INTERVAL = "100 years";
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
			if (empty($args["yes"])) {
				throw new \RuntimeException("Refusing to reset enrichment without args.yes=true.");
			}

			$forceLocks = !empty($args["forceLocks"]);
			$id = isset($args["id"]) ? (int)$args["id"] : null;

			$items = [];
			if ($id) {
				$item = IngestItem::get($id);
				if (!$item) {
					throw new \InvalidArgumentException("Ingest item #{$id} not found.");
				}
				$items = [$item];
			} else {
				foreach (IngestItem::getAll()->getItems() as $item) {
					if (
						$item->getStatus() === IngestItem::STATUS_KEYWORDED
						|| $item->getStatus() === IngestItem::STATUS_CATEGORIZED
						|| $item->getEnrichmentVersion()
						|| $item->getKeywordsList() !== []
						|| $item->getCoverFileId()
					) {
						$items[] = $item;
					}
				}
			}

			$this->outputLine(ConsoleStyle::step(sprintf(
				"Resetting LLM enrichment on %s item(s)%s.",
				count($items),
				$forceLocks ? " (forceLocks)" : "",
			)));

			$n = 0;
			foreach ($items as $item) {
				Signals::throwIfStopped();
				$item->resetEnrichment($forceLocks);
				$n++;
				$this->outputLine(ConsoleStyle::skipped(
					$item->getSource() . "/" . $item->getSourceKey(),
					"enrichment cleared",
				));
			}

			$this->outputLine(sprintf(
				"%s Reset enrichment on %s item(s).",
				ConsoleStyle::ok("Done"),
				ConsoleStyle::count($n),
			));
		};
	}
}
