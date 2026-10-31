<?php

namespace App\Controllers\Homepage;

use App\Classes\Content\Post;
use App\Classes\Content\Section;
use App\Classes\Views\HTMLEngine;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Index extends \Katu\Controllers\Controller
{
	private const FEED_PAGE_SIZE = 3;

	public function getResponse(ServerRequestInterface $request, ResponseInterface $response)
	{
		$params = $request->getQueryParams();
		$page = max(1, (int)($params["page"] ?? 1));
		$partial = (string)($params["partial"] ?? "") === "1";

		$all = Post::getPublished();
		$total = count($all);
		$offset = ($page - 1) * self::FEED_PAGE_SIZE;
		$feedPosts = array_slice($all, $offset, self::FEED_PAGE_SIZE);
		$hasMore = ($offset + count($feedPosts)) < $total;
		$nextPage = $hasMore ? $page + 1 : null;

		$engine = new HTMLEngine($request);

		$ritualyBannerImages = $this->ritualyBannerImages($page);

		if ($partial) {
			$html = $engine->render("Homepage/feed.twig", [
				"posts" => $feedPosts,
				"ritualyBannerImages" => $ritualyBannerImages,
			]);
			$response = $response
				->withHeader("Content-Type", "text/html; charset=UTF-8")
				->withHeader("Cache-Control", "no-store");
			if ($nextPage !== null) {
				$nextUrl = (string)\Katu\Tools\Routing\URL::getFor("homepage.index") . "?page={$nextPage}&partial=1";
				$response = $response->withHeader("X-Feed-Next", $nextUrl);
			}

			return $response->withBody($html);
		}

		$sections = [];
		foreach (Section::work() as $code) {
			$sections[] = $this->sectionPlot($code);
		}

		$invitation = Post::getUpcomingInvitation();
		$featured = $invitation ?? ($all[0] ?? null);

		return $response->withBody($engine->render("Homepage/index.twig", [
			"invitation" => $invitation,
			"latestPost" => $all[0] ?? null,
			"heroImages" => $this->heroImages($featured),
			"sections" => $sections,
			"feedPosts" => $feedPosts,
			"feedHasMore" => $hasMore,
			"feedNextPage" => $nextPage,
			"ritualyBannerImages" => $ritualyBannerImages,
		]));
	}

	/**
	 * Portrait-leaning rituály photos for the feed interlude banner.
	 * Each page gets a distinct 4-photo window (step 4 through a larger pool).
	 *
	 * @return list<array{url: string, image: \Katu\Tools\Images\Image}>
	 */
	private function ritualyBannerImages(int $page = 1): array
	{
		$pool = $this->sectionPlotImages(
			Post::getPublishedBySection(Section::RITUALY),
			"portrait",
			24,
		);
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

		// Alternate mirrored order so consecutive banners feel different even on a short pool.
		if ($page % 2 === 0) {
			$picked = array_reverse($picked);
		}

		return $picked;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function sectionPlot(string $code): array
	{
		$plot = [
			"code" => $code,
			"title" => Section::title($code),
			"navLabel" => Section::navLabel($code),
			"lead" => Section::lead($code),
			"path" => Section::pathPrefix($code),
			"url" => (string)\Katu\Tools\Routing\URL::getFor(Section::routeName($code)),
		];

		$posts = Post::getPublishedBySection($code);
		$prefer = $code === Section::RITUALY ? "portrait" : "landscape";
		$limit = $code === Section::RITUALY ? 2 : 1;
		$plot["images"] = $this->sectionPlotImages($posts, $prefer, $limit);

		return $plot;
	}

	/**
	 * Prefer high-rated photos with the given LLM orientation from the section’s posts.
	 * Scans recent posts only and defers StorageFile loads until after ranking —
	 * a full-section file+ingest scan was ~3s of homepage TTFB on production.
	 *
	 * @param list<Post> $posts
	 * @return list<array{url: string, image: \Katu\Tools\Images\Image}>
	 */
	private function sectionPlotImages(array $posts, string $preferOrientation = "landscape", int $limit = 1): array
	{
		// Prefer LLM orientation, then rating. Never call getWidth/getHeight here
		// (GCS downloads) and avoid per-file ingest lookups (see post_files.orientation).
		$preferOrientation = strtolower($preferOrientation);
		$limit = max(1, $limit);
		// Small plot needs (1–2) only need a recent window; banner pools scan the section.
		$maxPosts = $limit <= 4 ? 30 : count($posts);
		$posts = array_slice($posts, 0, $maxPosts);
		$candidates = [];
		$preferredCount = 0;

		foreach ($posts as $post) {
			foreach ($post->getFiles() as $file) {
				if ($file->isDemoted() || $file->getRole() === "skip") {
					continue;
				}

				$preferred = $file->getOrientation() === $preferOrientation;
				$candidates[] = [
					"url" => $post->getPublicUrl(),
					"post" => $post,
					"file" => $file,
					"rating" => $file->getRating() ?? 0.55,
					"preferred" => $preferred,
				];
				if ($preferred) {
					$preferredCount++;
				}
			}

			if ($limit <= 4 && $preferredCount >= $limit * 3) {
				break;
			}
		}

		if ($candidates === []) {
			return [];
		}

		usort($candidates, function (array $a, array $b) {
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

	/**
	 * Collage slots from one featured post only (cover lead + best-rated rest).
	 *
	 * @return list<array{url: string, image: \Katu\Tools\Images\Image}>
	 */
	private function heroImages(?Post $featured): array
	{
		if (!$featured) {
			return [];
		}

		$images = [];
		foreach ($featured->getCollageFiles(5) as $file) {
			$image = $file->getImage();
			if (!$image) {
				continue;
			}

			$images[] = [
				"url" => $featured->getPublicUrl(),
				"image" => $image,
				"post" => $featured,
				"file" => $file,
			];
		}

		return $images;
	}

	/**
	 * @param list<Post> $posts
	 * @return list<array{url: string, image: \Katu\Tools\Images\Image}>
	 */
	private function imagesFromPosts(array $posts, int $limit): array
	{
		$images = [];
		foreach ($posts as $post) {
			foreach ($post->getFiles() as $file) {
				$image = $file->getImage();
				if (!$image) {
					continue;
				}

				$images[] = [
					"url" => $post->getPublicUrl(),
					"image" => $image,
					"post" => $post,
					"file" => $file,
				];

				if (count($images) >= $limit) {
					return $images;
				}
			}
		}

		return $images;
	}
}
