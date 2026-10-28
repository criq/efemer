<?php

namespace App\Classes\ThirdParty\Google;

use Google\ApiCore\ApiException;
use Google\Cloud\SecretManager\V1\AccessSecretVersionRequest;
use Google\Cloud\SecretManager\V1\AddSecretVersionRequest;
use Google\Cloud\SecretManager\V1\Client\SecretManagerServiceClient;
use Google\Cloud\SecretManager\V1\SecretPayload;

class Secrets
{
	public const INSTAGRAM_SESSION_ID = "instagram-session-id";
	public const GEMINI_API_KEY = "gemini-api-key";

	public static function get(string $name): ?string
	{
		$client = Cloud::getSecretManagerClient();
		$request = AccessSecretVersionRequest::build(
			SecretManagerServiceClient::secretVersionName(Cloud::getProjectId(), $name, "latest"),
		);

		try {
			$response = $client->accessSecretVersion($request);
		} catch (ApiException $exception) {
			if (in_array($exception->getStatus(), ["NOT_FOUND", "PERMISSION_DENIED"], true)) {
				return null;
			}

			throw $exception;
		}

		$payload = $response->getPayload();
		if (!$payload) {
			return null;
		}

		$value = trim((string)$payload->getData());

		return $value !== "" ? $value : null;
	}

	public static function put(string $name, string $value): void
	{
		$value = trim($value);
		if ($value === "") {
			throw new \InvalidArgumentException("Refusing to store an empty secret.");
		}

		$current = static::get($name);
		if ($current === $value) {
			return;
		}

		$client = Cloud::getSecretManagerClient();
		$payload = new SecretPayload;
		$payload->setData($value);
		$client->addSecretVersion(AddSecretVersionRequest::build(
			SecretManagerServiceClient::secretName(Cloud::getProjectId(), $name),
			$payload,
		));
	}
}
