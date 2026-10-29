<?php

namespace App\Controllers\Homepage;

use App\Classes\Content\Post;
use App\Classes\Content\Section;
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
		$plot["images"] = $this->sectionPlotImages($posts);

		return $plot;
	}

	/**
	 * Prefer a landscape, high-rated photo from the section’s posts.
	 *
	 * @param list<Post> $posts
	 * @return list<array{url: string, image: \Katu\Tools\Images\Image}>
	 */
	private function sectionPlotImages(array $posts): array
	{
		$best = null;
		$bestScore = -1.0;

		foreach ($posts as $post) {
			foreach ($post->getFiles() as $file) {
				if ($file->isDemoted() || $file->getRole() === "skip") {
					continue;
				}

				$image = $file->getImage();
				if (!$image) {
					continue;
				}

				try {
					$width = (float)$image->getWidth();
					$height = (float)$image->getHeight();
				} catch (\Throwable) {
					continue;
				}
				if ($height <= 0) {
					continue;
				}

				$ratio = $width / $height;
				$landscape = $ratio >= 1.15 ? 1.0 : ($ratio >= 1.0 ? 0.35 : 0.0);
				$rating = $file->getRating() ?? 0.55;
				$score = ($landscape * 2.0) + $rating;
				if ($score <= $bestScore) {
					continue;
				}

				$bestScore = $score;
				$best = [
					"url" => $post->getPublicUrl(),
					"image" => $image,
					"post" => $post,
					"file" => $file,
				];
			}
		}

		return $best ? [$best] : [];
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
