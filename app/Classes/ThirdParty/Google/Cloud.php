<?php

namespace App\Classes\ThirdParty\Google;

use Google\Cloud\SecretManager\V1\Client\SecretManagerServiceClient;
use Google\Cloud\Storage\StorageClient;
use Katu\Files\File;

class Cloud
{
	public const PROJECT_ID = "efemer-456414";
	public const FILES_BUCKET = "efemer-files";

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

	public static function getSecretManagerClient(): SecretManagerServiceClient
	{
		return new SecretManagerServiceClient([
			"credentials" => (string)static::getKeyFile(),
			"projectId" => static::getProjectId(),
			"transport" => "rest",
		]);
	}
}
