<?php

namespace App\Classes\Ingest;

use Katu\Files\File;
use Katu\Tools\Calendar\Time;

class CorpusExport
{
	/** @var list<callable(string): void> */
	private array $loggers = [];

	public function onLog(callable $logger): self
	{
		$this->loggers[] = $logger;

		return $this;
	}

	/**
	 * @return array{dir: string, files: list<string>, itemCount: int, keywordCount: int}
	 */
	public function run(?string $status = IngestItem::STATUS_KEYWORDED): array
	{
		// tmp/ is mounted into the PHP container; data/ingest is not.
		$stamp = (new Time)->format("Ymd-His");
		$dir = new File(\App\App::getBaseDir(), "tmp", "ingest", "exports", $stamp);
		$dir->makeDir();

		$items = [];
		foreach (IngestItem::getAll()->getItems() as $item) {
			if ($status !== null && $status !== "" && $item->getStatus() !== $status) {
				continue;
			}
			$items[] = $item;
		}

		usort($items, function (IngestItem $a, IngestItem $b) {
			return ((int)($b->getPostedAt()?->format("U") ?? 0)) <=> ((int)($a->getPostedAt()?->format("U") ?? 0));
		});

		$keywordFreq = [];
		$cooccur = [];
		$rows = [];
		$fileRows = [];

		foreach ($items as $item) {
			$keywords = $item->getKeywordsList();
			foreach ($keywords as $keyword) {
				$keywordFreq[$keyword] = ($keywordFreq[$keyword] ?? 0) + 1;
			}
			$unique = array_values(array_unique($keywords));
			sort($unique);
			for ($i = 0; $i < count($unique); $i++) {
				for ($j = $i + 1; $j < count($unique); $j++) {
					$key = $unique[$i] . "\t" . $unique[$j];
					$cooccur[$key] = ($cooccur[$key] ?? 0) + 1;
				}
			}

			$hints = $item->getLayoutHints();
			$rows[] = [
				"id" => (int)$item->getId(),
				"source" => $item->getSource(),
				"sourceKey" => $item->getSourceKey(),
				"title" => $item->getTitle(),
				"status" => $item->getStatus(),
				"kind" => $item->getKind(),
				"postedAt" => $item->getPostedAt()?->format("Y-m-d H:i:s"),
				"enrichmentVersion" => $item->getEnrichmentVersion(),
				"coverFileId" => $item->getCoverFileId(),
				"duplicateOfId" => $item->getDuplicateOfId(),
				"hasEmbedding" => $item->hasEmbedding(),
				"keywords" => $keywords,
				"themes" => $item->getThemesList(),
				"entities" => $item->getEntitiesList(),
				"layoutPattern" => is_array($hints) ? ($hints["pattern"] ?? null) : null,
				"fileCount" => $item->getFileCount(),
			];

			foreach ($item->getFiles() as $file) {
				$fileRows[] = [
					"ingestItemId" => (int)$item->getId(),
					"fileId" => (int)$file->getId(),
					"position" => $file->getPosition(),
					"sourceSlot" => $file->getSourceSlot(),
					"role" => $file->getRole(),
					"rating" => $file->getRating(),
					"isCover" => $file->isCover(),
					"demote" => $file->isDemoted(),
					"keywords" => $file->getKeywords(),
					"altText" => $file->getAltText(),
				];
			}
		}

		arsort($keywordFreq);
		arsort($cooccur);

		$written = [];
		$written[] = $this->writeJson($dir, "items.json", $rows);
		$written[] = $this->writeJson($dir, "files.json", $fileRows);
		$written[] = $this->writeJson($dir, "keyword-freq.json", $keywordFreq);
		$written[] = $this->writeCooccurJson($dir, "keyword-cooccur.json", $cooccur);
		$neighbors = Deduper::topNeighbors(5);
		$written[] = $this->writeItemsCsv($dir, "items.csv", $rows);
		$written[] = $this->writeKeywordFreqCsv($dir, "keyword-freq.csv", $keywordFreq);
		$written[] = $this->writeCooccurCsv($dir, "keyword-cooccur.csv", $cooccur);
		$written[] = $this->writeNeighborsCsv($dir, "neighbors.csv", $neighbors);
		$written[] = $this->writeJson($dir, "neighbors.json", $neighbors);

		$this->log(sprintf(
			"Exported %d items (%d distinct keywords, %d neighbor rows) → %s",
			count($rows),
			count($keywordFreq),
			count($neighbors),
			$dir,
		));

		return [
			"dir" => (string)$dir,
			"files" => $written,
			"itemCount" => count($rows),
			"keywordCount" => count($keywordFreq),
		];
	}

	/**
	 * @param list<array<string, mixed>> $rows
	 */
	private function writeJson(File $dir, string $name, array $rows): string
	{
		$path = new File($dir, $name);
		$path->set(json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");

		return (string)$path;
	}

	/**
	 * @param array<string, int> $cooccur
	 */
	private function writeCooccurJson(File $dir, string $name, array $cooccur): string
	{
		$out = [];
		foreach ($cooccur as $key => $count) {
			[$a, $b] = explode("\t", $key, 2);
			$out[] = ["a" => $a, "b" => $b, "count" => $count];
		}

		return $this->writeJson($dir, $name, $out);
	}

	/**
	 * @param list<array<string, mixed>> $rows
	 */
	private function writeItemsCsv(File $dir, string $name, array $rows): string
	{
		$path = new File($dir, $name);
		$fh = fopen((string)$path, "w");
		if (!$fh) {
			throw new \RuntimeException("Cannot write {$path}");
		}

		fputcsv($fh, ["id", "source", "sourceKey", "title", "status", "kind", "postedAt", "enrichmentVersion", "coverFileId", "duplicateOfId", "hasEmbedding", "keywords", "themes", "entities", "layoutPattern", "fileCount"]);
		foreach ($rows as $row) {
			fputcsv($fh, [
				$row["id"],
				$row["source"],
				$row["sourceKey"],
				$row["title"],
				$row["status"],
				$row["kind"],
				$row["postedAt"],
				$row["enrichmentVersion"],
				$row["coverFileId"],
				$row["duplicateOfId"],
				$row["hasEmbedding"] ? 1 : 0,
				implode("; ", $row["keywords"]),
				implode("; ", $row["themes"]),
				implode("; ", $row["entities"]),
				$row["layoutPattern"],
				$row["fileCount"],
			]);
		}
		fclose($fh);

		return (string)$path;
	}

	/**
	 * @param array<string, int> $freq
	 */
	private function writeKeywordFreqCsv(File $dir, string $name, array $freq): string
	{
		$path = new File($dir, $name);
		$fh = fopen((string)$path, "w");
		if (!$fh) {
			throw new \RuntimeException("Cannot write {$path}");
		}

		fputcsv($fh, ["keyword", "count"]);
		foreach ($freq as $keyword => $count) {
			fputcsv($fh, [$keyword, $count]);
		}
		fclose($fh);

		return (string)$path;
	}

	/**
	 * @param array<string, int> $cooccur
	 */
	private function writeCooccurCsv(File $dir, string $name, array $cooccur): string
	{
		$path = new File($dir, $name);
		$fh = fopen((string)$path, "w");
		if (!$fh) {
			throw new \RuntimeException("Cannot write {$path}");
		}

		fputcsv($fh, ["keyword_a", "keyword_b", "count"]);
		$n = 0;
		foreach ($cooccur as $key => $count) {
			if ($n++ >= 500) {
				break;
			}
			[$a, $b] = explode("\t", $key, 2);
			fputcsv($fh, [$a, $b, $count]);
		}
		fclose($fh);

		return (string)$path;
	}

	/**
	 * @param list<array{id: int, neighborId: int, score: float}> $rows
	 */
	private function writeNeighborsCsv(File $dir, string $name, array $rows): string
	{
		$path = new File($dir, $name);
		$fh = fopen((string)$path, "w");
		if (!$fh) {
			throw new \RuntimeException("Cannot write {$path}");
		}

		fputcsv($fh, ["id", "neighborId", "score"]);
		foreach ($rows as $row) {
			fputcsv($fh, [$row["id"], $row["neighborId"], $row["score"]]);
		}
		fclose($fh);

		return (string)$path;
	}

	private function log(string $message): void
	{
		foreach ($this->loggers as $logger) {
			$logger($message);
		}
	}
}
