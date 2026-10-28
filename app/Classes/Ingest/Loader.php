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
	 * @param list<array{contents: string, fileName: string, caption?: ?string, sourceSlot?: ?string, galleryIndex?: ?int}> $files
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
		$sourceKey = IngestItem::normalizeSourceKey($sourceKey);
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
		} elseif ($item->getSourceKey() !== $sourceKey) {
			// Migrate legacy "/path" keys to the canonical form on touch.
			$item->setSourceKey($sourceKey);
		}

		$unchanged = !$isNew && $item->getContentHash() === $contentHash;
		if ($unchanged) {
			$this->syncFileSlots($item, $files);
			$this->log(ConsoleStyle::skipped($source . "/" . $sourceKey));

			return $item;
		}

		// Upload to GCS first so a mid-flight failure leaves the previous files/hash intact.
		$fileTime = $postedAt ?: new Time;
		$uploaded = [];
		foreach ($files as $file) {
			$contents = $file["contents"] ?? "";
			if ($contents === "") {
				continue;
			}

			$fileName = (string)($file["fileName"] ?? "file.bin");
			$storageFile = StorageFile::createFromContents($contents, $fileName, $fileTime);
			$uploaded[] = [
				"storageFile" => $storageFile,
				"caption" => isset($file["caption"]) ? (string)$file["caption"] : null,
				"sourceSlot" => isset($file["sourceSlot"]) ? (string)$file["sourceSlot"] : null,
				"galleryIndex" => array_key_exists("galleryIndex", $file) && $file["galleryIndex"] !== null
					? (int)$file["galleryIndex"]
					: null,
			];
		}

		$item->setSourceUrl($sourceUrl);
		$item->setInstagramAccount($instagramAccount);
		$item->setInstagramShortcode($instagramShortcode);
		$item->setTitle($title);
		$item->setPostedAt($postedAt);
		$item->setBodyHtml($bodyHtml);
		$item->setStatus(IngestItem::STATUS_LOADED);
		$item->clearEnrichment();
		$item->setQualityFlags($qualityFlags);
		$item->touchUpdated();
		$item->persist();

		$item->clearFiles();
		$position = 1;
		foreach ($uploaded as $row) {
			$item->attachStorageFile(
				$row["storageFile"],
				$position++,
				$row["caption"],
				$row["storageFile"]->getContentHash(),
				$row["sourceSlot"],
				$row["galleryIndex"],
			);
		}

		// Hash only after files are linked so a mid-flight failure retries next run.
		$item->setContentHash($contentHash);
		$item->persist();

		$path = $source . "/" . $sourceKey;
		$this->log($isNew
			? ConsoleStyle::loaded($path, $title)
			: ConsoleStyle::updated($path, $title));

		return $item;
	}

	/**
	 * When binary content is unchanged, still refresh gallery slot metadata from the source.
	 *
	 * @param list<array{contents?: string, sourceSlot?: ?string, galleryIndex?: ?int}> $files
	 */
	private function syncFileSlots(IngestItem $item, array $files): void
	{
		$existing = array_values($item->getFiles()->getArrayCopy());
		$i = 0;
		foreach ($files as $file) {
			if (($file["contents"] ?? "") === "") {
				continue;
			}
			if (!isset($existing[$i])) {
				break;
			}

			$link = $existing[$i];
			$slot = array_key_exists("sourceSlot", $file) ? $file["sourceSlot"] : null;
			$galleryIndex = array_key_exists("galleryIndex", $file) && $file["galleryIndex"] !== null
				? (int)$file["galleryIndex"]
				: null;
			$changed = false;

			if ($slot !== null && $link->getSourceSlot() !== $slot) {
				$link->setSourceSlot($slot);
				$changed = true;
			}
			if ($galleryIndex !== null && $link->getGalleryIndex() !== $galleryIndex) {
				$link->setGalleryIndex($galleryIndex);
				$changed = true;
			}
			if ($changed) {
				$link->persist();
			}

			$i++;
		}
	}

	private function log(string $message): void
	{
		foreach ($this->loggers as $logger) {
			$logger($message);
		}
	}
}
