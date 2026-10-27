<?php

require dirname(__DIR__) . "/vendor/autoload.php";

use App\Classes\Import\InstagramImporter;

$importer = (new InstagramImporter)->onLog(function (string $message): void {
	fwrite(STDOUT, $message . PHP_EOL);
});

$importer->run();
