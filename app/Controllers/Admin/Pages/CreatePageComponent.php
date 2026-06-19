<?php

namespace App\Controllers\Admin\Pages;

use App\Classes\Pages\Components\KindCollection;
use App\Classes\Pages\Page;
use App\Classes\Pages\PageComponent;
use Katu\Tools\Calendar\Time;
use Katu\Tools\Strings\Code;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class CreatePageComponent extends \Katu\Controllers\Controller
{
	public function getResponse(ServerRequestInterface $request, ResponseInterface $response, string $pageId, string $kindCode)
	{
		$page = Page::get($pageId);
		$kind = KindCollection::createDefault()->filterByCode(new Code($kindCode))->getFirst();

		$pageComponent = new PageComponent;
		$pageComponent->setTimeCreated(new Time);
		$pageComponent->setPage($page);
		$pageComponent->setKind($kind);
		$pageComponent->setPosition($page->getPageComponents()->getMaxPosition() + 1);
		$pageComponent->persist();

		return $response
			->withStatus(302)
			->withHeader("Location", (string)\Katu\Tools\Routing\URL::getFor("admin.pages.editPageComponent", [
				"pageId" => $page->getId(),
				"pageComponentId" => $pageComponent->getId(),
			]))
			;
	}
}
