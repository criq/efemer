<?php

namespace App\Classes\Jobs\Ingest;

use App\Classes\Ingest\IngestItem;
use App\Classes\Ingest\PostMaterializer;
use App\Classes\Jobs\ConsoleStyle;
use App\Classes\Jobs\Job;
use App\Classes\Jobs\Signals;
use Katu\Tools\Calendar\Timeout;

/**
 * Materialize keyworded ingest items into public posts.
 *
 * ./bin/job jobs:run App.Classes.Jobs.Ingest.MaterializePosts -f
 * ./bin/job jobs:run App.Classes.Jobs.Ingest.MaterializePosts --args='{"id":427}' -f
 * ./bin/job jobs:run App.Classes.Jobs.Ingest.MaterializePosts --args='{"invitationsOnly":true}' -f
 * ./bin/job jobs:run App.Classes.Jobs.Ingest.MaterializePosts --args='{"dryRun":true,"limit":5}' -f
 */
class MaterializePosts extends Job
{
	const DEFAULT_INTERVAL = "1 day";
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
			$dryRun = !empty($args["dryRun"]);
			$invitationsOnly = !empty($args["invitationsOnly"]);
			$id = isset($args["id"]) ? (int)$args["id"] : null;
			$limit = isset($args["limit"]) ? max(1, (int)$args["limit"]) : null;
			if ($this->getLimit()) {
				$limit = $this->getLimit();
			}

			$items = $this->selectItems($id, $invitationsOnly, $limit);
			$this->outputLine(ConsoleStyle::step(sprintf(
				"MaterializePosts: %s item(s)%s%s.",
				count($items),
				$invitationsOnly ? ", invitationsOnly" : "",
				$dryRun ? ", dryRun" : "",
			)));

			$materializer = (new PostMaterializer)
				->onLog(fn (string $message) => $this->outputLine($message));

			$ok = 0;
			$skipped = 0;
			foreach ($items as $index => $item) {
				Signals::throwIfStopped();
				$this->outputLine(ConsoleStyle::item(
					$index + 1,
					count($items),
					$item->getTitle() ?: ("#" . $item->getId()),
				));

				$post = $materializer->materialize($item, $dryRun);
				if ($post) {
					$ok++;
				} else {
					$skipped++;
				}
			}

			$this->outputLine(sprintf(
				"%s MaterializePosts: %s ok, %s skipped.",
				ConsoleStyle::ok("Done"),
				$ok,
				$skipped,
			));
		};
	}

	/**
	 * @return list<IngestItem>
	 */
	private function selectItems(?int $id, bool $invitationsOnly, ?int $limit): array
	{
		if ($id) {
			$item = IngestItem::get($id);
			if (!$item) {
				throw new \InvalidArgumentException("Ingest item #{$id} not found.");
			}

			return [$item];
		}

		$selected = [];
		foreach (IngestItem::getAll()->getItems() as $item) {
			$status = $item->getStatus();
			if (
				$status !== IngestItem::STATUS_KEYWORDED
				&& $status !== IngestItem::STATUS_CATEGORIZED
				&& $status !== IngestItem::STATUS_MATERIALIZED
			) {
				continue;
			}
			if ($invitationsOnly && !$item->isInvitation()) {
				continue;
			}
			$selected[] = $item;
		}

		usort($selected, function (IngestItem $a, IngestItem $b) {
			$aTime = (int)($a->getStartsAt()?->format("U") ?? $a->getPostedAt()?->format("U") ?? 0);
			$bTime = (int)($b->getStartsAt()?->format("U") ?? $b->getPostedAt()?->format("U") ?? 0);

			return $bTime <=> $aTime;
		});

		if ($limit !== null) {
			$selected = array_slice($selected, 0, $limit);
		}

		return $selected;
	}
}
