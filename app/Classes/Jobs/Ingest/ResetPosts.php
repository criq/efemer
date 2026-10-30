<?php

namespace App\Classes\Jobs\Ingest;

use App\Classes\Content\Post;
use App\Classes\Ingest\IngestItem;
use App\Classes\Jobs\ConsoleStyle;
use App\Classes\Jobs\Job;
use Katu\PDO\Connection;
use Katu\Tools\Calendar\Timeout;

/**
 * Delete public posts and unlink ingest (status → keyworded). Keeps ingest + files.
 *
 * ./bin/job jobs:run App.Classes.Jobs.Ingest.ResetPosts --args='{"yes":true}' -f
 */
class ResetPosts extends Job
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
				throw new \RuntimeException("Refusing to reset posts without args.yes=true.");
			}

			$connection = Connection::getInstance("app");

			$this->outputLine(ConsoleStyle::step("Clearing post ↔ ingest links…"));
			$connection->createQuery("UPDATE posts SET ingestItemId = NULL")->getResult();
			$connection->createQuery("UPDATE ingest_items SET postId = NULL, eventId = NULL")->getResult();

			$this->outputLine(ConsoleStyle::step("Deleting posts…"));
			$count = 0;
			foreach (Post::getAll() as $post) {
				$post->delete();
				$count++;
			}
			$this->outputLine(ConsoleStyle::ok("Deleted") . " " . ConsoleStyle::count($count) . " posts.");

			$this->outputLine(ConsoleStyle::step("Re-queueing materialized ingest as keyworded…"));
			$requeued = 0;
			foreach (IngestItem::getAll()->getItems() as $item) {
				if ($item->getStatus() !== IngestItem::STATUS_MATERIALIZED) {
					continue;
				}
				$item->setStatus(IngestItem::STATUS_KEYWORDED);
				$item->touchUpdated();
				$item->persist();
				$requeued++;
			}
			$this->outputLine(ConsoleStyle::ok("Re-queued") . " " . ConsoleStyle::count($requeued) . " ingest items.");
			$this->outputLine(ConsoleStyle::done("posts reset"));
		};
	}
}
