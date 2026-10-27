<?php

namespace App\Classes\Html;

class Excerpt
{
	public static function fromHtml(?string $html, int $limit = 220): string
	{
		$text = html_entity_decode(strip_tags((string)$html), ENT_QUOTES | ENT_HTML5, "UTF-8");
		$text = trim(preg_replace("/\s+/u", " ", $text) ?? "");
		if ($text === "" || mb_strlen($text) <= $limit) {
			return $text;
		}

		$window = mb_substr($text, 0, $limit + 1);
		$sentences = "";
		if (preg_match_all("/.*?[.!?](?=\s|$)/u", $window, $matches)) {
			foreach ($matches[0] as $sentence) {
				$next = trim($sentences . $sentence);
				if (mb_strlen($next) > $limit) {
					break;
				}
				$sentences = $next;
			}
		}

		if (mb_strlen($sentences) >= 40) {
			return $sentences;
		}

		$cut = mb_substr($text, 0, $limit);
		$space = mb_strrpos($cut, " ");
		if ($space !== false && $space > (int)($limit * 0.6)) {
			$cut = mb_substr($cut, 0, $space);
		}

		return rtrim($cut, " .,;:–—-") . "…";
	}
}
