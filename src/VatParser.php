<?php
declare(strict_types=1);

namespace Vexco;

final class VatParser
{
    // países miembros de la UE válidos para VIES
    private const EU_COUNTRIES = [
        'AT','BE','BG','CY','CZ','DE','DK','EE','ES','FI','FR','GR','HR','HU','IE','IT','LT','LU','LV','MT','NL','PL','PT','RO','SE','SI','SK','XI'
    ];

    public function parse(?string $raw): array
    {
        if ($raw === null) {
            return ['action' => 'OMIT', 'reason' => 'Dato vacío'];
        }

        $s = trim($raw);
        if ($s === '') {
            return ['action' => 'OMIT', 'reason' => 'Dato vacío'];
        }

        // pasa a mayúsculas y quita todo lo que no sea letra o número
        $normalized = strtoupper($s);
        $normalized = preg_replace('/[^A-Z0-9]/', '', $normalized);

        if ($normalized === '') {
            return ['action' => 'OMIT', 'reason' => 'Formato irreconocible tras normalizar'];
        }

        // filtra valores basura directos
        if (in_array($s, ['N/A', '-', 'PENDIENTE'], true)) {
            return ['action' => 'OMIT', 'reason' => 'Dato no válido para validación'];
        }

        $garbage = ['12345', 'NA', 'PENDIENTE', 'NULL', 'NONE'];
        if (in_array($normalized, $garbage, true)) {
            return ['action' => 'OMIT', 'reason' => 'Dato no válido para validación'];
        }

        // extrae código país (2 letras) + número
        if (preg_match('/^([A-Z]{2})([A-Z0-9]+)$/', $normalized, $m)) {
            $country = $m[1];
            $vatNumber = $m[2];

            // países fuera de UE -> omitido (GB, CH, US...)
            if (!in_array($country, self::EU_COUNTRIES, true)) {
                return ['action' => 'OMIT', 'reason' => 'País fuera de la UE / no soportado por VIES'];
            }

            if ($vatNumber === '') {
                return ['action' => 'OMIT', 'reason' => 'Formato irreconocible'];
            }

            return [
                'action'     => 'VALIDATE',
                'country'    => $country,
                'vatNumber'  => $vatNumber,
                'raw'        => $s,
                'normalized' => $country . $vatNumber,
            ];
        }

        // solo alfanumérico sin prefijo de 2 letras -> VIES necesita código país
        if (preg_match('/^[A-Z0-9]+$/', $normalized)) {
            return ['action' => 'OMIT', 'reason' => 'Falta prefijo de país (no se puede validar con VIES)'];
        }

        return ['action' => 'OMIT', 'reason' => 'Formato irreconocible'];
    }
}
