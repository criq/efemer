<?php

namespace App\Controllers\Admin\Ingest;

use App\Classes\Ingest\IngestItem;
use App\Classes\Views\HTMLEngine;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Index extends \Katu\Controllers\Controller
{
	private const PER_PAGE = 50;

	public function getResponse(ServerRequestInterface $request, ResponseInterface $response)
	{
		$params = $request->getQueryParams();
		$sourceFilter = (string)($params["source"] ?? "");
		$statusFilter = (string)($params["status"] ?? "");
		$accountFilter = (string)($params["account"] ?? "");
		$kindFilter = (string)($params["kind"] ?? "");
		$q = trim((string)($params["q"] ?? ""));
		$page = max(1, (int)($params["page"] ?? 1));

		$allItems = IngestItem::getAll()->getItems();
		$pipeline = $this->buildPipeline($allItems);
		$kinds = $this->collectKinds($allItems);

		$items = $allItems;
		usort($items, function (IngestItem $a, IngestItem $b) {
			return ((int)($b->getPostedAt()?->format("U") ?? 0)) <=> ((int)($a->getPostedAt()?->format("U") ?? 0));
		});

		if ($sourceFilter !== "") {
			$items = array_values(array_filter($items, fn (IngestItem $item) => $item->getSource() === $sourceFilter));
		}
		if ($statusFilter === "stale") {
			$items = array_values(array_filter(
				$items,
				fn (IngestItem $item) => $item->getStatus() === IngestItem::STATUS_KEYWORDED
					&& $item->getEnrichmentVersion() !== IngestItem::ENRICHMENT_VERSION,
			));
		} elseif ($statusFilter !== "") {
			$items = array_values(array_filter($items, fn (IngestItem $item) => $item->getStatus() === $statusFilter));
		}
		if ($accountFilter !== "") {
			$items = array_values(array_filter($items, fn (IngestItem $item) => $item->getInstagramAccount() === $accountFilter));
		}
		if ($kindFilter !== "") {
			$items = array_values(array_filter($items, fn (IngestItem $item) => ($item->getKind() ?? "") === $kindFilter));
		}
		if ($q !== "") {
			$needle = mb_strtolower($q);
			$items = array_values(array_filter($items, function (IngestItem $item) use ($needle) {
				$haystacks = array_merge(
					[
						(string)$item->getTitle(),
						(string)$item->getSourceKey(),
						(string)$item->getKind(),
					],
					$item->getKeywordsList(),
					$item->getThemesList(),
					$item->getEntitiesList(),
				);
				foreach ($haystacks as $hay) {
					if (mb_strpos(mb_strtolower($hay), $needle) !== false) {
						return true;
					}
				}

				return false;
			}));
		}

		$totalFiltered = count($items);
		$totalPages = max(1, (int)ceil($totalFiltered / self::PER_PAGE));
		if ($page > $totalPages) {
			$page = $totalPages;
		}
		$offset = ($page - 1) * self::PER_PAGE;
		$pageItems = array_slice($items, $offset, self::PER_PAGE);

		$rows = [];
		foreach ($pageItems as $item) {
			$rows[] = [
				"item" => $item,
				"enrichmentVersion" => $item->getEnrichmentVersion(),
				"enrichedAt" => $item->getEnrichedAt(),
				"isStale" => $item->getStatus() === IngestItem::STATUS_KEYWORDED
					&& $item->getEnrichmentVersion() !== IngestItem::ENRICHMENT_VERSION,
				"hasCover" => $item->getCoverFileId() !== null,
				"keywordCount" => count($item->getKeywordsList()),
				"kind" => $item->getKind(),
			];
		}

		$filterQuery = $this->buildQuery([
			"source" => $sourceFilter,
			"status" => $statusFilter,
			"account" => $accountFilter,
			"kind" => $kindFilter,
			"q" => $q,
		]);

		return $response->withBody((new HTMLEngine($request))->render("Admin/Ingest/index.twig", [
			"rows" => $rows,
			"pipeline" => $pipeline,
			"kinds" => $kinds,
			"enrichmentVersion" => IngestItem::ENRICHMENT_VERSION,
			"sourceFilter" => $sourceFilter,
			"statusFilter" => $statusFilter,
			"accountFilter" => $accountFilter,
			"kindFilter" => $kindFilter,
			"q" => $q,
			"filterQuery" => $filterQuery,
			"pagination" => [
				"page" => $page,
				"perPage" => self::PER_PAGE,
				"total" => $totalFiltered,
				"totalPages" => $totalPages,
				"from" => $totalFiltered === 0 ? 0 : $offset + 1,
				"to" => min($offset + self::PER_PAGE, $totalFiltered),
				"hasPrev" => $page > 1,
				"hasNext" => $page < $totalPages,
				"prevPage" => max(1, $page - 1),
				"nextPage" => min($totalPages, $page + 1),
				"pages" => $this->pageWindow($page, $totalPages),
			],
		]));
	}

	/**
	 * @param array<string, string> $params
	 */
	private function buildQuery(array $params): string
	{
		$parts = [];
		foreach ($params as $key => $value) {
			if ($value === "") {
				continue;
			}
			$parts[] = rawurlencode($key) . "=" . rawurlencode($value);
		}

		return implode("&", $parts);
	}

	/**
	 * @return list<int|null> null = ellipsis gap
	 */
	private function pageWindow(int $page, int $totalPages): array
	{
		if ($totalPages <= 7) {
			return range(1, $totalPages);
		}

		$pages = [1];
		$start = max(2, $page - 1);
		$end = min($totalPages - 1, $page + 1);

		if ($start > 2) {
			$pages[] = null;
		}
		for ($i = $start; $i <= $end; $i++) {
			$pages[] = $i;
		}
		if ($end < $totalPages - 1) {
			$pages[] = null;
		}
		$pages[] = $totalPages;

		return $pages;
	}

	/**
	 * @param list<IngestItem> $items
	 * @return list<array{kind: string, count: int}>
	 */
	private function collectKinds(array $items): array
	{
		$counts = [];
		foreach ($items as $item) {
			$kind = $item->getKind();
			if ($kind === null || $kind === "") {
				continue;
			}
			$counts[$kind] = ($counts[$kind] ?? 0) + 1;
		}
		arsort($counts);
		$out = [];
		foreach ($counts as $kind => $count) {
			$out[] = ["kind" => $kind, "count" => $count];
		}

		return $out;
	}

	/**
	 * @param list<IngestItem> $items
	 * @return array{
	 *   total: int,
	 *   byStatus: array<string, int>,
	 *   bySource: array<string, int>,
	 *   stale: int,
	 *   stages: list<array{code: string, label: string, count: int}>,
	 *   skipped: int
	 * }
	 */
	private function buildPipeline(array $items): array
	{
		$byStatus = [
			IngestItem::STATUS_LOADED => 0,
			IngestItem::STATUS_KEYWORDED => 0,
			IngestItem::STATUS_CATEGORIZED => 0,
			IngestItem::STATUS_MATERIALIZED => 0,
			IngestItem::STATUS_SKIPPED => 0,
		];
		$bySource = [];
		$stale = 0;

		foreach ($items as $item) {
			$status = $item->getStatus();
			if (!isset($byStatus[$status])) {
				$byStatus[$status] = 0;
			}
			$byStatus[$status]++;

			$label = $item->getSourceLabel();
			$bySource[$label] = ($bySource[$label] ?? 0) + 1;

			if (
				$status === IngestItem::STATUS_KEYWORDED
				&& $item->getEnrichmentVersion() !== IngestItem::ENRICHMENT_VERSION
			) {
				$stale++;
			}
		}

		$stages = [
			["code" => IngestItem::STATUS_LOADED, "label" => "Načteno", "count" => $byStatus[IngestItem::STATUS_LOADED]],
			["code" => IngestItem::STATUS_KEYWORDED, "label" => "LLM", "count" => $byStatus[IngestItem::STATUS_KEYWORDED]],
			["code" => IngestItem::STATUS_CATEGORIZED, "label" => "Kategorie", "count" => $byStatus[IngestItem::STATUS_CATEGORIZED]],
			["code" => IngestItem::STATUS_MATERIALIZED, "label" => "Příspěvky", "count" => $byStatus[IngestItem::STATUS_MATERIALIZED]],
		];

		return [
			"total" => count($items),
			"byStatus" => $byStatus,
			"bySource" => $bySource,
			"stale" => $stale,
			"stages" => $stages,
			"skipped" => $byStatus[IngestItem::STATUS_SKIPPED],
		];
	}
}
