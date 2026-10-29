<?php
declare(strict_types=1);

namespace Vexco;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;

final class ViesClient
{
    private Client $http;

    public function __construct()
    {
        $this->http = new Client([
            'base_uri'    => 'https://ec.europa.eu/',
            'timeout'     => 15,
            'http_errors' => false,
            'headers'     => [
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    public function check(string $countryCode, string $vatNumber): array
    {
        $countryCode = strtoupper(trim($countryCode));
        $vatNumber   = strtoupper(trim($vatNumber));

        $path = 'taxation_customs/vies/rest-api/check-vat-number';
        $body = [
            'countryCode' => $countryCode,
            'vatNumber'   => $vatNumber,
        ];

        // intenta llamada a VIES
        try {
            $response = $this->http->post($path, ['json' => $body]);
        } catch (ConnectException $e) {
            // timeout o sin conexión -> error temporal, no marcar como inválido
            return [
                'status' => 'ERROR_TEMPORAL',
                'reason' => 'No se pudo conectar con VIES (timeout o error de red)',
            ];
        } catch (RequestException $e) {
            return [
                'status' => 'ERROR_TEMPORAL',
                'reason' => 'Error de red al realizar la petición a VIES',
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'ERROR_TEMPORAL',
                'reason' => 'Error inesperado al llamar a VIES',
            ];
        }

        $statusCode = $response->getStatusCode();

        // 5xx o 429 -> servicio no disponible, reintentable
        if ($statusCode >= 500 || $statusCode === 429) {
            return [
                'status' => 'ERROR_TEMPORAL',
                'reason' => 'VIES no disponible temporalmente (HTTP ' . $statusCode . ')',
            ];
        }

        $raw = (string) $response->getBody();
        if ($raw === '') {
            return [
                'status' => 'ERROR_TEMPORAL',
                'reason' => 'Respuesta vacía de VIES',
            ];
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return [
                'status' => 'ERROR_TEMPORAL',
                'reason' => 'Respuesta JSON no interpretable por VIES',
            ];
        }

        // válido según VIES
        if (array_key_exists('valid', $data) && $data['valid'] === true) {
            return [
                'status' => 'VALIDO',
                'data'   => $data,
            ];
        }

        // inválido según VIES
        if (array_key_exists('valid', $data) && $data['valid'] === false) {
            $reason = 'Número de IVA no válido según VIES';

            // intenta usar motivo que devuelve VIES
            if (isset($data['userError']) && is_string($data['userError']) && $data['userError'] !== '') {
                $reason = $data['userError'];
            } elseif (isset($data['error']) && is_string($data['error']) && $data['error'] !== '') {
                $reason = $data['error'];
            }

            return [
                'status' => 'INVALIDO',
                'reason' => $reason,
            ];
        }

        // algunos casos devuelven actionSucceed false
        if (isset($data['actionSucceed']) && $data['actionSucceed'] === false) {
            $reason = 'VIES indica error en la consulta';
            if (isset($data['errorWrappers'][0]['error']) && is_string($data['errorWrappers'][0]['error'])) {
                $reason = $data['errorWrappers'][0]['error'];
            }

            return [
                'status' => 'ERROR_TEMPORAL',
                'reason' => $reason,
            ];
        }

        // respuesta distinta a lo esperado -> tratar como temporal para poder reintentar
        return [
            'status' => 'ERROR_TEMPORAL',
            'reason' => 'Respuesta inesperada de VIES',
        ];
    }
}
