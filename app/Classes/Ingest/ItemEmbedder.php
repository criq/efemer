<?php

namespace App\Classes\Ingest;

use App\Classes\Jobs\ConsoleStyle;
use App\Classes\ThirdParty\Google\Gemini;

class ItemEmbedder
{
	private const MAX_CHARS = 6000;

	private Gemini $gemini;

	/** @var list<callable(string): void> */
	private array $loggers = [];

	public function __construct(?Gemini $gemini = null)
	{
		$this->gemini = $gemini ?? new Gemini;
	}

	public function onLog(callable $logger): self
	{
		$this->loggers[] = $logger;

		return $this;
	}

	/**
	 * Build embed text, call Gemini, persist (unless dryRun).
	 *
	 * @return list<float>|null null when skipped (empty text)
	 */
	public function embed(IngestItem $item, bool $dryRun = false): ?array
	{
		$text = $this->buildText($item);
		if ($text === "") {
			$this->log(ConsoleStyle::skipped(
				$item->getSource() . "/" . $item->getSourceKey(),
				"empty text",
			));

			return null;
		}

		$vector = $this->gemini->embedText($text);
		if ($dryRun) {
			$this->log(ConsoleStyle::ok("Dry-run embed") . " #" . $item->getId()
				. " dims=" . count($vector));

			return $vector;
		}

		$item->setEmbedding($vector);
		// Fresh vector invalidates prior soft-dupe links until DedupIngest re-runs.
		$item->setDuplicateOfId(null);
		$item->persist();

		$this->log(ConsoleStyle::ok("Embedded") . " #" . $item->getId()
			. " dims=" . count($vector));

		return $vector;
	}

	public function buildText(IngestItem $item): string
	{
		$parts = [];
		$title = trim((string)$item->getTitle());
		if ($title !== "") {
			$parts[] = $title;
		}

		$keywords = $item->getKeywordsList();
		if ($keywords !== []) {
			$parts[] = "Keywords: " . implode(", ", $keywords);
		}

		$body = trim(html_entity_decode(strip_tags((string)($item->getBodyHtml() ?? "")), ENT_QUOTES | ENT_HTML5, "UTF-8"));
		$body = preg_replace("/\s+/u", " ", $body) ?? $body;
		$body = trim($body);
		if ($body !== "") {
			$parts[] = $body;
		}

		$text = implode("\n\n", $parts);
		if (mb_strlen($text) > self::MAX_CHARS) {
			$text = mb_substr($text, 0, self::MAX_CHARS);
		}

		return trim($text);
	}

	private function log(string $message): void
	{
		foreach ($this->loggers as $logger) {
			$logger($message);
		}
	}
}
