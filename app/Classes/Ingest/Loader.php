<?php

namespace App\Classes\Ingest;

use App\Classes\Jobs\ConsoleStyle;
use App\Classes\Storage\StorageFile;
use Katu\Tools\Calendar\Time;

class Loader
{
	/** @var list<callable(string): void> */
	private array $loggers = [];

	public function onLog(callable $logger): self
	{
		$this->loggers[] = $logger;

		return $this;
	}

	/**
	 * @param list<array{contents: string, fileName: string, caption?: ?string}> $files
	 * @param list<string> $qualityFlags
	 */
	public function upsert(
		string $source,
		string $sourceKey,
		?string $sourceUrl,
		?string $title,
		?Time $postedAt,
		?string $bodyHtml,
		array $files,
		?string $instagramAccount = null,
		?string $instagramShortcode = null,
		array $qualityFlags = [],
	): IngestItem {
		$contentHash = hash("sha256", implode("\n", [
			(string)$title,
			(string)$bodyHtml,
			implode("|", array_map(fn (array $f) => hash("sha256", $f["contents"]), $files)),
		]));

		$item = IngestItem::getBySourceKey($source, $sourceKey);
		$isNew = $item === null;
		if ($isNew) {
			$item = new IngestItem;
			$item->timeCreated = new Time;
			$item->setSource($source);
			$item->setSourceKey($sourceKey);
		}

		$unchanged = !$isNew && $item->getContentHash() === $contentHash;
		if ($unchanged) {
			$this->log(ConsoleStyle::skipped($source . "/" . ltrim($sourceKey, "/")));

			return $item;
		}

		$item->setSourceUrl($sourceUrl);
		$item->setInstagramAccount($instagramAccount);
		$item->setInstagramShortcode($instagramShortcode);
		$item->setTitle($title);
		$item->setPostedAt($postedAt);
		$item->setBodyHtml($bodyHtml);
		$item->setContentHash($contentHash);
		$item->setStatus(IngestItem::STATUS_LOADED);
		$item->clearEnrichment();
		$item->setQualityFlags($qualityFlags);
		$item->touchUpdated();
		$item->persist();

		$item->clearFiles();
		$position = 1;
		$fileTime = $postedAt ?: new Time;
		foreach ($files as $file) {
			$contents = $file["contents"] ?? "";
			if ($contents === "") {
				continue;
			}

			$fileName = (string)($file["fileName"] ?? "file.bin");
			$storageFile = StorageFile::createFromContents($contents, $fileName, $fileTime);
			$item->attachStorageFile(
				$storageFile,
				$position++,
				isset($file["caption"]) ? (string)$file["caption"] : null,
				$storageFile->getContentHash(),
			);
		}

		$path = $source . "/" . ltrim($sourceKey, "/");
		$this->log($isNew
			? ConsoleStyle::loaded($path, $title)
			: ConsoleStyle::updated($path, $title));

		return $item;
	}

	private function log(string $message): void
	{
		foreach ($this->loggers as $logger) {
			$logger($message);
		}
	}
}
