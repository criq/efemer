<?php

namespace App\Controllers\Homepage;

use App\Classes\Content\Event;
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
		$event = Event::getUpcoming();

		return $response->withBody((new HTMLEngine($request))->render("Homepage/index.twig", [
			"event" => $event,
			"latestPost" => $latest[0] ?? null,
			"heroImages" => $this->heroImages($event, $latest[0] ?? null),
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
		$plot["images"] = $this->imagesFromPosts($posts, 1);

		return $plot;
	}

	/**
	 * @return list<array{url: string, image: \Katu\Tools\Images\Image}>
	 */
	private function heroImages(?Event $event, ?Post $post): array
	{
		$images = [];
		if ($event && $event->getHeroImage()) {
			$images[] = [
				"url" => $event->getPublicUrl(),
				"image" => $event->getHeroImage(),
			];
		}

		// Mock: small left, two small right, large right.
		// Čajový dýchánek, Zahrádkářská kolonie, Advent a Vánoce, Svatba Mlhových.
		foreach ([102, 27, 92, 78] as $postId) {
			$mock = Post::get($postId);
			$image = $mock?->getHeroImage();
			if (!$mock || !$image) {
				continue;
			}

			$images[] = [
				"url" => $mock->getPublicUrl(),
				"image" => $image,
			];
		}

		if (count($images) < 5 && $post) {
			foreach ($this->imagesFromPosts([$post], 5) as $image) {
				$images[] = $image;
				if (count($images) >= 5) {
					break;
				}
			}
		}

		return array_slice($images, 0, 5);
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
