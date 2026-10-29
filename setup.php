<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Vexco\Config;
use Vexco\HubSpotClient;
use Vexco\SchemaSetup;

$cfg = Config::load(__DIR__);
$hs  = new HubSpotClient($cfg['token'], $cfg['base_url']);

$schema = new SchemaSetup($hs, 'joan');
$schema->run();

echo "\n¡Listo! Grupo y propiedades listos para usar.\n";