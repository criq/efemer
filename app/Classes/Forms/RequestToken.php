<?php

namespace App\Classes\Forms;

use Psr\Http\Message\ServerRequestInterface;

class RequestToken
{
	public static function get(ServerRequestInterface $request): ?string
	{
		$parsedBody = $request->getParsedBody();
		if (is_array($parsedBody) && array_key_exists("formToken", $parsedBody)) {
			return $parsedBody["formToken"];
		}

		return $_POST["formToken"] ?? null;
	}

	public static function validate(ServerRequestInterface $request): bool
	{
		return \Katu\Tools\Forms\Token::validate(static::get($request) ?? "");
	}
}
