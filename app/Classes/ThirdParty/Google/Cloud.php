<?php

namespace App\Classes\ThirdParty\Google;

use Google\Cloud\SecretManager\V1\Client\SecretManagerServiceClient;
use Google\Cloud\Storage\StorageClient;
use Katu\Files\File;

class Cloud
{
	public const PROJECT_ID = "efemer-456414";
	public const FILES_BUCKET = "efemer";

	public static function getProjectId(): string
	{
		return self::PROJECT_ID;
	}

	public static function getKeyFile(): File
	{
		return new File(\App\App::getBaseDir(), ".secret", "efemer-storage@efemer-456414.iam.gserviceaccount.com.json");
	}

	public static function getStorageClient(): StorageClient
	{
		return new StorageClient([
			"keyFilePath" => static::getKeyFile(),
			"projectId" => static::getProjectId(),
		]);
	}

	public static function getFilesBucket(): \Google\Cloud\Storage\Bucket
	{
		return static::getStorageClient()->bucket(self::FILES_BUCKET);
	}

	/**
	 * Top-level GCS path shard for this app environment (DEV, PROD, …).
	 */
	public static function getFilesEnvironmentShard(): string
	{
		$env = mb_strtoupper(trim((string)(new \App\Config\AppConfig)->getEnvironment()));
		$shard = match ($env) {
			"DEVELOPMENT", "DEV" => "DEV",
			"PRODUCTION", "PROD" => "PROD",
			"STAGING", "STAGE" => "STAGE",
			default => preg_replace("/[^A-Z0-9_-]/", "", $env) ?: "DEV",
		};

		return $shard;
	}

	public static function getFilesObjectPrefix(): string
	{
		return static::getFilesEnvironmentShard() . "/";
	}

	public static function uriBelongsToCurrentEnvironment(string $uri): bool
	{
		$prefix = "gs://" . self::FILES_BUCKET . "/" . static::getFilesObjectPrefix();

		return str_starts_with($uri, $prefix);
	}

	public static function getSecretManagerClient(): SecretManagerServiceClient
	{
		return new SecretManagerServiceClient([
			"credentials" => (string)static::getKeyFile(),
			"projectId" => static::getProjectId(),
			"transport" => "rest",
		]);
	}
}

