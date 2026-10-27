<?php

set_time_limit(0);
ini_set("display_errors", "0");
error_reporting(E_ALL & ~E_DEPRECATED);

require dirname(__DIR__) . "/vendor/autoload.php";

use App\Classes\Instagram\Archiver;

$argv = $argv ?? [];
$loop = in_array("--loop", $argv, true);
$full = in_array("--full", $argv, true);
$interval = Archiver::DEFAULT_INTERVAL;
foreach ($argv as $arg) {
	if (str_starts_with($arg, "--interval=")) {
		$interval = max(60, (int)substr($arg, strlen("--interval=")));
	}
}

$archiver = (new Archiver)->onLog(function (string $message): void {
	fwrite(STDOUT, date("c") . " " . $message . PHP_EOL);
});

if ($loop) {
	$archiver->loop($interval, $full);
	exit(0);
}

$archiver->run($full);
