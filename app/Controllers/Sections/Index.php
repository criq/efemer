<?php

namespace App\Controllers\Sections;

use App\Classes\Content\Post;
use App\Classes\Content\RitualyFeedBanner;
use App\Classes\Content\Section;
use App\Classes\Views\HTMLEngine;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Index extends \Katu\Controllers\Controller
{
	private const FEED_PAGE_SIZE = 4;

	public function ritualy(ServerRequestInterface $request, ResponseInterface $response)
	{
		return $this->renderFeed(
			$request,
			$response,
			Section::RITUALY,
			"Sections/ritualy.twig",
			"Sections/ritualy-feed.twig",
			"sections.ritualy",
			[
				"blurb" => Section::blurb(Section::RITUALY),
			],
		);
	}

	public function umeni(ServerRequestInterface $request, ResponseInterface $response)
	{
		return $this->renderFeed(
			$request,
			$response,
			Section::UMENI,
			"Sections/umeni.twig",
			"Sections/umeni-feed.twig",
			"sections.umeni",
		);
	}

	public function denik(ServerRequestInterface $request, ResponseInterface $response)
	{
		return $this->renderFeed(
			$request,
			$response,
			Section::DENIK,
			"Sections/denik.twig",
			"Sections/denik-feed.twig",
			"sections.denik",
		);
	}

	/**
	 * @param array<string, mixed> $extra
	 */
	private function renderFeed(
		ServerRequestInterface $request,
		ResponseInterface $response,
		string $section,
		string $pageTemplate,
		string $partialTemplate,
		string $routeName,
		array $extra = [],
	): ResponseInterface {
		$params = $request->getQueryParams();
		$page = max(1, (int)($params["page"] ?? 1));
		$partial = (string)($params["partial"] ?? "") === "1";

		$all = Post::getPublishedBySection($section);
		$total = count($all);
		$offset = ($page - 1) * self::FEED_PAGE_SIZE;
		$posts = array_slice($all, $offset, self::FEED_PAGE_SIZE);
		$hasMore = ($offset + count($posts)) < $total;
		$nextPage = $hasMore ? $page + 1 : null;

		// Promo banner on every feed except rituály itself (already there).
		$showRitualyBanner = $section !== Section::RITUALY;
		$ritualyBannerImages = $showRitualyBanner ? RitualyFeedBanner::imagesForPage($page) : [];

		$engine = new HTMLEngine($request);
		$feedVars = [
			"posts" => $posts,
			"showRitualyBanner" => $showRitualyBanner,
			"ritualyBannerImages" => $ritualyBannerImages,
			"feedPageSize" => self::FEED_PAGE_SIZE,
		];

		if ($partial) {
			$html = $engine->render($partialTemplate, $feedVars);
			$response = $response
				->withHeader("Content-Type", "text/html; charset=UTF-8")
				->withHeader("Cache-Control", "no-store");
			if ($nextPage !== null) {
				$nextUrl = (string)\Katu\Tools\Routing\URL::getFor($routeName) . "?page={$nextPage}&partial=1";
				$response = $response->withHeader("X-Feed-Next", $nextUrl);
			}

			return $response->withBody($html);
		}

		return $response->withBody($engine->render($pageTemplate, array_merge([
			"section" => $section,
			"title" => Section::title($section),
			"lead" => Section::lead($section),
			"hasMore" => $hasMore,
			"nextPage" => $nextPage,
			"page" => $page,
		], $extra, $feedVars)));
	}
}
