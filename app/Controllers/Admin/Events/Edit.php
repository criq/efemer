<?php

namespace App\Controllers\Admin\Events;

use App\Classes\Content\Event;
use App\Classes\Content\Section;
use App\Classes\Html\Sanitizer;
use App\Classes\Instagram\EventLoader;
use App\Classes\Instagram\PostUrl;
use App\Classes\Instagram\RateLimitedException;
use App\Classes\Instagram\SessionExpiredException;
use App\Classes\Storage\StorageFile;
use App\Classes\Views\HTMLEngine;
use Katu\Errors\Error;
use Katu\Errors\ErrorCollection;
use Katu\Files\UploadCollection;
use Katu\Tools\Calendar\Time;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Edit extends \Katu\Controllers\Controller
{
	public function getResponse(ServerRequestInterface $request, ResponseInterface $response, string $eventId = "")
	{
		$event = $eventId !== "" ? Event::get($eventId) : null;
		if ($eventId !== "" && !$event) {
			throw new \Katu\Exceptions\ModelNotFoundException;
		}

		$errors = new ErrorCollection;
		$form = $this->formFromEvent($event);

		if ($this->isSubmittedWithToken($request)) {
			$body = $request->getParsedBody() ?? [];
			$action = (string)($body["action"] ?? "save");
			$form = $this->formFromBody($body, $form);

			if ($action === "fetch") {
				$this->fetchInstagram($form, $errors);
			} else {
				$this->validate($form, $errors);
				$upload = UploadCollection::createFromInput($request->getUploadedFiles()["hero"] ?? [])->filterWithoutError()->getFirst();

				if (!$errors->hasErrors()) {
					$event = $this->persist($event, $form, $upload);

					return $response
						->withStatus(302)
						->withHeader("Location", (string)\Katu\Tools\Routing\URL::getFor("admin.events.index"))
						;
				}
			}
		}

		return $response->withBody((new HTMLEngine($request))->render("Admin/Events/edit.twig", [
			"errors" => $errors,
			"event" => $event,
			"form" => $form,
			"sections" => Section::work(),
		]));
	}

	/**
	 * @return array<string, mixed>
	 */
	private function formFromEvent(?Event $event): array
	{
		return [
			"title" => $event?->getTitle() ?? "",
			"url" => $event?->getUrl() ?? "",
			"place" => $event?->getPlace() ?? "",
			"section" => $event?->getSection() ?? Section::DENIK,
			"startsAt" => $event?->getStartsAtLocal() ?? "",
			"endsAt" => $event?->getEndsAtLocal() ?? "",
			"published" => $event ? $event->isPublished() : true,
			"bodyHtml" => $event?->getBodyHtml() ?? "",
			"heroFileId" => $event?->heroFileId,
			"heroImage" => $event?->getHeroImage(),
		];
	}

	/**
	 * @param array<string, mixed> $body
	 * @param array<string, mixed> $form
	 * @return array<string, mixed>
	 */
	private function formFromBody(array $body, array $form): array
	{
		$form["title"] = trim((string)($body["title"] ?? ""));
		$form["url"] = trim((string)($body["url"] ?? ""));
		$form["place"] = trim((string)($body["place"] ?? ""));
		$form["section"] = (string)($body["section"] ?? "");
		$form["startsAt"] = trim((string)($body["startsAt"] ?? ""));
		$form["endsAt"] = trim((string)($body["endsAt"] ?? ""));
		$form["published"] = !empty($body["published"]);
		$form["bodyHtml"] = (string)($body["bodyHtml"] ?? "");
		$heroFileId = trim((string)($body["heroFileId"] ?? ""));
		$form["heroFileId"] = $heroFileId !== "" ? $heroFileId : null;
		if ($form["heroFileId"]) {
			$file = StorageFile::get($form["heroFileId"]);
			$form["heroImage"] = $file ? $file->getImage() : null;
		}

		return $form;
	}

	/**
	 * @param array<string, mixed> $form
	 */
	private function fetchInstagram(array &$form, ErrorCollection $errors): void
	{
		if (PostUrl::shortcode((string)$form["url"]) === null) {
			$errors->addError(new Error("Vložte odkaz na příspěvek na Instagramu."));

			return;
		}

		try {
			$draft = (new EventLoader)->load((string)$form["url"]);
			$form["url"] = $draft["url"];
			if ($form["title"] === "") {
				$form["title"] = $draft["title"];
			}
			if ($draft["bodyHtml"] !== "") {
				$form["bodyHtml"] = $draft["bodyHtml"];
			}
			if ($form["place"] === "" && $draft["place"]) {
				$form["place"] = $draft["place"];
			}
			if ($form["startsAt"] === "" && $draft["startsAt"]) {
				$form["startsAt"] = $draft["startsAt"]->format("Y-m-d\\TH:i");
			}
			if ($form["endsAt"] === "" && $draft["endsAt"]) {
				$form["endsAt"] = $draft["endsAt"]->format("Y-m-d\\TH:i");
			}
			$form["section"] = $draft["section"];
			if ($draft["heroFile"]) {
				$form["heroFileId"] = $draft["heroFile"]->getId();
				$form["heroImage"] = $draft["heroFile"]->getImage();
			}
		} catch (RateLimitedException) {
			$errors->addError(new Error("Instagram teď nejde načíst (příliš mnoho požadavků). Vyplňte událost ručně."));
		} catch (SessionExpiredException) {
			$errors->addError(new Error("Instagram session vypršela. Vyplňte událost ručně, nebo obnovte sessionid."));
		} catch (\InvalidArgumentException $exception) {
			$errors->addError(new Error($exception->getMessage()));
		} catch (\Throwable) {
			$errors->addError(new Error("Z Instagramu se příspěvek nepodařilo načíst. Vyplňte událost ručně."));
		}
	}

	/**
	 * @param array<string, mixed> $form
	 */
	private function validate(array $form, ErrorCollection $errors): void
	{
		if ($form["title"] === "") {
			$errors->addError(new Error("Zadejte název."));
		}
		if ($form["startsAt"] === "") {
			$errors->addError(new Error("Zadejte začátek."));
		} elseif (!$this->parseTime((string)$form["startsAt"])) {
			$errors->addError(new Error("Začátek není platné datum."));
		}
		if ($form["endsAt"] !== "" && !$this->parseTime((string)$form["endsAt"])) {
			$errors->addError(new Error("Konec není platné datum."));
		}
		if ($form["section"] !== "" && !Section::isWork((string)$form["section"])) {
			$errors->addError(new Error("Vyberte související sekci."));
		}
		if ($form["url"] !== "" && !preg_match("#^https?://#i", (string)$form["url"])) {
			$errors->addError(new Error("Odkaz musí začínat http:// nebo https://."));
		}
	}

	/**
	 * @param array<string, mixed> $form
	 */
	private function persist(?Event $event, array $form, mixed $upload): Event
	{
		if (!$event) {
			$event = new Event;
			$event->setTimeCreated(new Time);
		}

		$startsAt = $this->parseTime((string)$form["startsAt"]);
		$endsAt = $form["endsAt"] !== "" ? $this->parseTime((string)$form["endsAt"]) : null;

		$event->setTitle((string)$form["title"]);
		$event->setStartsAt($startsAt ?: new Time);
		$event->setEndsAt($endsAt);
		$event->setPlace($form["place"] !== "" ? (string)$form["place"] : null);
		$event->setSection($form["section"] !== "" && Section::isWork((string)$form["section"]) ? (string)$form["section"] : null);
		$event->setUrl($form["url"] !== "" ? (string)$form["url"] : null);
		$event->setBodyHtml(Sanitizer::clean((string)$form["bodyHtml"]) ?: null);
		$event->setPublished((bool)$form["published"]);

		if ($upload) {
			$event->setHeroFile(StorageFile::createFromUpload($upload));
		} elseif ($form["heroFileId"]) {
			$event->setHeroFile(StorageFile::get($form["heroFileId"]));
		}

		if (!$event->getPath()) {
			$event->assignPath();
		}

		$event->persist();

		return $event;
	}

	private function parseTime(string $value): ?Time
	{
		$value = trim(str_replace("T", " ", $value));
		if ($value === "") {
			return null;
		}

		try {
			$time = new Time($value);
		} catch (\Throwable) {
			return null;
		}

		return $time;
	}
}
