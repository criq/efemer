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

		if ($account === "himmlhaus") {
			return $this->result(Section::HIMMELHAUS, $caption, 0.96);
		}

		$ritualScore = $this->score($normalized, $this->ritualPatterns());
		$artScore = $this->score($normalized, $this->artPatterns());
		$houseScore = $this->score($normalized, $this->housePatterns());

		if ($houseScore >= 2 && $houseScore >= $ritualScore && $houseScore >= $artScore) {
			return $this->result(Section::HIMMELHAUS, $caption, min(0.93, 0.7 + 0.05 * $houseScore));
		}

		if ($ritualScore > $artScore && $ritualScore >= 1) {
			$confidence = min(0.94, 0.62 + 0.08 * $ritualScore);

			return $this->result(Section::RITUALY, $caption, $confidence);
		}

		if ($artScore >= 1) {
			return $this->result(Section::UMENI, $caption, min(0.92, 0.6 + 0.08 * $artScore));
		}

		return $this->result(Section::UMENI, $caption, 0.55);
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
