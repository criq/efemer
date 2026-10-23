<?php

namespace App\Controllers\Admin\Pages\Builder;

use App\Classes\Forms\RequestToken;
use App\Classes\Html\Sanitizer;
use App\Classes\Pages\PageComponent;
use Katu\Errors\Error;
use Katu\Errors\ErrorCollection;
use Katu\Tools\Rest\RestResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class UpdateValue extends \Katu\Controllers\Controller
{
	public function getResponse(ServerRequestInterface $request, ResponseInterface $response, string $pageId, string $pageComponentId)
	{
		if ($request->getMethod() !== "POST") {
			throw new \Katu\Exceptions\NotFoundException;
		}

		if (!RequestToken::validate($request)) {
			throw new \Katu\Exceptions\ForbiddenException;
		}

		$pageComponent = PageComponent::get($pageComponentId);
		if (!$pageComponent || (string)$pageComponent->getPage()->getId() !== $pageId) {
			throw new \Katu\Exceptions\ModelNotFoundException;
		}

		$kindCode = $pageComponent->getKind()?->getCode()->getConstantFormat();
		if ($kindCode !== "TEXT") {
			return $this->jsonError($response, "Text lze upravit jen u textové součásti.", 400);
		}

		$parsedBody = $request->getParsedBody();
		if (!is_array($parsedBody)) {
			$parsedBody = $_POST;
		}

		$value = Sanitizer::clean($parsedBody["value"] ?? "");
		$pageComponent->setValue($value !== "" ? $value : null);
		$pageComponent->persist();

		return $response
			->withStatus(200)
			->withHeader("Content-Type", "application/json; charset=UTF-8")
			->withBody((new RestResponse([
				"value" => $pageComponent->getValue(),
			]))->getStream())
			;
	}

	private function jsonError(ResponseInterface $response, string $message, int $status): ResponseInterface
	{
		return $response
			->withStatus($status)
			->withHeader("Content-Type", "application/json; charset=UTF-8")
			->withBody((new ErrorCollection([
				new Error($message),
			]))->getRestResponse()->getStream())
			;
	}
}
