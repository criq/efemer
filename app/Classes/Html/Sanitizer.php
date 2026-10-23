<?php

namespace App\Classes\Html;

class Sanitizer
{
	private const ALLOWED_TAGS = "<p><br><strong><b><em><i><u><s><a><ul><ol><li><h1><h2><h3><h4><blockquote><hr>";

	public static function clean(?string $html): string
	{
		$html = trim((string)$html);
		if ($html === "") {
			return "";
		}

		$html = preg_replace("#<script\b[^>]*>.*?</script>#is", "", $html) ?? $html;
		$html = preg_replace("#<style\b[^>]*>.*?</style>#is", "", $html) ?? $html;
		$html = strip_tags($html, self::ALLOWED_TAGS);

		$previous = libxml_use_internal_errors(true);
		$document = new \DOMDocument();
		$loaded = $document->loadHTML(
			"<?xml encoding=\"UTF-8\"><div id=\"sanitize-root\">" . $html . "</div>",
			LIBXML_HTML_NODEFDTD,
		);
		libxml_clear_errors();
		libxml_use_internal_errors($previous);

		if (!$loaded) {
			return $html;
		}

		$root = $document->getElementById("sanitize-root");
		if (!$root) {
			return $html;
		}

		static::cleanAttributes($root);

		$result = "";
		foreach ([...$root->childNodes] as $child) {
			$result .= $document->saveHTML($child);
		}

		return trim($result);
	}

	private static function cleanAttributes(\DOMNode $node): void
	{
		if ($node instanceof \DOMElement) {
			$tag = strtolower($node->tagName);
			$allowed = match ($tag) {
				"a" => ["href", "title", "target", "rel"],
				"h1", "h2", "h3", "h4" => ["id"],
				default => [],
			};

			$remove = [];
			foreach ($node->attributes ?? [] as $attribute) {
				$name = strtolower($attribute->name);
				if (!in_array($name, $allowed, true)) {
					$remove[] = $attribute->name;
					continue;
				}

				if ($name === "href" && preg_match("/^\s*javascript:/i", $attribute->value)) {
					$remove[] = $attribute->name;
				}
			}

			foreach ($remove as $name) {
				$node->removeAttribute($name);
			}

			if ($tag === "a" && $node->hasAttribute("href") && !$node->hasAttribute("rel")) {
				$node->setAttribute("rel", "noopener noreferrer");
			}
		}

		foreach ([...$node->childNodes] as $child) {
			static::cleanAttributes($child);
		}
	}
}
