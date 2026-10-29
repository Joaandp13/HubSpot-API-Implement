<?php
declare(strict_types=1);

namespace Vexco;

final class Processor
{
    private string $prefix;
    private string $propEstado;
    private string $propFecha;
    private string $propMotivo;

    private int $total = 0;
    private int $validas = 0;
    private int $invalidas = 0;
    private int $conError = 0;
    private int $omitidas = 0;

    public function __construct(
        private HubSpotClient $hs,
        private VatParser $parser,
        private ViesClient $vies,
        string $firstName
    ) {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '', $firstName));
        $this->prefix     = 'vies_' . $slug . '_';
        $this->propEstado = $this->prefix . 'estado';
        $this->propFecha  = $this->prefix . 'fecha';
        $this->propMotivo = $this->prefix . 'motivo';
    }

    public function run(): void
    {
        echo "Iniciando validación VIES..." . PHP_EOL . PHP_EOL;

        // carga también las propiedades vies_* para aplicar idempotencia
        $props = [
            'name',
            'nif_cif_vat',
            $this->propEstado,
            $this->propFecha,
            $this->propMotivo,
        ];

        $batch = [];
        $batchSize = 100;

        foreach ($this->hs->searchCompaniesWithVat($props) as $company) {
            $this->total++;

            $id = $company['id'];
            $propsObj = $company['properties'] ?? [];
            $rawVat = $propsObj['nif_cif_vat'] ?? null;
            $existingEstado = $propsObj[$this->propEstado] ?? null;

            // idempotencia: si ya tiene resultado definitivo, no vuelve a procesar ni a escribir
            if (in_array($existingEstado, ['VALIDO', 'INVALIDO', 'OMITIDO'], true)) {
                match ($existingEstado) {
                    'VALIDO'   => $this->validas++,
                    'INVALIDO' => $this->invalidas++,
                    'OMITIDO'  => $this->omitidas++,
                    default    => null,
                };
                continue;
            }

            $parsed = $this->parser->parse($rawVat);

            // va directo a omitido
            if ($parsed['action'] === 'OMIT') {
                $this->omitidas++;
                $batch[] = [
                    'id' => $id,
                    'properties' => [
                        $this->propEstado => 'OMITIDO',
                        $this->propMotivo => $parsed['reason'] ?? 'Dato que no se puede validar',
                    ],
                ];
            } elseif ($parsed['action'] === 'VALIDATE') {
                // valida contra VIES
                $check = $this->vies->check($parsed['country'], $parsed['vatNumber']);

                if ($check['status'] === 'VALIDO') {
                    $this->validas++;
                    $batch[] = [
                        'id' => $id,
                        'properties' => [
                            $this->propEstado => 'VALIDO',
                            $this->propFecha  => date('c'), // ISO 8601
                            $this->propMotivo => '',
                        ],
                    ];
                } elseif ($check['status'] === 'INVALIDO') {
                    $this->invalidas++;
                    $batch[] = [
                        'id' => $id,
                        'properties' => [
                            $this->propEstado => 'INVALIDO',
                            $this->propMotivo => $check['reason'] ?? 'Número no válido según VIES',
                            $this->propFecha  => '',
                        ],
                    ];
                } elseif ($check['status'] === 'ERROR_TEMPORAL') {
                    // no marca como inválido, deja para reintentar
                    $this->conError++;
                    $batch[] = [
                        'id' => $id,
                        'properties' => [
                            $this->propEstado => 'ERROR_TEMPORAL',
                            $this->propMotivo => $check['reason'] ?? 'Error temporal al consultar VIES',
                        ],
                    ];
                } else {
                    $this->conError++;
                    $batch[] = [
                        'id' => $id,
                        'properties' => [
                            $this->propEstado => 'ERROR_TEMPORAL',
                            $this->propMotivo => 'Resultado desconocido de VIES',
                        ],
                    ];
                }
            } else {
                $this->omitidas++;
                $batch[] = [
                    'id' => $id,
                    'properties' => [
                        $this->propEstado => 'OMITIDO',
                        $this->propMotivo => 'Dato que no se puede validar',
                    ],
                ];
            }

            // envía batch cada 100
            if (count($batch) >= $batchSize) {
                $this->flushBatch($batch);
                $batch = [];
            }
        }

        // envía lo que quede
        if (!empty($batch)) {
            $this->flushBatch($batch);
            $batch = [];
        }

        $this->printSummary();
    }

    private function flushBatch(array &$batch): void
    {
        try {
            $res = $this->hs->batchUpdateCompanies($batch);
            if ($res['status'] < 200 || $res['status'] >= 300) {
                echo "Aviso: batch update devolvió HTTP " . $res['status'] . PHP_EOL;
            }
        } catch (\Throwable $e) {
            echo "Error al enviar batch: " . $e->tagMessage . PHP_EOL;
        }
    }

    private function printSummary(): void
    {
        echo PHP_EOL . str_repeat('=', 40) . PHP_EOL;
        echo "RESUMEN VALIDACIÓN VIES" . PHP_EOL;
        echo str_repeat('=', 40) . PHP_EOL;
        echo "Total empresas con nif_cif_vat: " . $this->total . PHP_EOL;
        echo "Válidas (VALIDO):                " . $this->validas . PHP_EOL;
        echo "Inválidas (INVALIDO):            " . $this->invalidas . PHP_EOL;
        echo "Con error (ERROR_TEMPORAL):      " . $this->conError . PHP_EOL;
        echo "Omitidas (OMITIDO):              " . $this->omitidas . PHP_EOL;
        echo str_repeat('=', 40) . PHP_EOL;
    }
}
