<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Vexco\VatParser;
use Vexco\ViesClient;

$parser = new VatParser();
$vies   = new ViesClient();

// Casos extraídos de tu run.php para probar
$tests = [
    'ESA39000013',      // ES con prefijo A
    'ESB00000000',      // Inválido ES
    'IT00488410010',    // IT
    'it00488410010',    // IT minúsculas
    'DE129274202',      // DE
    'DE143593636',      // Inválido DE
    'FR00552120875',    // Inválido FR
    'NL001679042B01',   // Inválido NL
    'PT500697256',      // PT
    '[ ES A39000013 ]', // Con espacios
    'ES.A48265169',     // Con punto
    'DE 129 274 202',   // Con espacios
    'GB123456789',      // GB fuera UE
    'CHE-123.456.789 MWST', // CH fuera UE
    'A82018474',        // Sin prefijo
    '500697256',        // Sin prefijo
    '12345',            // Basura
    'N/A',              // Basura
    'PENDIENTE',        // Basura
];

foreach ($tests as $raw) {
    echo PHP_EOL . "=== RAW: " . $raw . " ===" . PHP_EOL;
    
    $parsed = $parser->parse($raw);
    
    echo "VatParser => " . PHP_EOL;
    print_r($parsed);
    
    if ($parsed['action'] === 'VALIDATE') {
        echo "-> Llamando a VIES con " . $parsed['country'] . $parsed['vatNumber'] . " ..." . PHP_EOL;
        $res = $vies->check($parsed['country'], $parsed['vatNumber']);
        echo "ViesClient => " . PHP_EOL;
        print_r($res);
    } else {
        echo "-> OMITIDO por VatParser (no se llama a VIES)" . PHP_EOL;
    }
    
    echo str_repeat('-', 50) . PHP_EOL;
}