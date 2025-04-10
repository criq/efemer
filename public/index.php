<?php

error_reporting(~E_ALL);
ini_set("display_errors", false);

// error_reporting(E_ALL & ~E_DEPRECATED);
// ini_set("display_errors", true);

ini_set("session.gc_probability", 1);
ini_set("session.gc_divisor", 1000);
ini_set("session.gc_maxlifetime", 86400);

include_once "../vendor/autoload.php";

try {
	\App\App::getInstance()->run();
} catch (\Throwable $e) {
	\App\App::getLogger(new \Katu\Types\TIdentifier("app"))->error($e);
}
