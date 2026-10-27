<?php

namespace App\Classes\Instagram;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

class Client
{
	private const APP_ID = "936619743392459";
	private const USER_AGENT = "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36";

	private HttpClient $http;
	private HttpClient $downloadHttp;

	public function __construct(string $sessionid)
	{
		$cookies = "sessionid=" . $sessionid;
		$userId = Session::userId($sessionid);
		if ($userId) {
			$cookies .= "; ds_user_id=" . $userId;
		}

		$this->http = new HttpClient([
			"http_errors" => false,
			"allow_redirects" => false,
			"timeout" => 60,
			"headers" => [
				"User-Agent" => self::USER_AGENT,
				"Accept" => "*/*",
				"Accept-Language" => "en-US,en;q=0.9,cs;q=0.8",
				"X-IG-App-ID" => self::APP_ID,
				"X-Requested-With" => "XMLHttpRequest",
				"Referer" => "https://www.instagram.com/",
				"Cookie" => $cookies,
			],
		]);
		$this->downloadHttp = new HttpClient([
			"http_errors" => false,
			"allow_redirects" => true,
			"timeout" => 60,
			"headers" => [
				"User-Agent" => self::USER_AGENT,
				"Referer" => "https://www.instagram.com/",
			],
		]);
	}

	/**
	 * @return \Generator<int, array<string, mixed>>
	 */
	public function iteratePosts(string $account): \Generator
	{
		$profile = $this->fetchProfile($account);
		$userId = (string)($profile["id"] ?? "");
		if ($userId === "") {
			throw new \RuntimeException("Instagram profile {$account} has no user id.");
		}

		$seen = [];
		foreach ($this->timelineNodes($profile) as $node) {
			$post = $this->fromGraphNode($account, $node);
			if (!$post || isset($seen[$post["shortcode"]])) {
				continue;
			}
			$seen[$post["shortcode"]] = true;
			yield $post;
		}

		$maxId = null;
		while (true) {
			$page = $this->fetchFeedPage($userId, $maxId);
			$items = $page["items"] ?? [];
			if (!is_array($items) || $items === []) {
				break;
			}

			foreach ($items as $item) {
				if (!is_array($item)) {
					continue;
				}
				$post = $this->fromFeedItem($account, $item);
				if (!$post || isset($seen[$post["shortcode"]])) {
					continue;
				}
				$seen[$post["shortcode"]] = true;
				yield $post;
			}

			if (empty($page["more_available"]) || empty($page["next_max_id"])) {
				break;
			}

			$maxId = (string)$page["next_max_id"];
			usleep(2_000_000);
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	public function fetchByShortcode(string $shortcode): array
	{
		$mediaId = self::shortcodeToMediaId($shortcode);
		$urls = [
			"https://www.instagram.com/api/v1/media/{$mediaId}/info/",
			"https://i.instagram.com/api/v1/media/{$mediaId}/info/",
			"https://www.instagram.com/p/" . rawurlencode($shortcode) . "/?__a=1&__d=dis",
		];

		$lastError = null;
		foreach ($urls as $url) {
			try {
				$json = $this->getJson($url);
				$item = $json["items"][0] ?? $json["graphql"]["shortcode_media"] ?? null;
				if (is_array($item) && isset($item["shortcode"])) {
					$post = $this->fromGraphNode("", $item);
					if ($post) {
						return $post;
					}
				}
				if (is_array($item)) {
					$account = (string)($item["user"]["username"] ?? "");
					$post = $this->fromFeedItem($account, $item);
					if ($post) {
						return $post;
					}
				}
			} catch (RateLimitedException | SessionExpiredException $exception) {
				throw $exception;
			} catch (\Throwable $exception) {
				$lastError = $exception;
			}
		}

		throw $lastError ?? new \RuntimeException("Instagram post {$shortcode} could not be loaded.");
	}

	public static function shortcodeToMediaId(string $shortcode): string
	{
		$alphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_";
		$id = gmp_init(0);
		foreach (str_split($shortcode) as $char) {
			$pos = strpos($alphabet, $char);
			if ($pos === false) {
				throw new \InvalidArgumentException("Invalid Instagram shortcode.");
			}
			$id = gmp_add(gmp_mul($id, 64), $pos);
		}

		return gmp_strval($id);
	}

	public function download(string $url, string $destination): void
	{
		$dir = dirname($destination);
		if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
			throw new \RuntimeException("Cannot create {$dir}");
		}

		try {
			$response = $this->downloadHttp->request("GET", $url, [
				"sink" => $destination,
			]);
		} catch (GuzzleException $exception) {
			throw new \RuntimeException("Media download failed: " . $exception->getMessage(), 0, $exception);
		}

		$status = $response->getStatusCode();
		if ($status >= 400) {
			@unlink($destination);
			throw new \RuntimeException("Media download HTTP {$status}");
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private function fetchProfile(string $account): array
	{
		$urls = [
			"https://www.instagram.com/api/v1/users/web_profile_info/?username=" . rawurlencode($account),
			"https://i.instagram.com/api/v1/users/web_profile_info/?username=" . rawurlencode($account),
		];

		$lastError = null;
		foreach ($urls as $url) {
			try {
				$json = $this->getJson($url);
				$user = $json["data"]["user"] ?? null;
				if (is_array($user) && !empty($user["id"])) {
					return $user;
				}
				$lastError = new \RuntimeException("Profile payload for {$account} is missing a user object.");
			} catch (RateLimitedException | SessionExpiredException $exception) {
				throw $exception;
			} catch (\Throwable $exception) {
				$lastError = $exception;
			}
		}

		throw $lastError ?? new \RuntimeException("Could not load Instagram profile {$account}.");
	}

	/**
	 * @param array<string, mixed> $profile
	 * @return list<array<string, mixed>>
	 */
	private function timelineNodes(array $profile): array
	{
		$edges = $profile["edge_owner_to_timeline_media"]["edges"] ?? [];
		$nodes = [];
		foreach (is_array($edges) ? $edges : [] as $edge) {
			$node = is_array($edge) ? ($edge["node"] ?? null) : null;
			if (is_array($node)) {
				$nodes[] = $node;
			}
		}

		return $nodes;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function fetchFeedPage(string $userId, ?string $maxId): array
	{
		$query = http_build_query(array_filter([
			"count" => 12,
			"max_id" => $maxId,
		], fn ($value) => $value !== null && $value !== ""));

		$urls = [
			"https://www.instagram.com/api/v1/feed/user/{$userId}/?{$query}",
			"https://i.instagram.com/api/v1/feed/user/{$userId}/?{$query}",
		];

		foreach ($urls as $url) {
			try {
				$json = $this->getJson($url);
				if (isset($json["items"]) && is_array($json["items"])) {
					return $json;
				}
			} catch (RateLimitedException | SessionExpiredException $exception) {
				throw $exception;
			} catch (\Throwable) {
				continue;
			}
		}

		return ["items" => [], "more_available" => false];
	}

	/**
	 * @param array<string, mixed> $node
	 * @return array<string, mixed>|null
	 */
	private function fromGraphNode(string $account, array $node): ?array
	{
		$shortcode = (string)($node["shortcode"] ?? "");
		if ($shortcode === "") {
			return null;
		}

		$caption = "";
		$captionEdges = $node["edge_media_to_caption"]["edges"] ?? [];
		if (is_array($captionEdges) && isset($captionEdges[0]["node"]["text"])) {
			$caption = (string)$captionEdges[0]["node"]["text"];
		}

		$media = [];
		$children = $node["edge_sidecar_to_children"]["edges"] ?? null;
		if (is_array($children) && $children) {
			foreach ($children as $child) {
				$childNode = is_array($child) ? ($child["node"] ?? []) : [];
				$media[] = $this->graphMedia($childNode);
			}
		} else {
			$media[] = $this->graphMedia($node);
		}

		return [
			"account" => $account,
			"shortcode" => $shortcode,
			"pk" => (string)($node["id"] ?? ""),
			"taken_at" => (int)($node["taken_at_timestamp"] ?? time()),
			"media_type" => (string)($node["__typename"] ?? ""),
			"caption" => $caption,
			"location" => is_array($node["location"] ?? null) ? ($node["location"]["name"] ?? null) : null,
			"media" => array_values(array_filter($media)),
		];
	}

	/**
	 * @param array<string, mixed> $node
	 * @return array<string, mixed>|null
	 */
	private function graphMedia(array $node): ?array
	{
		$isVideo = !empty($node["is_video"]);
		$url = $isVideo
			? (string)($node["video_url"] ?? $node["display_url"] ?? "")
			: (string)($node["display_url"] ?? "");
		if ($url === "") {
			return null;
		}

		return [
			"url" => $url,
			"is_video" => $isVideo,
		];
	}

	/**
	 * @param array<string, mixed> $item
	 * @return array<string, mixed>|null
	 */
	private function fromFeedItem(string $account, array $item): ?array
	{
		$shortcode = (string)($item["code"] ?? "");
		if ($shortcode === "") {
			return null;
		}

		$caption = "";
		if (is_array($item["caption"] ?? null)) {
			$caption = (string)($item["caption"]["text"] ?? "");
		} elseif (is_string($item["caption"] ?? null)) {
			$caption = $item["caption"];
		}

		$media = [];
		$carousel = $item["carousel_media"] ?? null;
		if (is_array($carousel) && $carousel) {
			foreach ($carousel as $child) {
				if (is_array($child)) {
					$media[] = $this->feedMedia($child);
				}
			}
		} else {
			$media[] = $this->feedMedia($item);
		}

		return [
			"account" => $account,
			"shortcode" => $shortcode,
			"pk" => (string)($item["pk"] ?? $item["id"] ?? ""),
			"taken_at" => (int)($item["taken_at"] ?? time()),
			"media_type" => (string)($item["media_type"] ?? ""),
			"caption" => $caption,
			"location" => is_array($item["location"] ?? null) ? ($item["location"]["name"] ?? null) : null,
			"media" => array_values(array_filter($media)),
		];
	}

	/**
	 * @param array<string, mixed> $item
	 * @return array<string, mixed>|null
	 */
	private function feedMedia(array $item): ?array
	{
		$video = $item["video_versions"][0]["url"] ?? null;
		if (is_string($video) && $video !== "") {
			return ["url" => $video, "is_video" => true];
		}

		$image = $item["image_versions2"]["candidates"][0]["url"] ?? null;
		if (is_string($image) && $image !== "") {
			return ["url" => $image, "is_video" => false];
		}

		return null;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function getJson(string $url): array
	{
		try {
			$response = $this->http->request("GET", $url);
		} catch (GuzzleException $exception) {
			throw new \RuntimeException("Instagram request failed: " . $exception->getMessage(), 0, $exception);
		}

		$body = (string)$response->getBody();
		$this->assertSession($response, $body);
		$json = json_decode($body, true);
		if (!is_array($json)) {
			throw new \RuntimeException("Instagram returned non-JSON from {$url}.");
		}

		return $json;
	}

	private function assertSession(ResponseInterface $response, string $body = ""): void
	{
		$status = $response->getStatusCode();
		if ($status === 429) {
			throw new RateLimitedException("Instagram rate-limited the archive request.");
		}

		$location = $response->getHeaderLine("Location");
		if (in_array($status, [301, 302, 303, 307, 308], true) && str_contains($location, "login")) {
			throw new SessionExpiredException("Instagram sessionid was rejected (redirected to login).");
		}

		if ($status === 401 || $status === 403) {
			throw new SessionExpiredException("Instagram sessionid was rejected (HTTP {$status}).");
		}

		if ($status >= 400) {
			throw new \RuntimeException("Instagram HTTP {$status}.");
		}

		if (str_contains($body, "checkpoint_required") || str_contains($body, "\"challenge_required\"")) {
			throw new SessionExpiredException("Instagram session needs a checkpoint. Refresh sessionid in Secret Manager.");
		}
	}
}
