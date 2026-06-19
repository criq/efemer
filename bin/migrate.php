<?php

include_once __DIR__ . "/../vendor/autoload.php";

(new \App\Classes\Database\Migrator)->run();

echo "Migrations complete.\n";
