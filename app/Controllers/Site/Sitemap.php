<?php

namespace App\Controllers\Site;

use App\Classes\Content\Post;
use App\Classes\Content\Section;
use GuzzleHttp\Psr7\Utils;
use Katu\Tools\Calendar\Time;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Sitemap extends \Katu\Controllers\Controller
{
	public function getResponse(ServerRequestInterface $request, ResponseInterface $response)
	{
		$urls = [];

		foreach ($this->staticEntries() as $entry) {
			$urls[] = $entry;
		}

		foreach (Section::work() as $section) {
			foreach (Post::getPublishedBySection($section) as $post) {
				$urls[] = $this->postEntry($post);
			}
		}

		foreach (Post::getCurrentInvitations() as $post) {
			$urls[] = $this->postEntry($post);
		}

		$xml = $this->renderXml(array_values(array_filter($urls)));

		return $response
			->withHeader("Content-Type", "application/xml; charset=UTF-8")
			->withHeader("Cache-Control", "public, max-age=3600")
			->withBody(Utils::streamFor($xml));
	}

	/**
	 * @return list<array{loc: string, lastmod: ?string, changefreq: string, priority: string}>
	 */
	private function staticEntries(): array
	{
		return [
			$this->entry((string)\Katu\Tools\Routing\URL::getFor("homepage.index"), null, "daily", "1.0"),
			$this->entry((string)\Katu\Tools\Routing\URL::getFor("sections.ritualy"), null, "weekly", "0.9"),
			$this->entry((string)\Katu\Tools\Routing\URL::getFor("sections.denik"), null, "daily", "0.9"),
			$this->entry((string)\Katu\Tools\Routing\URL::getFor("sections.umeni"), null, "weekly", "0.8"),
			$this->entry((string)\Katu\Tools\Routing\URL::getFor("sections.udalosti"), null, "daily", "0.7"),
			$this->entry((string)\Katu\Tools\Routing\URL::getFor("site.kontakt"), null, "monthly", "0.6"),
			$this->entry((string)\Katu\Tools\Routing\URL::getFor("site.vTisku"), null, "monthly", "0.5"),
			$this->entry((string)\Katu\Tools\Routing\URL::getFor("site.newsletter"), null, "monthly", "0.4"),
		];
	}

	/**
	 * @return array{loc: string, lastmod: ?string, changefreq: string, priority: string}|null
	 */
	private function postEntry(Post $post): ?array
	{
		$loc = $post->getPublicUrl();
		if ($loc === "") {
			return null;
		}

		$lastmod = $post->getPublishedAt() ?? $post->getExplicitAt();

		return $this->entry(
			$loc,
			$lastmod,
			$post->isInvitation() ? "daily" : "monthly",
			$post->isInvitation() ? "0.8" : "0.7",
		);
	}

	/**
	 * @return array{loc: string, lastmod: ?string, changefreq: string, priority: string}
	 */
	private function entry(string $loc, ?Time $lastmod, string $changefreq, string $priority): array
	{
		return [
			"loc" => $loc,
			"lastmod" => $lastmod ? $lastmod->format("Y-m-d") : null,
			"changefreq" => $changefreq,
			"priority" => $priority,
		];
	}

	/**
	 * @param list<array{loc: string, lastmod: ?string, changefreq: string, priority: string}> $urls
	 */
	private function renderXml(array $urls): string
	{
		$lines = [
			"<?xml version=\"1.0\" encoding=\"UTF-8\"?>",
			"<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">",
		];

		foreach ($urls as $url) {
			$lines[] = "  <url>";
			$lines[] = "    <loc>" . htmlspecialchars($url["loc"], ENT_XML1 | ENT_QUOTES, "UTF-8") . "</loc>";
			if (!empty($url["lastmod"])) {
				$lines[] = "    <lastmod>" . $url["lastmod"] . "</lastmod>";
			}
			$lines[] = "    <changefreq>" . $url["changefreq"] . "</changefreq>";
			$lines[] = "    <priority>" . $url["priority"] . "</priority>";
			$lines[] = "  </url>";
		}

		$lines[] = "</urlset>";
		$lines[] = "";

		return implode("\n", $lines);
	}
}
