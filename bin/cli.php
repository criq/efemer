<?php

set_time_limit(0);
ini_set("display_errors", "1");
error_reporting(E_ALL & ~E_DEPRECATED);

require dirname(__DIR__) . "/vendor/autoload.php";

$app = new \Symfony\Component\Console\Application("efemer", "1.0");
$app->add(new \App\Classes\Jobs\RunFromCLI);
$app->add(new \App\Classes\Jobs\RunLoop);
$app->run();
