<?php

namespace App;

use App\Classes\Users\User;
use App\Classes\Views\HTMLEngine;
use Katu\Errors\Error;
use Katu\Errors\ErrorCollection;
use Katu\Types\TIdentifier;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

class App extends \Katu\App
{
	public static function getLogger(TIdentifier $identifier): LoggerInterface
	{
		return new \App\Classes\Logs\Logger($identifier);
	}

	public static function getErrorHandler(): ?callable
	{
		return function (ServerRequestInterface $request, \Throwable $exception, bool $displayErrorDetails, bool $logErrors, bool $logErrorDetails, ?LoggerInterface $logger = null): ResponseInterface
		{
			$logger = $logger ?: static::getLogger(new TIdentifier("error"));
			$response = static::getInstance()->getResponseFactory()->createResponse();

			$isJson = \Katu\Tools\HTTPTools\Headers\Accept::createFromRequest($request)->accepts("application/json");

			try {
				throw $exception;

			// Bad request.
			} catch (\Katu\Exceptions\UserErrorException $exception) {
				if ($isJson) {
					return $response
						->withStatus(404)
						->withHeader("Content-Type", "application/json; charset=UTF-8")
						->withBody((new ErrorCollection([
							new Error("Bad request."),
						]))->getRestResponse()->getStream())
						;
				} else {
					return $response
						->withStatus(400)
						->withBody((new HTMLEngine($request))->render("Errors/400.twig"))
						;
				}

			// Unauthorized.
			} catch (\Katu\Exceptions\UnauthorizedException $exception) {
				if ($isJson) {
					return $response
						->withStatus(404)
						->withHeader("Content-Type", "application/json; charset=UTF-8")
						->withBody((new ErrorCollection([
							new Error("Unauthorized."),
						]))->getRestResponse()->getStream())
						;
				} else {
					$user = User::getFromRequest($request);
					if (!$user) {
						return $response
							->withStatus(302)
							->withHeader("Location", (string)\Katu\Tools\Routing\URL::getFor("account.login"))
							;
					}

					return $response
						->withStatus(401)
						->withBody((new HTMLEngine($request))->render("Errors/401.twig"))
						;
				}

			// Forbidden.
			} catch (\Katu\Exceptions\ForbiddenException $exception) {
				if ($isJson) {
					return $response
						->withStatus(404)
						->withHeader("Content-Type", "application/json; charset=UTF-8")
						->withBody((new ErrorCollection([
							new Error("Forbidden."),
						]))->getRestResponse()->getStream())
						;
				} else {
					return $response
						->withStatus(403)
						->withBody((new HTMLEngine($request))->render("Errors/403.twig"))
						;
				}
			// Not found.
			} catch (\Katu\Exceptions\NotFoundException $exception) {
				if ($isJson) {
					return $response
						->withStatus(404)
						->withHeader("Content-Type", "application/json; charset=UTF-8")
						->withBody((new ErrorCollection([
							new Error("Not found."),
						]))->getRestResponse()->getStream())
						;
				} else {
					return $response
						->withStatus(404)
						->withBody((new HTMLEngine($request))->render("Errors/404.twig"))
						;
				}

			// Redirect.
			} catch (\Katu\Exceptions\RedirectException $e) {
				return $response
					->withStatus(302)
					->withHeader("Location", $e->getUrl())
					;

			} catch (\Throwable $exception) {
				$logger->error($exception);

				if ($isJson) {
					return $response
						->withStatus(500)
						->withHeader("Content-Type", "application/json; charset=UTF-8")
						->withBody((new ErrorCollection([
							new Error("Error."),
						]))->getRestResponse()->getStream())
						;

				} else {
					return $response
						->withStatus(500)
						->withBody((new HTMLEngine($request))->render("Errors/500.twig"))
						;

				}
			}

			return $response->withStatus(500);
		};
	}
}
