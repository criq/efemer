<?php

namespace App\Classes\Jobs\Ingest;

use App\Classes\Ingest\IngestItem;
use App\Classes\Ingest\KeywordAnalyzer;
use App\Classes\Ingest\KeywordQc as KeywordQcReviewer;
use App\Classes\Jobs\ConsoleStyle;
use App\Classes\Jobs\Job;
use App\Classes\Jobs\Signals;
use App\Classes\ThirdParty\Google\Gemini;
use Katu\Tools\Calendar\Timeout;

/**
 * Deterministic QC on keyworded ingest items (typos, cover, roles, pattern).
 * Optionally re-runs KeywordIngest for items flagged needs_rekeyword.
 *
 * ./bin/job jobs:run App.Classes.Jobs.Ingest.KeywordQc -f
 * ./bin/job jobs:run App.Classes.Jobs.Ingest.KeywordQc --args='{"dryRun":true}' -f
 * ./bin/job jobs:run App.Classes.Jobs.Ingest.KeywordQc --args='{"rekeyword":true}' -f
 * ./bin/job jobs:run App.Classes.Jobs.Ingest.KeywordQc --args='{"id":123}' -f
 */
class KeywordQc extends Job
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
			$dryRun = !empty($args["dryRun"]);
			$fix = !array_key_exists("fix", $args) || !empty($args["fix"]);
			if ($dryRun) {
				$fix = false;
			}
			$rekeyword = !empty($args["rekeyword"]);
			$id = isset($args["id"]) ? (int)$args["id"] : null;

			$items = $this->selectItems($id, $limit);
			$this->outputLine(ConsoleStyle::step(sprintf(
				"KeywordQc: %s item(s)%s%s.",
				count($items),
				$dryRun ? ", dryRun" : ($fix ? "" : ", no-fix"),
				$rekeyword ? ", rekeyword flagged" : "",
			)));

			$reviewer = new KeywordQcReviewer;
			$ok = 0;
			$fixedCount = 0;
			$issueCount = 0;
			$needsRekeywordIds = [];

			foreach ($items as $index => $item) {
				Signals::throwIfStopped();
				$n = $index + 1;
				$result = $reviewer->review($item, $fix);
				$ok++;

				$label = $item->getTitle() ?: ("#" . $item->getId());
				if ($result["fixed"] === [] && $result["issues"] === []) {
					$this->outputLine(ConsoleStyle::item($n, count($items), $label) . " " . ConsoleStyle::muted("ok"));
					continue;
				}

				$this->outputLine(ConsoleStyle::item($n, count($items), $label));
				foreach ($result["fixed"] as $line) {
					$fixedCount++;
					$this->outputLine("  " . ConsoleStyle::ok("fix") . " " . $line);
				}
				foreach ($result["issues"] as $line) {
					$issueCount++;
					$this->outputLine("  " . ConsoleStyle::warn($line));
				}
				if ($result["needsRekeyword"]) {
					$needsRekeywordIds[] = (int)$item->getId();
				}
			}

			$this->outputLine(sprintf(
				"%s KeywordQc: reviewed %s · fixes %s · issues %s · needs_rekeyword %s.",
				ConsoleStyle::ok("Done"),
				ConsoleStyle::count($ok),
				ConsoleStyle::count($fixedCount),
				ConsoleStyle::count($issueCount),
				ConsoleStyle::count(count($needsRekeywordIds)),
			));

			if (!$rekeyword || $needsRekeywordIds === [] || $dryRun) {
				return;
			}

			$this->outputLine(ConsoleStyle::step("Rekeywording " . count($needsRekeywordIds) . " flagged item(s)."));
			$gemini = new Gemini;
			$analyzer = (new KeywordAnalyzer($gemini))
				->onLog(fn (string $message) => $this->outputLine($message));

			$reOk = 0;
			$reFailed = 0;
			foreach ($needsRekeywordIds as $index => $itemId) {
				Signals::throwIfStopped();
				$item = IngestItem::get($itemId);
				if (!$item) {
					continue;
				}
				$n = $index + 1;
				$this->outputLine(ConsoleStyle::item($n, count($needsRekeywordIds), $item->getTitle() ?: ("#" . $itemId)));
				try {
					$analyzer->analyze($item);
					$item = IngestItem::get($itemId) ?: $item;
					$item->removeQualityFlag(KeywordQcReviewer::FLAG_NEEDS_REKEYWORD);
					$qc = $reviewer->review($item, true);
					$this->outputLine(ConsoleStyle::loaded(
						$item->getSource() . "/" . $item->getSourceKey(),
						implode(", ", array_slice($item->getKeywordsList(), 0, 8)),
					));
					if ($qc["needsRekeyword"]) {
						$this->outputLine(ConsoleStyle::warn("still needs_rekeyword after rekeyword"));
					}
					$reOk++;
				} catch (\Throwable $exception) {
					if ($exception->getMessage() === "Interrupted.") {
						throw $exception;
					}
					$reFailed++;
					$this->outputLine(
						ConsoleStyle::path($item->getSource() . "/" . $item->getSourceKey())
						. " "
						. ConsoleStyle::failure($exception),
					);
				}
			}

			$this->outputLine(sprintf(
				"%s Rekeyword: ok %s, failed %s.",
				ConsoleStyle::ok("Done"),
				ConsoleStyle::count($reOk),
				ConsoleStyle::count($reFailed),
			));
		};
	}

	/**
	 * @return list<IngestItem>
	 */
	private function selectItems(?int $id, ?int $limit): array
	{
		if ($id) {
			$item = IngestItem::get($id);
			if (!$item) {
				throw new \InvalidArgumentException("Ingest item #{$id} not found.");
			}

			return [$item];
		}

		$items = IngestItem::getAll()->getItems();
		usort($items, fn (IngestItem $a, IngestItem $b) => ((int)$a->getId()) <=> ((int)$b->getId()));

		$selected = [];
		foreach ($items as $item) {
			if ($item->getStatus() !== IngestItem::STATUS_KEYWORDED) {
				continue;
			}
			$selected[] = $item;
			if ($limit !== null && count($selected) >= $limit) {
				break;
			}
		}

		return $selected;
	}
}
