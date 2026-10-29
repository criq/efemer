<?php

namespace App\Classes\Ingest;

/**
 * Deterministic QC run by KeywordAnalyzer right after each LLM apply.
 * Auto-fixes typos/cover/roles/pattern; flags needs_rekeyword for a later KeywordIngest pass.
 */
class KeywordQc
{
	public const FLAG_NEEDS_REKEYWORD = "needs_rekeyword";

	private const ALLOWED_KINDS = [
		"diary", "wedding", "gift", "portrait",
		"workshop", "installation", "bouquet", "product", "other",
	];

	/**
	 * @return array{
	 *   item: IngestItem,
	 *   fixed: list<string>,
	 *   issues: list<string>,
	 *   needsRekeyword: bool,
	 *   changed: bool
	 * }
	 */
	public function review(IngestItem $item, bool $fix = true): array
	{
		$files = array_values($item->getFiles()->getArrayCopy());
		$fixed = [];
		$issues = [];
		$changed = false;

		$keywordsBefore = $item->getKeywordsList();
		$themesBefore = $item->getThemesList();
		$entitiesBefore = $item->getEntitiesList();

		$keywords = KeywordNormalizer::normalizeList($keywordsBefore);
		$themes = KeywordNormalizer::normalizeList($themesBefore);
		$entities = KeywordNormalizer::normalizeList($entitiesBefore, preserveCase: true);

		if ($keywords !== $keywordsBefore || $themes !== $themesBefore || $entities !== $entitiesBefore) {
			$fixed[] = "normalized item keywords/themes/entities";
			if ($fix) {
				$item->setKeywordsList($keywords);
				$item->setThemesList($themes);
				$item->setEntitiesList($entities);
				$changed = true;
			}
		}

		foreach ($files as $file) {
			$before = $file->getKeywords();
			$after = KeywordNormalizer::normalizeList($before);
			if ($after !== $before) {
				$fixed[] = "normalized file #{$file->getId()} keywords";
				if ($fix) {
					$file->setKeywords($after ?: null);
					$file->persist();
					$changed = true;
				}
			}
		}

		$kind = $item->getKind();
		if ($kind === null || $kind === "") {
			$issues[] = "missing kind";
		} elseif (!in_array($kind, self::ALLOWED_KINDS, true)) {
			$issues[] = "invalid kind={$kind}";
		}

		if ($keywords === []) {
			$issues[] = "empty keywords";
		}
		if (KeywordNormalizer::listContainsCyrillic($keywords)
			|| KeywordNormalizer::listContainsCyrillic($themes)
			|| KeywordNormalizer::listContainsCyrillic($entities)
		) {
			$issues[] = "cyrillic remains after normalize";
		}

		$roleFixed = $this->enforcePreCopyRoles($files, $fix);
		if ($roleFixed !== []) {
			$fixed = array_merge($fixed, $roleFixed);
			$changed = $changed || $fix;
		}

		$coverFixed = $this->enforceCover($item, $files, $fix);
		if ($coverFixed !== []) {
			$fixed = array_merge($fixed, $coverFixed);
			$changed = $changed || $fix;
		}

		$patternFixed = $this->enforceLayoutPattern($item, $files, $fix);
		if ($patternFixed !== []) {
			$fixed = array_merge($fixed, $patternFixed);
			$changed = $changed || $fix;
		}

		$usableFiles = array_filter(
			$files,
			fn (IngestItemFile $f) => $f->getRole() !== IngestItemFile::ROLE_SKIP && !$f->isDemoted(),
		);
		if ($files !== [] && $usableFiles !== [] && $item->getCoverFileId() === null) {
			$issues[] = "missing cover";
		}

		$needsRekeyword = $this->shouldRekeyword($issues);

		if ($fix) {
			$flagsBefore = $item->getQualityFlags();
			if ($needsRekeyword) {
				$item->addQualityFlag(self::FLAG_NEEDS_REKEYWORD);
			} else {
				$item->removeQualityFlag(self::FLAG_NEEDS_REKEYWORD);
			}
			$flagsChanged = $item->getQualityFlags() !== $flagsBefore;
			if ($changed || $flagsChanged) {
				$item->touchUpdated();
				$item->persist();
				$changed = true;
			}
		}

		return [
			"item" => $item,
			"fixed" => array_values(array_unique($fixed)),
			"issues" => $issues,
			"needsRekeyword" => $needsRekeyword,
			"changed" => $changed,
		];
	}

	/**
	 * @param list<string> $issues
	 */
	private function shouldRekeyword(array $issues): bool
	{
		foreach ($issues as $issue) {
			if (
				str_starts_with($issue, "empty keywords")
				|| str_starts_with($issue, "cyrillic")
				|| str_starts_with($issue, "missing kind")
				|| str_starts_with($issue, "invalid kind")
				|| str_starts_with($issue, "missing cover")
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param list<IngestItemFile> $files
	 * @return list<string>
	 */
	private function enforcePreCopyRoles(array $files, bool $fix): array
	{
		$fixed = [];
		foreach ($files as $file) {
			if ($file->getSourceSlot() !== IngestItemFile::SLOT_PRE_COPY) {
				continue;
			}
			if ($file->isRoleLocked() || $file->isDemoted()) {
				continue;
			}
			if ($file->getRole() === IngestItemFile::ROLE_SKIP) {
				continue;
			}
			if ($file->getRole() === IngestItemFile::ROLE_LEAD) {
				continue;
			}

			$fixed[] = "pre_copy file #{$file->getId()} role → lead";
			if ($fix) {
				$file->setRole(IngestItemFile::ROLE_LEAD);
				$file->persist();
			}
		}

		return $fixed;
	}

	/**
	 * @param list<IngestItemFile> $files
	 * @return list<string>
	 */
	private function enforceCover(IngestItem $item, array $files, bool $fix): array
	{
		if ($item->isCoverLocked() || $files === []) {
			return [];
		}

		$byId = [];
		foreach ($files as $file) {
			$byId[(int)$file->getId()] = $file;
		}

		$currentId = $item->getCoverFileId();
		$current = $currentId !== null ? ($byId[$currentId] ?? null) : null;
		$resolved = $this->resolveCover($files, $current);

		if (!$resolved) {
			return [];
		}

		if ($current && (int)$current->getId() === (int)$resolved->getId() && $current->isCover()) {
			return [];
		}

		$fixed = ["cover → file #{$resolved->getId()} ({$resolved->getSourceSlot()})"];
		if (!$fix) {
			return $fixed;
		}

		foreach ($files as $file) {
			$file->setIsCover((int)$file->getId() === (int)$resolved->getId());
			$file->persist();
		}
		$item->setCoverFileId((int)$resolved->getId());
		$rationale = trim((string)$item->getCoverRationale());
		$suffix = "(cover QC)";
		if (!str_contains($rationale, $suffix)) {
			$item->setCoverRationale(trim(($rationale !== "" ? $rationale . " " : "") . $suffix));
		}

		return $fixed;
	}

	/**
	 * @param list<IngestItemFile> $files
	 */
	private function resolveCover(array $files, ?IngestItemFile $proposed): ?IngestItemFile
	{
		$usable = static fn (IngestItemFile $f): bool => $f->getRole() !== IngestItemFile::ROLE_SKIP && !$f->isDemoted();

		$preCopy = array_values(array_filter(
			$files,
			fn (IngestItemFile $f) => $f->getSourceSlot() === IngestItemFile::SLOT_PRE_COPY && $usable($f),
		));

		if ($preCopy !== []) {
			if ($proposed && $proposed->getSourceSlot() === IngestItemFile::SLOT_PRE_COPY && $usable($proposed)) {
				return $proposed;
			}

			return $this->rankCoverCandidates($preCopy);
		}

		if ($proposed && $usable($proposed)) {
			return $proposed;
		}

		return $this->rankCoverCandidates(array_values(array_filter($files, $usable)));
	}

	/**
	 * @param list<IngestItemFile> $candidates
	 */
	private function rankCoverCandidates(array $candidates): ?IngestItemFile
	{
		if ($candidates === []) {
			return null;
		}

		usort($candidates, function (IngestItemFile $a, IngestItemFile $b) {
			$score = fn (IngestItemFile $f) => match ($f->getRole()) {
				IngestItemFile::ROLE_LEAD => 3,
				IngestItemFile::ROLE_SUPPORT => 2,
				IngestItemFile::ROLE_DETAIL => 1,
				default => 0,
			} + (($f->getRating() ?? 0) * 0.1) - ((int)($f->getPosition() ?? 99) * 0.001);

			return $score($b) <=> $score($a);
		});

		return $candidates[0];
	}

	/**
	 * @param list<IngestItemFile> $files
	 * @return list<string>
	 */
	private function enforceLayoutPattern(IngestItem $item, array $files, bool $fix): array
	{
		$body = trim(html_entity_decode(strip_tags((string)$item->getBodyHtml()), ENT_QUOTES | ENT_HTML5, "UTF-8"));
		$thinText = $item->hasQualityFlag("thin_text") || mb_strlen($body) < 80;
		$fileCount = count($files);
		$hasPreCopy = false;
		foreach ($files as $file) {
			if ($file->getSourceSlot() === IngestItemFile::SLOT_PRE_COPY) {
				$hasPreCopy = true;
				break;
			}
		}

		$hints = $item->getLayoutHints() ?? [];
		$pattern = isset($hints["pattern"]) && is_string($hints["pattern"]) ? $hints["pattern"] : "";

		$expected = $pattern;
		if ($fileCount <= 1) {
			$expected = "single";
		} elseif ($thinText) {
			$expected = "gallery_essay";
		} elseif ($hasPreCopy && $body !== "") {
			$expected = "hero_text_gallery";
		} elseif ($body !== "" && !$hasPreCopy) {
			$expected = "text_lead_gallery";
		}

		if ($expected === "" || $expected === $pattern) {
			return [];
		}

		$fixed = ["layout pattern {$pattern} → {$expected}"];
		if ($fix) {
			$hints["pattern"] = $expected;
			$item->setLayoutHints($hints);
		}

		return $fixed;
	}
}
