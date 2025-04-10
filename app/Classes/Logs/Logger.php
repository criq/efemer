<?php

namespace App\Classes\Logs;

use Katu\Types\TIdentifier;

class Logger extends \Katu\Tools\Logs\Logger
{
	public function __construct(TIdentifier $identifier)
	{
		parent::__construct($identifier);
	}
}
