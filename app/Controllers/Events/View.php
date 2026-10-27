<?php

namespace App\Controllers\Events;

use App\Classes\Content\Event;
use App\Classes\Content\Section;
use App\Classes\Views\HTMLEngine;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class View extends \Katu\Controllers\Controller
{
	public function getResponse(ServerRequestInterface $request, ResponseInterface $response, string $slug)
	{
		$path = Section::pathPrefix(Section::UDALOSTI) . "/" . ltrim($slug, "/");
		$event = Event::getByPath($path);
		if (!$event || !$event->isPublished()) {
			throw new \Katu\Exceptions\ModelNotFoundException;
		}

		$related = $event->getSection();
		$relatedIsWork = $related && Section::isWork($related);

		return $response->withBody((new HTMLEngine($request))->render("Events/view.twig", [
			"event" => $event,
			"sectionTitle" => Section::title(Section::UDALOSTI),
			"sectionPath" => Section::pathPrefix(Section::UDALOSTI),
			"relatedTitle" => $relatedIsWork ? Section::title($related) : null,
			"relatedPath" => $relatedIsWork ? Section::pathPrefix($related) : null,
		]));
	}
}
