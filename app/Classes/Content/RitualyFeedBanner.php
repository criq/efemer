<?php

namespace App\Classes\Content;

/**
 * Dark rituály interlude for infinite feeds (homepage, deník, umění, post continues).
 */
class RitualyFeedBanner
{
	/**
	 * Portrait-leaning rituály photos for one banner slot.
	 * Each page gets a distinct 4-photo window (step 4 through a larger pool).
	 *
	 * @return list<array{url: string, image: \Katu\Tools\Images\Image}>
	 */
	public static function imagesForPage(int $page = 1): array
	{
		$page = max(1, $page);
		$pool = self::portraitPool(24);
		if ($pool === []) {
			return [];
		}

		$count = count($pool);
		$take = min(4, $count);
		$offset = (($page - 1) * $take) % $count;
		$picked = [];
		for ($i = 0; $i < $take; $i++) {
			$picked[] = $pool[($offset + $i) % $count];
		}

		if ($page % 2 === 0) {
			$picked = array_reverse($picked);
		}

		return $picked;
	}

	/**
	 * @return list<array{url: string, image: \Katu\Tools\Images\Image}>
	 */
	private static function portraitPool(int $limit): array
	{
		$limit = max(1, $limit);
		$posts = Post::getPublishedBySection(Section::RITUALY);
		$candidates = [];
		$preferredCount = 0;

		foreach ($posts as $post) {
			foreach ($post->getFiles() as $file) {
				if ($file->isDemoted() || $file->getRole() === "skip") {
					continue;
				}

				$preferred = $file->getOrientation() === "portrait";
				$candidates[] = [
					"url" => $post->getPublicUrl(),
					"file" => $file,
					"rating" => $file->getRating() ?? 0.55,
					"preferred" => $preferred,
				];
				if ($preferred) {
					$preferredCount++;
				}
			}

			if ($preferredCount >= $limit) {
				break;
			}
		}

		if ($candidates === []) {
			return [];
		}

		usort($candidates, static function (array $a, array $b): int {
			if ($a["preferred"] !== $b["preferred"]) {
				return $a["preferred"] ? -1 : 1;
			}

			return $b["rating"] <=> $a["rating"];
		});

		$picked = [];
		$seen = [];
		foreach ($candidates as $candidate) {
			$key = (int)$candidate["file"]->storageFileId;
			if ($key < 1 || isset($seen[$key])) {
				continue;
			}

			$image = $candidate["file"]->getImage();
			if (!$image) {
				continue;
			}

			$seen[$key] = true;
			$picked[] = [
				"url" => $candidate["url"],
				"image" => $image,
			];
			if (count($picked) >= $limit) {
				break;
			}
		}

		return $picked;
	}
}
