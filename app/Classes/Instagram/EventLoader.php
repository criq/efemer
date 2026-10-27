<?php

namespace App\Classes\Instagram;

use App\Classes\Content\Section;
use App\Classes\Storage\StorageFile;
use Katu\Tools\Calendar\Time;

class EventLoader
{
	/**
	 * @return array{
	 *     title: string,
	 *     bodyHtml: string,
	 *     url: string,
	 *     place: ?string,
	 *     section: string,
	 *     startsAt: ?Time,
	 *     endsAt: ?Time,
	 *     heroFile: ?StorageFile
	 * }
	 */
	public function load(string $url): array
	{
		$shortcode = PostUrl::shortcode($url);
		if (!$shortcode) {
			throw new \InvalidArgumentException("To nevypadá na odkaz na Instagram.");
		}

		$client = new Client(Session::load());
		$post = $client->fetchByShortcode($shortcode);
		$caption = trim((string)($post["caption"] ?? ""));
		$dates = CaptionDates::parse($caption);

		$hero = null;
		foreach ($post["media"] ?? [] as $item) {
			$mediaUrl = is_array($item) ? ($item["url"] ?? null) : null;
			if (!is_string($mediaUrl) || $mediaUrl === "" || !empty($item["is_video"])) {
				continue;
			}

			$dir = sys_get_temp_dir() . "/efemer-ig-event";
			if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
				break;
			}
			$path = $dir . "/" . $shortcode . ".jpg";
			try {
				$client->download($mediaUrl, $path);
				$contents = (string)file_get_contents($path);
				if ($contents !== "") {
					$hero = StorageFile::createFromContents($contents, $shortcode . ".jpg");
				}
			} catch (\Throwable) {
				$hero = null;
			}
			@unlink($path);
			break;
		}

		return [
			"title" => $this->titleFromCaption($caption) ?: $shortcode,
			"bodyHtml" => $this->captionToHtml($caption),
			"url" => PostUrl::canonical($shortcode),
			"place" => is_string($post["location"] ?? null) ? $post["location"] : null,
			"section" => Section::HIMMELHAUS,
			"startsAt" => $dates["startsAt"],
			"endsAt" => $dates["endsAt"],
			"heroFile" => $hero,
		];
	}

	private function titleFromCaption(string $caption): string
	{
		$first = preg_split("/\R/u", trim($caption))[0] ?? "";
		$first = trim(preg_replace("/\s+/u", " ", $first) ?? $first);
		$first = preg_replace("/^[#@]\\S+\\s*/u", "", $first) ?? $first;
		if (mb_strlen($first) > 80) {
			$first = rtrim(mb_substr($first, 0, 77)) . "…";
		}

		return $first;
	}

	private function captionToHtml(string $caption): string
	{
		$caption = trim($caption);
		if ($caption === "") {
			return "";
		}

		$escaped = htmlspecialchars($caption, ENT_QUOTES | ENT_HTML5, "UTF-8");
		$paragraphs = preg_split("/\n{2,}/", $escaped) ?: [$escaped];
		$html = "";
		foreach ($paragraphs as $paragraph) {
			$html .= "<p>" . nl2br(trim($paragraph), false) . "</p>\n";
		}

		return trim($html);
	}
}
