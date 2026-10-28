<?php

namespace App\Classes\Jobs\Ingest;

use App\Classes\Ingest\IngestItem;
use App\Classes\Ingest\KeywordAnalyzer;
use App\Classes\Jobs\ConsoleStyle;
use App\Classes\Jobs\Job;
use App\Classes\Jobs\Signals;
use App\Classes\ThirdParty\Google\Gemini;
use Katu\Tools\Calendar\Timeout;

/**
 * Stage 2: Gemini keyword / vision enrichment on loaded ingest items.
 *
 * ./bin/job jobs:run App.Classes.Jobs.Ingest.KeywordIngest -f
 * ./bin/job jobs:run App.Classes.Jobs.Ingest.KeywordIngest --args='{"limit":5,"rekeyword":true}' -f
 * ./bin/job jobs:run App.Classes.Jobs.Ingest.KeywordIngest --args='{"dryRun":true,"limit":1}' -f
 * ./bin/job jobs:run App.Classes.Jobs.Ingest.KeywordIngest --args='{"id":123}' -f
 */
class KeywordIngest extends Job
{
	const DEFAULT_INTERVAL = "1 day";
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
			$limit = isset($args["limit"]) ? max(1, (int)$args["limit"]) : 20;
			if ($this->getLimit()) {
				$limit = $this->getLimit();
			}
			$rekeyword = !empty($args["rekeyword"]);
			$dryRun = !empty($args["dryRun"]);
			$maxImages = isset($args["maxImages"]) ? max(1, (int)$args["maxImages"]) : 12;
			$id = isset($args["id"]) ? (int)$args["id"] : null;

			// Fail fast before touching the DB if the key is missing.
			$gemini = new Gemini;
			$analyzer = (new KeywordAnalyzer($gemini))
				->onLog(fn (string $message) => $this->outputLine($message));

			$items = $this->selectItems($id, $limit, $rekeyword);
			$this->outputLine(ConsoleStyle::step(sprintf(
				"KeywordIngest: %s item(s)%s%s.",
				count($items),
				$rekeyword ? ", rekeyword" : "",
				$dryRun ? ", dryRun" : "",
			)));

			$ok = 0;
			$failed = 0;
			$skipped = 0;

			foreach ($items as $index => $item) {
				Signals::throwIfStopped();
				$n = $index + 1;
				$this->outputLine(ConsoleStyle::item($n, count($items), $item->getTitle() ?: ("#" . $item->getId())));

				if (!$rekeyword && !$item->needsEnrichment()) {
					$this->outputLine(ConsoleStyle::skipped(
						$item->getSource() . "/" . $item->getSourceKey(),
						"already keyworded",
					));
					$skipped++;
					continue;
				}

				try {
					$analyzer->analyze($item, $maxImages, $dryRun);
					if ($dryRun) {
						$this->outputLine(ConsoleStyle::ok("Dry-run OK") . " #" . $item->getId());
					} else {
						$item = IngestItem::get((int)$item->getId()) ?: $item;
						$this->outputLine(ConsoleStyle::loaded(
							$item->getSource() . "/" . $item->getSourceKey(),
							implode(", ", array_slice($item->getKeywordsList(), 0, 8)),
						));
					}
					$ok++;
				} catch (\Throwable $exception) {
					if ($exception->getMessage() === "Interrupted.") {
						throw $exception;
					}
					$failed++;
					$this->outputLine(
						ConsoleStyle::path($item->getSource() . "/" . $item->getSourceKey())
						. " "
						. ConsoleStyle::failure($exception),
					);
				}
			}

			$this->outputLine(sprintf(
				"%s KeywordIngest: ok %s, failed %s, skipped %s.",
				ConsoleStyle::ok("Done"),
				ConsoleStyle::count($ok),
				ConsoleStyle::count($failed),
				ConsoleStyle::count($skipped),
			));
		};
	}

	/**
	 * @return list<IngestItem>
	 */
	private function selectItems(?int $id, int $limit, bool $rekeyword): array
	{
		if ($id) {
			$item = IngestItem::get($id);
			if (!$item) {
				throw new \InvalidArgumentException("Ingest item #{$id} not found.");
			}

			return [$item];
		}

		$items = IngestItem::getAll()->getItems();
		usort($items, function (IngestItem $a, IngestItem $b) {
			return ((int)($b->getPostedAt()?->format("U") ?? 0)) <=> ((int)($a->getPostedAt()?->format("U") ?? 0));
		});

		$selected = [];
		foreach ($items as $item) {
			if (!$rekeyword && !$item->needsEnrichment()) {
				continue;
			}
			if (!in_array($item->getStatus(), [
				IngestItem::STATUS_LOADED,
				IngestItem::STATUS_KEYWORDED,
			], true) && !$rekeyword) {
				continue;
			}

			$selected[] = $item;
			if (count($selected) >= $limit) {
				break;
			}
		}

		return $selected;
	}
}
