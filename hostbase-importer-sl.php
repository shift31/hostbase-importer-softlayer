<?php

require __DIR__ . '/vendor/autoload.php';

use Hostbase\SoftlayerImporter;

$config = parse_ini_file(__DIR__ . '/config.ini');

$importer = new SoftlayerImporter($config);

$importer->importHardware();

$importer->importVirtualGuests();

$importer->importSubnets();