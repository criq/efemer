<?php

namespace App\Controllers\Admin\Posts;

use App\Classes\Content\Post;
use App\Classes\Content\PostParts;
use App\Classes\Content\Section;
use App\Classes\Content\Tags;
use App\Classes\Ingest\IngestItem;
use App\Classes\Ingest\IngestItemFile;
use App\Classes\Views\HTMLEngine;
use Katu\Errors\Error;
use Katu\Errors\ErrorCollection;
use Katu\Tools\Calendar\Time;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Edit extends \Katu\Controllers\Controller
{
	public function getResponse(ServerRequestInterface $request, ResponseInterface $response, string $postId)
	{
		$post = Post::get($postId);
		if (!$post) {
			throw new \Katu\Exceptions\ModelNotFoundException;
		}

		$errors = new ErrorCollection;
		$ingestItem = $this->resolveIngestItem($post);

		if ($this->isSubmittedWithToken($request)) {
			$body = $request->getParsedBody() ?? [];
			$title = trim((string)($body["title"] ?? ""));
			$section = (string)($body["section"] ?? "");
			$status = (string)($body["status"] ?? Post::STATUS_REVIEW);
			$excerpt = trim((string)($body["excerpt"] ?? ""));
			$place = trim((string)($body["place"] ?? ""));
			$startsAtRaw = trim((string)($body["startsAt"] ?? ""));
			$endsAtRaw = trim((string)($body["endsAt"] ?? ""));
			$isInvitation = !empty($body["isInvitation"]);

			$allowedSections = Section::all();
			if ($title === "") {
				$errors->addError(new Error("Zadejte název."));
			}
			if (!in_array($section, $allowedSections, true)) {
				$errors->addError(new Error("Vyberte sekci."));
			}
			if (!in_array($status, [Post::STATUS_REVIEW, Post::STATUS_PUBLISHED, Post::STATUS_HIDDEN], true)) {
				$errors->addError(new Error("Neplatný stav."));
			}

			$includedStorageIds = [];
			foreach ((array)($body["includedStorageFileIds"] ?? []) as $id) {
				$id = (int)$id;
				if ($id > 0) {
					$includedStorageIds[$id] = true;
				}
			}
			$includedStorageIds = array_keys($includedStorageIds);

			$editorParts = json_decode((string)($body["partsJson"] ?? "[]"), true);
			if (!is_array($editorParts)) {
				$errors->addError(new Error("Neplatná struktura částí."));
				$editorParts = [];
			}

			$post->setTitle($title !== "" ? $title : null);
			$post->setSection(in_array($section, $allowedSections, true) ? $section : $post->getSection());
			$post->setStatus($status);
			$wantInvitation = $isInvitation || $section === Section::UDALOSTI;
			$tags = array_values(array_filter(
				$post->getTagsList(),
				fn (string $tag) => $tag !== Tags::INVITATION && $tag !== Tags::REPORT,
			));
			if ($wantInvitation) {
				$tags[] = Tags::INVITATION;
			}
			$post->setTagsList($tags);
			$post->setPlace($place !== "" ? $place : null);
			$post->setStartsAt($startsAtRaw !== "" ? new Time($startsAtRaw) : null);
			$post->setEndsAt($endsAtRaw !== "" ? new Time($endsAtRaw) : null);

			if (!$errors->hasErrors()) {
				$this->syncPostFiles($post, $ingestItem, $includedStorageIds);
				$storageToPostFile = $this->storageIdMap($post);
				$parts = $this->editorPartsToPostParts($editorParts, $storageToPostFile);
				$validIds = [];
				foreach ($post->getFiles() as $file) {
					$validIds[(int)$file->getId()] = true;
				}
				$parts = PostParts::scrubMissingFiles($parts, $validIds);
				$post->setParts($parts);
				if ($excerpt !== "") {
					$post->setExcerpt($excerpt);
				}
				$post->persist();

				return $response
					->withStatus(302)
					->withHeader("Location", (string)\Katu\Tools\Routing\URL::getFor("admin.posts.index"))
					;
			}
		}

		$postFiles = [];
		foreach ($post->getFiles() as $file) {
			$thumb = null;
			try {
				$img = $file->getImage();
				$thumb = $img ? (string)$img->getImageVersion("THUMBNAIL")->getURL() : null;
			} catch (\Throwable) {
				$thumb = null;
			}
			$postFiles[] = [
				"id" => (int)$file->getId(),
				"storageFileId" => (int)$file->storageFileId,
				"caption" => $file->getCaption(),
				"thumb" => $thumb,
				"included" => true,
			];
		}

		$ingestPhotos = $this->ingestPhotoPayload($ingestItem, $post);

		$editorParts = $this->partsForEditor($post);

		return $response->withBody((new HTMLEngine($request))->render("Admin/Posts/edit.twig", [
			"errors" => $errors,
			"post" => $post,
			"sections" => Section::all(),
			"postFiles" => $postFiles,
			"ingestPhotos" => $ingestPhotos,
			"editorPartsJson" => json_encode($editorParts, JSON_UNESCAPED_UNICODE),
		]));
	}

	private function resolveIngestItem(Post $post): ?IngestItem
	{
		$id = $post->getIngestItemId();
		if (!$id) {
			return null;
		}

		return IngestItem::get($id);
	}

	/**
	 * @param list<int> $includedStorageIds
	 */
	private function syncPostFiles(Post $post, ?IngestItem $ingestItem, array $includedStorageIds): void
	{
		$included = array_fill_keys($includedStorageIds, true);
		$existingByStorage = [];
		foreach ($post->getFiles() as $file) {
			$sid = (int)$file->storageFileId;
			$existingByStorage[$sid] = $file;
			if (!isset($included[$sid])) {
				$file->delete();
				unset($existingByStorage[$sid]);
			}
		}

		$metaByStorage = [];
		if ($ingestItem) {
			foreach ($ingestItem->getFiles() as $ingestFile) {
				$sid = (int)$ingestFile->storageFileId;
				$metaByStorage[$sid] = $ingestFile;
			}
		}

		$position = 1;
		foreach ($includedStorageIds as $sid) {
			if (isset($existingByStorage[$sid])) {
				$existingByStorage[$sid]->setPosition($position++)->persist();
				continue;
			}
			try {
				$storage = \App\Classes\Storage\StorageFile::get($sid);
			} catch (\Throwable) {
				continue;
			}
			if (!$storage) {
				continue;
			}
			/** @var IngestItemFile|null $meta */
			$meta = $metaByStorage[$sid] ?? null;
			$post->attachStorageFile(
				$storage,
				$position++,
				$meta?->getCaption(),
				$meta?->getRating(),
				$meta?->getRole(),
				$meta ? $meta->isDemoted() : false,
			);
		}
	}

	/**
	 * @return array<int, int> storageFileId => postFileId
	 */
	private function storageIdMap(Post $post): array
	{
		$map = [];
		foreach ($post->getFiles() as $file) {
			$map[(int)$file->storageFileId] = (int)$file->getId();
		}

		return $map;
	}

	/**
	 * @param list<array<string, mixed>> $editorParts
	 * @param array<int, int> $storageToPostFile
	 * @return list<array{type: string, html?: string, postFileId?: int, postFileIds?: list<int>}>
	 */
	private function editorPartsToPostParts(array $editorParts, array $storageToPostFile): array
	{
		$out = [];
		foreach ($editorParts as $part) {
			if (!is_array($part)) {
				continue;
			}
			$type = (string)($part["type"] ?? "");
			if ($type === PostParts::TYPE_TEXT) {
				$normalized = PostParts::normalizePart([
					"type" => PostParts::TYPE_TEXT,
					"html" => (string)($part["html"] ?? ""),
				]);
				if ($normalized) {
					$out[] = $normalized;
				}
				continue;
			}
			if ($type === PostParts::TYPE_IMAGE) {
				$sid = (int)($part["storageFileId"] ?? 0);
				$postFileId = $storageToPostFile[$sid] ?? 0;
				$normalized = PostParts::normalizePart([
					"type" => PostParts::TYPE_IMAGE,
					"postFileId" => $postFileId,
				]);
				if ($normalized) {
					$out[] = $normalized;
				}
				continue;
			}
			if ($type === PostParts::TYPE_GALLERY) {
				$ids = [];
				foreach ((array)($part["storageFileIds"] ?? []) as $sid) {
					$sid = (int)$sid;
					if (isset($storageToPostFile[$sid])) {
						$ids[] = $storageToPostFile[$sid];
					}
				}
				$normalized = PostParts::normalizePart([
					"type" => PostParts::TYPE_GALLERY,
					"postFileIds" => $ids,
				]);
				if ($normalized) {
					$out[] = $normalized;
				}
			}
		}

		return $out;
	}

	/**
	 * @return list<array{type: string, html?: string, storageFileId?: int, storageFileIds?: list<int>}>
	 */
	private function partsForEditor(Post $post): array
	{
		$postFileToStorage = [];
		foreach ($post->getFiles() as $file) {
			$postFileToStorage[(int)$file->getId()] = (int)$file->storageFileId;
		}

		$out = [];
		foreach ($post->getParts() as $part) {
			$type = $part["type"] ?? "";
			if ($type === PostParts::TYPE_TEXT) {
				$out[] = ["type" => PostParts::TYPE_TEXT, "html" => (string)($part["html"] ?? "")];
				continue;
			}
			if ($type === PostParts::TYPE_IMAGE) {
				$pfid = (int)($part["postFileId"] ?? 0);
				$sid = $postFileToStorage[$pfid] ?? 0;
				if ($sid > 0) {
					$out[] = ["type" => PostParts::TYPE_IMAGE, "storageFileId" => $sid];
				}
				continue;
			}
			if ($type === PostParts::TYPE_GALLERY) {
				$sids = [];
				foreach ((array)($part["postFileIds"] ?? []) as $pfid) {
					$sid = $postFileToStorage[(int)$pfid] ?? 0;
					if ($sid > 0) {
						$sids[] = $sid;
					}
				}
				if ($sids !== []) {
					$out[] = ["type" => PostParts::TYPE_GALLERY, "storageFileIds" => $sids];
				}
			}
		}

		if ($out === []) {
			foreach (PostParts::resolveForRender($post) as $part) {
				$type = $part["type"] ?? "";
				if ($type === PostParts::TYPE_TEXT) {
					$out[] = ["type" => PostParts::TYPE_TEXT, "html" => (string)($part["html"] ?? "")];
				} elseif ($type === PostParts::TYPE_IMAGE && isset($part["file"])) {
					$file = $part["file"];
					$out[] = ["type" => PostParts::TYPE_IMAGE, "storageFileId" => (int)$file->storageFileId];
				} elseif ($type === PostParts::TYPE_GALLERY && !empty($part["files"])) {
					$sids = [];
					foreach ($part["files"] as $file) {
						$sids[] = (int)$file->storageFileId;
					}
					$out[] = ["type" => PostParts::TYPE_GALLERY, "storageFileIds" => $sids];
				}
			}
		}

		return $out;
	}

	/**
	 * @return list<array{storageFileId: int, caption: ?string, thumb: ?string, included: bool, demoted: bool}>
	 */
	private function ingestPhotoPayload(?IngestItem $ingestItem, Post $post): array
	{
		$included = [];
		foreach ($post->getFiles() as $file) {
			$included[(int)$file->storageFileId] = true;
		}

		$photos = [];
		if ($ingestItem) {
			foreach ($ingestItem->getFiles() as $ingestFile) {
				$sid = (int)$ingestFile->storageFileId;
				$thumb = null;
				try {
					$img = $ingestFile->getStorageFile()->getImage();
					$thumb = $img ? (string)$img->getImageVersion("THUMBNAIL")->getURL() : null;
				} catch (\Throwable) {
					$thumb = null;
				}
				$photos[] = [
					"storageFileId" => $sid,
					"caption" => $ingestFile->getCaption(),
					"thumb" => $thumb,
					"included" => isset($included[$sid]),
					"demoted" => $ingestFile->isDemoted() || $ingestFile->getRole() === IngestItemFile::ROLE_SKIP,
				];
			}
		}

		// Include any post files not present on ingest (manual).
		$seen = array_column($photos, "storageFileId");
		foreach ($post->getFiles() as $file) {
			$sid = (int)$file->storageFileId;
			if (in_array($sid, $seen, true)) {
				continue;
			}
			$thumb = null;
			try {
				$img = $file->getImage();
				$thumb = $img ? (string)$img->getImageVersion("THUMBNAIL")->getURL() : null;
			} catch (\Throwable) {
				$thumb = null;
			}
			$photos[] = [
				"storageFileId" => $sid,
				"caption" => $file->getCaption(),
				"thumb" => $thumb,
				"included" => true,
				"demoted" => false,
			];
		}

		return $photos;
	}
}
