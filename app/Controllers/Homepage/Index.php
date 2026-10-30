<?php

namespace App\Controllers\Homepage;

use App\Classes\Content\Post;
use App\Classes\Content\Section;
use App\Classes\Ingest\IngestItemFile;
use App\Classes\Views\HTMLEngine;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Index extends \Katu\Controllers\Controller
{
	public function getResponse(ServerRequestInterface $request, ResponseInterface $response)
	{
		$sections = [];
		foreach (Section::work() as $code) {
			$sections[] = $this->sectionPlot($code);
		}

		$latest = Post::getPublished();
		$invitation = Post::getUpcomingInvitation();
		$featured = $invitation ?? ($latest[0] ?? null);

		return $response->withBody((new HTMLEngine($request))->render("Homepage/index.twig", [
			"invitation" => $invitation,
			"latestPost" => $latest[0] ?? null,
			"heroImages" => $this->heroImages($featured),
			"sections" => $sections,
		]));
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
		$plot["images"] = $this->sectionPlotImages($posts, $prefer);

		return $plot;
	}

	/**
	 * Prefer a high-rated photo with the given LLM orientation from the section’s posts.
	 *
	 * @param list<Post> $posts
	 * @return list<array{url: string, image: \Katu\Tools\Images\Image}>
	 */
	private function sectionPlotImages(array $posts, string $preferOrientation = "landscape"): array
	{
		// Prefer LLM orientation, then rating. Avoid getWidth/getHeight —
		// each call can download from GCS and made the homepage time out on production.
		$preferOrientation = strtolower($preferOrientation);
		$bestPreferred = null;
		$bestPreferredRating = -1.0;
		$bestAny = null;
		$bestAnyRating = -1.0;

		foreach ($posts as $post) {
			foreach ($post->getFiles() as $file) {
				if ($file->isDemoted() || $file->getRole() === "skip") {
					continue;
				}

				$rating = $file->getRating() ?? 0.55;
				$image = $file->getImage();
				if (!$image) {
					continue;
				}

				$candidate = [
					"url" => $post->getPublicUrl(),
					"image" => $image,
					"post" => $post,
					"file" => $file,
				];

				if ($rating > $bestAnyRating) {
					$bestAnyRating = $rating;
					$bestAny = $candidate;
				}

				if ($this->fileOrientation($file) === $preferOrientation && $rating > $bestPreferredRating) {
					$bestPreferredRating = $rating;
					$bestPreferred = $candidate;
				}
			}
		}

		$best = $bestPreferred ?? $bestAny;

		return $best ? [$best] : [];
	}

	private function fileOrientation(\App\Classes\Content\PostFile $file): ?string
	{
		$storageFileId = (int)$file->storageFileId;
		if ($storageFileId < 1) {
			return null;
		}

		$ingestFile = IngestItemFile::getOneBy([
			"storageFileId" => $storageFileId,
		]);

		$orientation = $ingestFile?->getOrientation();

		return is_string($orientation) && $orientation !== "" ? strtolower($orientation) : null;
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
