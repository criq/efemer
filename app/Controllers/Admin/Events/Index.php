<?php

namespace App\Controllers\Admin\Events;

use App\Classes\Content\Event;
use App\Classes\Views\HTMLEngine;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Index extends \Katu\Controllers\Controller
{
	public function getResponse(ServerRequestInterface $request, ResponseInterface $response)
	{
		$events = Event::getAll()->getItems();
		usort($events, function (Event $a, Event $b) {
			return ((int)($b->getStartsAt()?->format("U") ?? 0)) <=> ((int)($a->getStartsAt()?->format("U") ?? 0));
		});

		return $response->withBody((new HTMLEngine($request))->render("Admin/Events/index.twig", [
			"events" => $events,
		]));
	}
}
