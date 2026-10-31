<?php

namespace App\Controllers\Posts;

use App\Classes\Content\Post;
use App\Classes\Content\RitualyFeedBanner;
use App\Classes\Content\Section;
use App\Classes\Views\HTMLEngine;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class View extends \Katu\Controllers\Controller
{
	private const FEED_PAGE_SIZE = 4;

	public function ritualy(ServerRequestInterface $request, ResponseInterface $response, string $slug)
	{
		return $this->render($request, $response, Section::RITUALY, $slug);
	}

	public function umeni(ServerRequestInterface $request, ResponseInterface $response, string $slug)
	{
		return $this->render($request, $response, Section::UMENI, $slug);
	}

	public function denik(ServerRequestInterface $request, ResponseInterface $response, string $slug)
	{
		return $this->render($request, $response, Section::DENIK, $slug);
	}

	private function render(ServerRequestInterface $request, ResponseInterface $response, string $section, string $slug): ResponseInterface
	{
		$path = Section::pathPrefix($section) . "/" . ltrim($slug, "/");
		$post = Post::getByPath($path);
		if (!$post || !$post->isPubliclyVisible()) {
			throw new \Katu\Exceptions\ModelNotFoundException;
		}

		$params = $request->getQueryParams();
		$partial = (string)($params["partial"] ?? "") === "1";
		$afterId = (int)($params["after"] ?? $post->getId());
		if ($afterId < 1) {
			$afterId = (int)$post->getId();
		}

		$peek = $this->postsAfter($section, $afterId, self::FEED_PAGE_SIZE + 1);
		$hasMore = count($peek) > self::FEED_PAGE_SIZE;
		$continuePosts = array_slice($peek, 0, self::FEED_PAGE_SIZE);

		$feedMeta = $this->feedMeta($section);
		$showRitualyBanner = $section !== Section::RITUALY;
		$bannerPage = $this->bannerPageAfter($section, $afterId);
		$ritualyBannerImages = $showRitualyBanner ? RitualyFeedBanner::imagesForPage($bannerPage) : [];
		$engine = new HTMLEngine($request);
		$nextUrl = null;
		if ($hasMore && $continuePosts !== []) {
			$lastId = (int)$continuePosts[array_key_last($continuePosts)]->getId();
			$nextUrl = rtrim($post->getPublicUrl(), "/") . "?partial=1&after={$lastId}";
		}

		$feedVars = [
			"posts" => $continuePosts,
			"showRitualyBanner" => $showRitualyBanner,
			"ritualyBannerImages" => $ritualyBannerImages,
			"feedPageSize" => self::FEED_PAGE_SIZE,
		];

		if ($partial) {
			$html = $engine->render($feedMeta["partialTemplate"], $feedVars);
			$response = $response
				->withHeader("Content-Type", "text/html; charset=UTF-8")
				->withHeader("Cache-Control", "no-store");
			if ($nextUrl !== null) {
				$response = $response->withHeader("X-Feed-Next", $nextUrl);
			}

			return $response->withBody($html);
		}

		return $response->withBody($engine->render("Posts/article.twig", array_merge([
			"post" => $post,
			"sectionTitle" => Section::title($post->getSection()),
			"sectionPath" => Section::pathPrefix($post->getSection()),
			"parts" => $post->getResolvedParts(),
			"sectionBodyClass" => $feedMeta["bodyClass"],
			"feedClass" => $feedMeta["feedClass"],
			"itemTemplate" => $feedMeta["itemTemplate"],
			"continuePosts" => $continuePosts,
			"feedHasMore" => $hasMore,
			"feedNextUrl" => $nextUrl,
		], $feedVars)));
	}

	private function bannerPageAfter(string $section, int $afterId): int
	{
		$all = Post::getPublishedBySection($section);
		foreach ($all as $i => $candidate) {
			if ((int)$candidate->getId() === $afterId) {
				return (int)floor(($i + 1) / self::FEED_PAGE_SIZE) + 1;
			}
		}

		return 1;
	}

	/**
	 * @return list<Post>
	 */
	private function postsAfter(string $section, int $afterId, int $limit): array
	{
		$all = Post::getPublishedBySection($section);
		$start = null;
		foreach ($all as $i => $candidate) {
			if ((int)$candidate->getId() === $afterId) {
				$start = $i + 1;
				break;
			}
		}
		if ($start === null) {
			return [];
		}

		return array_values(array_slice($all, $start, $limit));
	}

	/**
	 * @return array{bodyClass: string, feedClass: string, itemTemplate: string, partialTemplate: string}
	 */
	private function feedMeta(string $section): array
	{
		return match ($section) {
			Section::RITUALY => [
				"bodyClass" => "site--ritualy",
				"feedClass" => "ritualy",
				"itemTemplate" => "Sections/ritualy-item.twig",
				"partialTemplate" => "Sections/ritualy-feed.twig",
			],
			Section::UMENI => [
				"bodyClass" => "site--umeni",
				"feedClass" => "umeni",
				"itemTemplate" => "Sections/umeni-item.twig",
				"partialTemplate" => "Sections/umeni-feed.twig",
			],
			default => [
				"bodyClass" => "site--denik",
				"feedClass" => "denik",
				"itemTemplate" => "Sections/denik-item.twig",
				"partialTemplate" => "Sections/denik-feed.twig",
			],
		};
	}
}
