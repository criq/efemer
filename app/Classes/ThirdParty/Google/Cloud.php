<?php

namespace App\Classes\ThirdParty\Google;

class Cloud
{
	public static function getStorageClient(): \Google\Cloud\Storage\StorageClient
	{
		return new \Google\Cloud\Storage\StorageClient([
			"keyFilePath" => new \Katu\Files\File(\App\App::getBaseDir(), ".secret", "efemer-storage@efemer-456414.iam.gserviceaccount.com.json"),
		]);
	}

	public static function getFilesBucket(): \Google\Cloud\Storage\Bucket
	{
		return static::getStorageClient()->bucket("efemer-files");
	}
}
