<?php

namespace App\Classes\Ingest;

use App\Classes\Jobs\ConsoleStyle;

/**
 * Load offline Instaloader dumps from data/instagram into ingest_items.
 */
class InstagramDumpRefresh
{
	/** @var list<callable(string): void> */
	private array $loggers = [];

	public function onLog(callable $logger): self
	{
		$this->loggers[] = $logger;

		return $this;
	}

	public function run(?string $root = null): void
	{
		$root = $root ?: (string)(new \Katu\Files\File(\App\App::getBaseDir(), "data", "instagram"));
		$loader = (new Loader)->onLog(fn (string $message) => $this->log($message));
		$refresh = (new InstagramRefresh)->onLog(fn (string $message) => $this->log($message));
		$imported = 0;

		foreach (InstagramRefresh::ACCOUNTS as $account) {
			$accountDir = $root . "/" . $account;
			if (!is_dir($accountDir)) {
				$this->log(ConsoleStyle::warn("Missing dump: " . $accountDir));
				continue;
			}

			foreach (glob($accountDir . "/*/post.json") ?: [] as $jsonPath) {
				$post = json_decode((string)file_get_contents($jsonPath), true);
				if (!is_array($post) || empty($post["shortcode"])) {
					continue;
				}

				if (empty($post["account"])) {
					$post["account"] = $account;
				}

				if ($refresh->importMaterialized($loader, $post, dirname($jsonPath))) {
					$imported++;
				}
			}
		}

		$this->log(ConsoleStyle::done("Instagram dump into ingest", $imported));
	}

	private function log(string $message): void
	{
		foreach ($this->loggers as $logger) {
			$logger($message);
		}
	}
}
