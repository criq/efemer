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
		if (!$page) {
			throw new \Katu\Exceptions\ModelNotFoundException;
		}

		$kind = KindCollection::createDefault()->filterByCode(new Code($kindCode))->getFirst();
		if (!$kind) {
			throw new \Katu\Exceptions\NotFoundException;
		}

		$parent = $this->resolveParent($page, $request);
		$siblings = $parent ? $parent->getChildComponents() : $page->getRootPageComponents();

		$pageComponent = new PageComponent;
		$pageComponent->setTimeCreated(new Time);
		$pageComponent->setPage($page);
		$pageComponent->setParent($parent);
		$pageComponent->setKind($kind);
		$pageComponent->setPosition(($siblings->getMaxPosition() ?? 0) + 1);
		$pageComponent->setTemplateCode($kind::getTemplates()->getDefaultCode());
		$pageComponent->persist();

		return $response
			->withStatus(302)
			->withHeader("Location", (string)\Katu\Tools\Routing\URL::getFor("admin.pages.editPageComponent", [
				"pageId" => $page->getId(),
				"pageComponentId" => $pageComponent->getId(),
			]))
			;
	}

	private function resolveParent(Page $page, ServerRequestInterface $request): ?PageComponent
	{
		$parentId = (int)($request->getQueryParams()["parentId"] ?? 0);
		if ($parentId <= 0) {
			return null;
		}

		$parent = PageComponent::get($parentId);
		if (!$parent || (int)$parent->getPage()->getId() !== (int)$page->getId()) {
			throw new \Katu\Exceptions\ModelNotFoundException;
		}

		if (!$parent->isLayout()) {
			throw new \Katu\Exceptions\ForbiddenException;
		}

		return $parent;
	}
}
