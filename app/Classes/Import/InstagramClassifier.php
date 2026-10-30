<?php

namespace App\Classes\Import;

use App\Classes\Content\Section;

class InstagramClassifier
{
	/**
	 * @param array{account: string, caption: string, taken_at: ?int} $post
	 * @return array{section: string, title: string, excerpt: string, confidence: float, status: string}
	 */
	public function classify(array $post): array
	{
		$account = strtolower(trim((string)($post["account"] ?? "")));
		$caption = trim((string)($post["caption"] ?? ""));
		$normalized = $this->normalize($caption);

		$ritualScore = $this->score($normalized, $this->ritualPatterns());
		if ($ritualScore >= 1 && $account !== "himmlhaus") {
			return $this->result(Section::RITUALY, $caption, min(0.94, 0.62 + 0.08 * $ritualScore));
		}

		return $this->result(Section::DENIK, $caption, $account === "himmlhaus" ? 0.96 : 0.7);
	}

	/**
	 * @return array{section: string, title: string, excerpt: string, confidence: float, status: string}
	 */
	private function result(string $section, string $caption, float $confidence): array
	{
		$title = $this->titleFromCaption($caption);
		$excerpt = $this->excerptFromCaption($caption);
		$status = $confidence >= 0.75 ? "published" : "review";

		return [
			"section" => $section,
			"title" => $title,
			"excerpt" => $excerpt,
			"confidence" => round($confidence, 3),
			"status" => $status,
		];
	}

	private function titleFromCaption(string $caption): string
	{
		$caption = trim($caption);
		if ($caption === "") {
			return "Bez názvu";
		}

		$first = preg_split("/\R/u", $caption)[0] ?? $caption;
		$first = trim(preg_replace("/\s+/u", " ", $first) ?? $first);
		$first = preg_replace("/^[#@]\S+\s*/u", "", $first) ?? $first;
		if (mb_strlen($first) > 80) {
			$first = rtrim(mb_substr($first, 0, 77)) . "…";
		}

		return $first !== "" ? $first : "Bez názvu";
	}

	private function excerptFromCaption(string $caption): string
	{
		$caption = trim(preg_replace("/\s+/u", " ", $caption) ?? $caption);
		if (mb_strlen($caption) <= 280) {
			return $caption;
		}

		return rtrim(mb_substr($caption, 0, 277)) . "…";
	}

	private function normalize(string $caption): string
	{
		$caption = mb_strtolower($caption);
		$caption = strtr($caption, [
			"á" => "a", "č" => "c", "ď" => "d", "é" => "e", "ě" => "e",
			"í" => "i", "ň" => "n", "ó" => "o", "ř" => "r", "š" => "s",
			"ť" => "t", "ú" => "u", "ů" => "u", "ý" => "y", "ž" => "z",
		]);

		return $caption;
	}

	/**
	 * @param list<string> $patterns
	 */
	private function score(string $text, array $patterns): int
	{
		$score = 0;
		foreach ($patterns as $pattern) {
			if (str_contains($text, $pattern)) {
				$score++;
			}
		}

		return $score;
	}

	/**
	 * @return list<string>
	 */
	private function ritualPatterns(): array
	{
		return [
			"svatb", "nevest", "zenich", "obrad", "oddavk", "manzel",
			"prechod", "krest", "kytice", "kytka", "snouben", "par ",
			"ritual", "obradek",
		];
	}

	/**
	 * @return list<string>
	 */
	private function artPatterns(): array
	{
		return [
			"vystav", "instalac", "galerie", "happening", "performance",
			"plato", "dum umeni", "svatost", "videoklip", "objekt",
			"workshop", "festival", "scena", "site-specific",
		];
	}

	/**
	 * @return list<string>
	 */
	private function housePatterns(): array
	{
		return [
			"himml", "himmelhaus", "stredohor", "sudet", "slunovrat",
			"kupalo", "pes himmel",
		];
	}
}
