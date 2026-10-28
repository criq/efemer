<?php

namespace App\Classes\ThirdParty\Google;

use App\Classes\Jobs\Signals;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;

class Gemini
{
	public const MODEL = "gemini-3.8-flash";
	private const ENDPOINT = "https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent";
	private const MAX_ATTEMPTS = 5;

	private HttpClient $http;
	private string $apiKey;

	/** @var list<callable(string): void> */
	private array $loggers = [];

	public function __construct(?string $apiKey = null)
	{
		$this->apiKey = $apiKey ?? self::loadApiKey();
		if ($this->apiKey === "") {
			throw new \RuntimeException(
				"Gemini API key is missing. Store it in Secret Manager as " . Secrets::GEMINI_API_KEY
				. " or in .secret/gemini.api.",
			);
		}

		$this->http = new HttpClient([
			"http_errors" => false,
			"timeout" => 180,
		]);
	}

	public function onLog(callable $logger): self
	{
		$this->loggers[] = $logger;

		return $this;
	}

	public static function loadApiKey(): string
	{
		try {
			$fromSecrets = Secrets::get(Secrets::GEMINI_API_KEY);
		} catch (\Throwable) {
			$fromSecrets = null;
		}

		if (is_string($fromSecrets) && trim($fromSecrets) !== "") {
			return trim($fromSecrets);
		}

		$path = (string)(new \Katu\Files\File(\App\App::getBaseDir(), ".secret", "gemini.api"));
		if (is_file($path)) {
			$value = trim((string)file_get_contents($path));
			if ($value !== "") {
				return $value;
			}
		}

		return "";
	}

	/**
	 * @param list<array{text?: string, inline_data?: array{mime_type: string, data: string}}> $parts
	 * @return array{text: string, usage: array{promptTokens: ?int, candidatesTokens: ?int, totalTokens: ?int}, raw: array}
	 */
	public function generateContent(array $parts, ?string $systemInstruction = null, string $model = self::MODEL): array
	{
		$body = [
			"contents" => [
				[
					"role" => "user",
					"parts" => $parts,
				],
			],
			"generationConfig" => [
				"temperature" => 0.2,
				"responseMimeType" => "application/json",
			],
		];
		if ($systemInstruction !== null && $systemInstruction !== "") {
			$body["systemInstruction"] = [
				"parts" => [
					["text" => $systemInstruction],
				],
			];
		}

		$url = sprintf(self::ENDPOINT, rawurlencode($model)) . "?key=" . rawurlencode($this->apiKey);
		$attempt = 0;

		while (true) {
			$attempt++;
			Signals::throwIfStopped();

			try {
				$response = $this->http->request("POST", $url, [
					"headers" => [
						"Content-Type" => "application/json",
					],
					"json" => $body,
				]);
			} catch (GuzzleException $exception) {
				throw new \RuntimeException("Gemini request failed: " . $exception->getMessage(), 0, $exception);
			}

			$status = $response->getStatusCode();
			$rawBody = (string)$response->getBody();

			if (in_array($status, [429, 503], true) && $attempt < self::MAX_ATTEMPTS) {
				$delay = min(120, 5 * (2 ** ($attempt - 1)));
				$this->log("Gemini HTTP {$status} — waiting {$delay}s before retry {$attempt}/" . self::MAX_ATTEMPTS . ".");
				$this->sleepInterruptible($delay);
				continue;
			}

			if ($status >= 400) {
				$snippet = mb_substr(trim($rawBody), 0, 300);
				throw new \RuntimeException("Gemini HTTP {$status}: {$snippet}");
			}

			$json = json_decode($rawBody, true);
			if (!is_array($json)) {
				throw new \RuntimeException("Gemini returned non-JSON.");
			}

			$text = $this->extractText($json);
			$usageMeta = is_array($json["usageMetadata"] ?? null) ? $json["usageMetadata"] : [];

			return [
				"text" => $text,
				"usage" => [
					"promptTokens" => isset($usageMeta["promptTokenCount"]) ? (int)$usageMeta["promptTokenCount"] : null,
					"candidatesTokens" => isset($usageMeta["candidatesTokenCount"]) ? (int)$usageMeta["candidatesTokenCount"] : null,
					"totalTokens" => isset($usageMeta["totalTokenCount"]) ? (int)$usageMeta["totalTokenCount"] : null,
				],
				"raw" => $json,
			];
		}
	}

	/**
	 * @param array<string, mixed> $json
	 */
	private function extractText(array $json): string
	{
		$candidates = $json["candidates"] ?? null;
		if (!is_array($candidates) || $candidates === []) {
			$block = $json["promptFeedback"]["blockReason"] ?? null;
			throw new \RuntimeException("Gemini returned no candidates" . ($block ? " (blocked: {$block})" : "") . ".");
		}

		$parts = $candidates[0]["content"]["parts"] ?? null;
		if (!is_array($parts)) {
			throw new \RuntimeException("Gemini candidate has no parts.");
		}

		$text = "";
		foreach ($parts as $part) {
			if (is_array($part) && isset($part["text"])) {
				$text .= (string)$part["text"];
			}
		}

		$text = trim($text);
		if ($text === "") {
			throw new \RuntimeException("Gemini returned empty text.");
		}

		return $text;
	}

	private function sleepInterruptible(int $seconds): void
	{
		$deadline = time() + max(0, $seconds);
		while (true) {
			Signals::throwIfStopped();
			$remaining = $deadline - time();
			if ($remaining <= 0) {
				return;
			}
			sleep(min(5, $remaining));
		}
	}

	private function log(string $message): void
	{
		foreach ($this->loggers as $logger) {
			$logger($message);
		}
	}
}
