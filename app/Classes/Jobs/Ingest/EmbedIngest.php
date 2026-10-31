<?php

namespace App\Classes\Jobs\Ingest;

use App\Classes\Ingest\IngestItem;
use App\Classes\Ingest\ItemEmbedder;
use App\Classes\Jobs\ConsoleStyle;
use App\Classes\Jobs\Job;
use App\Classes\Jobs\Signals;
use App\Classes\ThirdParty\Google\Gemini;
use Katu\Tools\Calendar\Timeout;

/**
 * Gemini text embeddings on keyworded/materialized ingest items.
 *
 * ./bin/job jobs:run App.Classes.Jobs.Ingest.EmbedIngest -f
 * ./bin/job jobs:run App.Classes.Jobs.Ingest.EmbedIngest --args='{"limit":50}' -f
 * ./bin/job jobs:run App.Classes.Jobs.Ingest.EmbedIngest --args='{"id":123,"reembed":true}' -f
 * ./bin/job jobs:run App.Classes.Jobs.Ingest.EmbedIngest --args='{"dryRun":true,"limit":1}' -f
 */
class EmbedIngest extends Job
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
			$limit = isset($args["limit"]) ? max(1, (int)$args["limit"]) : null;
			if ($this->getLimit()) {
				$limit = $this->getLimit();
			}
			$reembed = !empty($args["reembed"]);
			$dryRun = !empty($args["dryRun"]);
			$id = isset($args["id"]) ? (int)$args["id"] : null;

			$gemini = new Gemini;
			$embedder = (new ItemEmbedder($gemini))
				->onLog(fn (string $message) => $this->outputLine($message));

			$items = $this->selectItems($id, $limit, $reembed);
			$this->outputLine(ConsoleStyle::step(sprintf(
				"EmbedIngest: %s item(s)%s%s.",
				count($items),
				$reembed ? ", reembed" : "",
				$dryRun ? ", dryRun" : "",
			)));

			$ok = 0;
			$failed = 0;
			$skipped = 0;

			foreach ($items as $index => $item) {
				Signals::throwIfStopped();
				$n = $index + 1;
				$this->outputLine(ConsoleStyle::item($n, count($items), $item->getTitle() ?: ("#" . $item->getId())));

				try {
					$result = $embedder->embed($item, $dryRun);
					if ($result === null) {
						$skipped++;
					} else {
						$ok++;
					}
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
				"%s EmbedIngest: ok %s, failed %s, skipped %s.",
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
	private function selectItems(?int $id, ?int $limit, bool $reembed): array
	{
		if ($id) {
			$item = IngestItem::get($id);
			if (!$item) {
				throw new \InvalidArgumentException("Ingest item #{$id} not found.");
			}

			return [$item];
		}

		$allowed = [
			IngestItem::STATUS_KEYWORDED,
			IngestItem::STATUS_MATERIALIZED,
		];
		$items = [];
		foreach (IngestItem::getAll()->getItems() as $item) {
			if (!in_array($item->getStatus(), $allowed, true)) {
				continue;
			}
			if (!$reembed && $item->hasEmbedding()) {
				continue;
			}
			$items[] = $item;
		}

		usort($items, function (IngestItem $a, IngestItem $b) {
			return ((int)($b->getPostedAt()?->format("U") ?? 0)) <=> ((int)($a->getPostedAt()?->format("U") ?? 0));
		});

		if ($limit !== null) {
			$items = array_slice($items, 0, $limit);
		}

		return $items;
	}
}
