<?php

namespace App\Classes\Ingest;

use App\Classes\Jobs\ConsoleStyle;

class Deduper
{
	public const DEFAULT_THRESHOLD = 0.92;

	/** @var list<callable(string): void> */
	private array $loggers = [];

	public function onLog(callable $logger): self
	{
		$this->loggers[] = $logger;

		return $this;
	}

	/**
	 * Soft-link near-duplicates via duplicateOfId (points at the older item).
	 *
	 * @return array{linked: int, cleared: int, compared: int}
	 */
	public function run(float $threshold = self::DEFAULT_THRESHOLD, bool $dryRun = false): array
	{
		$threshold = max(0.0, min(1.0, $threshold));
		$items = [];
		foreach (IngestItem::getAll()->getItems() as $item) {
			$embedding = $item->getEmbedding();
			if ($embedding === null) {
				continue;
			}
			$items[] = [
				"item" => $item,
				"id" => (int)$item->getId(),
				"embedding" => $embedding,
				"postedAt" => (int)($item->getPostedAt()?->format("U") ?? 0),
			];
		}

		usort($items, static function (array $a, array $b): int {
			if ($a["postedAt"] !== $b["postedAt"]) {
				return $a["postedAt"] <=> $b["postedAt"];
			}

			return $a["id"] <=> $b["id"];
		});

		$cleared = 0;
		if (!$dryRun) {
			foreach (IngestItem::getAll()->getItems() as $item) {
				if ($item->getDuplicateOfId() !== null) {
					$item->setDuplicateOfId(null);
					$item->persist();
					$cleared++;
				}
			}
		}

		$n = count($items);
		$best = []; // youngerId => ["olderId" => int, "score" => float]
		$compared = 0;

		for ($i = 0; $i < $n; $i++) {
			for ($j = $i + 1; $j < $n; $j++) {
				$compared++;
				$score = self::cosine($items[$i]["embedding"], $items[$j]["embedding"]);
				if ($score < $threshold) {
					continue;
				}

				// $i is older (or equal age with smaller id); $j is the duplicate.
				$olderId = $items[$i]["id"];
				$youngerId = $items[$j]["id"];
				if (!isset($best[$youngerId]) || $score > $best[$youngerId]["score"]) {
					$best[$youngerId] = [
						"olderId" => $olderId,
						"score" => $score,
					];
				}
			}
		}

		$linked = 0;
		foreach ($best as $youngerId => $row) {
			$linked++;
			$this->log(sprintf(
				"%s #%d → #%d (%.3f)",
				$dryRun ? ConsoleStyle::ok("Would link") : ConsoleStyle::ok("Linked"),
				$youngerId,
				$row["olderId"],
				$row["score"],
			));
			if ($dryRun) {
				continue;
			}
			$younger = IngestItem::get($youngerId);
			if (!$younger) {
				continue;
			}
			$younger->setDuplicateOfId((int)$row["olderId"]);
			$younger->persist();
		}

		$this->log(sprintf(
			"Deduper: compared %d pairs, linked %d, cleared %d (threshold %.2f)%s.",
			$compared,
			$linked,
			$cleared,
			$threshold,
			$dryRun ? ", dryRun" : "",
		));

		return [
			"linked" => $linked,
			"cleared" => $cleared,
			"compared" => $compared,
		];
	}

	/**
	 * Top-N neighbors for every embedded item (excluding self).
	 *
	 * @return list<array{id: int, neighborId: int, score: float}>
	 */
	public static function topNeighbors(int $topN = 5): array
	{
		$topN = max(1, $topN);
		$items = [];
		foreach (IngestItem::getAll()->getItems() as $item) {
			$embedding = $item->getEmbedding();
			if ($embedding === null) {
				continue;
			}
			$items[] = [
				"id" => (int)$item->getId(),
				"embedding" => $embedding,
			];
		}

		$rows = [];
		$n = count($items);
		for ($i = 0; $i < $n; $i++) {
			$scores = [];
			for ($j = 0; $j < $n; $j++) {
				if ($i === $j) {
					continue;
				}
				$scores[] = [
					"neighborId" => $items[$j]["id"],
					"score" => self::cosine($items[$i]["embedding"], $items[$j]["embedding"]),
				];
			}
			usort($scores, static fn (array $a, array $b): int => $b["score"] <=> $a["score"]);
			foreach (array_slice($scores, 0, $topN) as $neighbor) {
				$rows[] = [
					"id" => $items[$i]["id"],
					"neighborId" => $neighbor["neighborId"],
					"score" => round($neighbor["score"], 6),
				];
			}
		}

		return $rows;
	}

	/**
	 * @param list<float> $a
	 * @param list<float> $b
	 */
	public static function cosine(array $a, array $b): float
	{
		$len = min(count($a), count($b));
		if ($len < 1) {
			return 0.0;
		}

		$dot = 0.0;
		$normA = 0.0;
		$normB = 0.0;
		for ($i = 0; $i < $len; $i++) {
			$dot += $a[$i] * $b[$i];
			$normA += $a[$i] * $a[$i];
			$normB += $b[$i] * $b[$i];
		}

		if ($normA <= 0.0 || $normB <= 0.0) {
			return 0.0;
		}

		return $dot / (sqrt($normA) * sqrt($normB));
	}

	private function log(string $message): void
	{
		foreach ($this->loggers as $logger) {
			$logger($message);
		}
	}
}
