<?php

namespace App\Classes\Instagram;

use App\Classes\ThirdParty\Google\Secrets;
use Katu\Files\File;

class Session
{
	public static function localFile(): File
	{
		return new File(\App\App::getBaseDir(), ".secret", "instagram.session");
	}

	public static function load(): string
	{
		$fromSecrets = self::fromSecretManager();
		if ($fromSecrets !== null) {
			return $fromSecrets;
		}

		$fromFile = self::fromLocalFile();
		if ($fromFile !== null) {
			try {
				Secrets::put(Secrets::INSTAGRAM_SESSION_ID, $fromFile);
			} catch (\Throwable) {
				// Local fallback still works if Secret Manager is unreachable.
			}

			return $fromFile;
		}

		throw new SessionExpiredException(
			"Instagram sessionid is missing. Store it in Secret Manager as " . Secrets::INSTAGRAM_SESSION_ID . " or in .secret/instagram.session.",
		);
	}

	public static function save(string $sessionid): void
	{
		$sessionid = self::normalize($sessionid);
		if ($sessionid === "") {
			throw new \InvalidArgumentException("Empty Instagram sessionid.");
		}

		Secrets::put(Secrets::INSTAGRAM_SESSION_ID, $sessionid);

		$file = static::localFile();
		$file->getDir()->makeDir();
		$file->set($sessionid . "\n");
	}

	public static function userId(string $sessionid): ?string
	{
		$parts = explode(":", $sessionid, 2);
		$id = $parts[0] ?? "";

		return ctype_digit($id) ? $id : null;
	}

	private static function fromSecretManager(): ?string
	{
		try {
			$value = Secrets::get(Secrets::INSTAGRAM_SESSION_ID);
		} catch (\Throwable) {
			return null;
		}

		return $value !== null ? self::normalize($value) : null;
	}

	private static function fromLocalFile(): ?string
	{
		$file = static::localFile();
		if (!$file->exists()) {
			return null;
		}

		$normalized = self::normalize((string)$file->get());

		return $normalized !== "" ? $normalized : null;
	}

	private static function normalize(string $sessionid): string
	{
		$sessionid = trim($sessionid);
		$sessionid = preg_split("/\R/u", $sessionid)[0] ?? $sessionid;
		$sessionid = trim($sessionid);
		if (str_contains($sessionid, "%3A") || str_contains($sessionid, "%3a")) {
			$sessionid = urldecode($sessionid);
		}

		return trim($sessionid);
	}
}
