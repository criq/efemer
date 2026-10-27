<?php

namespace App\Classes\Instagram;

use Katu\Files\File;

class ArchiveState
{
	/** @var array<string, array{backfilled?: bool}> */
	private array $data;

	public function __construct()
	{
		$this->data = [];
		$file = $this->file();
		if ($file->exists()) {
			$decoded = json_decode((string)$file->get(), true);
			if (is_array($decoded)) {
				$this->data = $decoded;
			}
		}
	}

	public function isBackfilled(string $account): bool
	{
		return !empty($this->data[$account]["backfilled"]);
	}

	public function markBackfilled(string $account): void
	{
		$this->data[$account]["backfilled"] = true;
		$this->persist();
	}

	private function persist(): void
	{
		$file = $this->file();
		$file->getDir()->makeDir();
		$file->set(json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
	}

	private function file(): File
	{
		return new File(\App\App::getBaseDir(), "data", "instagram", "archive-state.json");
	}
}
