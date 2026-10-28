<?php

namespace App\Classes\Jobs;

/**
 * Shared Symfony Console colors for all jobs.
 *
 * Palette:
 * - cyan   progress / step markers
 * - green  success labels (Loaded, Done, ✓)
 * - yellow skip / warn
 * - red    errors
 * - magenta paths / ids
 * - white bold titles / counts
 * - gray   secondary text
 */
class ConsoleStyle
{
	public static function progress(int $current, int $total): string
	{
		return sprintf("<fg=cyan>[%d/%d]</>", $current, $total);
	}

	public static function step(string $message): string
	{
		return "<fg=cyan>·</> " . self::escape($message);
	}

	public static function title(string $text): string
	{
		return "<fg=white;options=bold>" . self::escape($text) . "</>";
	}

	public static function count(int|string $value): string
	{
		return self::title((string)$value);
	}

	public static function ok(string $label = "Done"): string
	{
		return "<fg=green;options=bold>" . self::escape($label) . "</>";
	}

	public static function skip(string $label = "Skip"): string
	{
		return "<fg=yellow>" . self::escape($label) . "</>";
	}

	public static function warn(string $message): string
	{
		return "<fg=yellow;options=bold>!</> " . self::escape($message);
	}

	public static function error(string $message): string
	{
		return "<fg=red;options=bold>" . self::escape($message) . "</>";
	}

	public static function muted(string $text): string
	{
		return "<fg=gray>" . self::escape($text) . "</>";
	}

	public static function path(string $text): string
	{
		return "<fg=magenta>" . self::escape($text) . "</>";
	}

	public static function jobStarted(string $title): string
	{
		return sprintf("%s Job %s started.", self::ok("→"), self::title($title));
	}

	public static function jobFinished(string $title): string
	{
		return sprintf("%s Job %s finished.", self::ok("✓"), self::title($title));
	}

	public static function jobLocked(string $title): string
	{
		return sprintf("%s Job %s locked.", self::skip("⏸"), self::title($title));
	}

	public static function done(string $message, ?int $count = null): string
	{
		$suffix = $count === null ? "" : " (" . self::count($count) . ")";

		return self::ok("Done") . " " . self::escape($message) . $suffix;
	}

	public static function loaded(string $path, ?string $title = null): string
	{
		return sprintf(
			"%s %s%s",
			self::ok("Loaded"),
			self::path($path),
			$title !== null && $title !== ""
				? " " . self::muted("—") . " " . self::title($title)
				: "",
		);
	}

	public static function updated(string $path, ?string $title = null): string
	{
		return sprintf(
			"%s %s%s",
			self::ok("Updated"),
			self::path($path),
			$title !== null && $title !== ""
				? " " . self::muted("—") . " " . self::title($title)
				: "",
		);
	}

	public static function skipped(string $path, string $reason = "unchanged"): string
	{
		return sprintf("%s %s %s", self::skip("Skip"), self::muted($reason), self::path($path));
	}

	public static function item(int $current, int $total, string $title): string
	{
		return self::progress($current, $total) . " " . self::title($title);
	}

	public static function escape(string $text): string
	{
		return str_replace(["<", ">"], ["\\<", "\\>"], $text);
	}
}
