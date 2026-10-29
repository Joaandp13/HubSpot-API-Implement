<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Vexco\Config;
use Vexco\HubSpotClient;
use Vexco\VatParser;
use Vexco\ViesClient;
use Vexco\Processor;

$cfg = Config::load(__DIR__);
$hs  = new HubSpotClient($cfg['token'], $cfg['base_url']);
$parser = new VatParser();
$vies   = new ViesClient();


$processor = new Processor($hs, $parser, $vies, 'joan');
$processor->run();