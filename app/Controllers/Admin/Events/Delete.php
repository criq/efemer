<?php

namespace App\Controllers\Admin\Events;

use App\Classes\Content\Event;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Delete extends \Katu\Controllers\Controller
{
	public function getResponse(ServerRequestInterface $request, ResponseInterface $response, string $eventId)
	{
		$event = Event::get($eventId);
		if (!$event) {
			throw new \Katu\Exceptions\ModelNotFoundException;
		}

		$event->delete();

		return $response
			->withStatus(302)
			->withHeader("Location", (string)\Katu\Tools\Routing\URL::getFor("admin.events.index"))
			;
	}
}
