<?php
declare(strict_types=1);

namespace Vexco;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;

final class HubSpotClient
{
    private const MAX_RETRIES = 5;
    private const OBJECTS = 'crm/v3/objects/companies';

    private Client $http;

    public function __construct(string $token, string $baseUrl)
    {
        $this->http = new Client([
            'base_uri'    => rtrim($baseUrl, '/') . '/',
            'headers'     => [
                'Authorization' => "Bearer $token",
                'Accept'        => 'application/json',
            ],
            'timeout'     => 30,
            'http_errors' => false,
        ]);
    }

    public function request(string $method, string $path, array $options = []): array
    {
        for ($attempt = 0; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                $response = $this->http->request($method, ltrim($path, '/'), $options);
            } catch (ConnectException $e) {
                // reintento por error de conexión con backoff
                sleep(min(2 ** $attempt, 30));
                continue;
            }

            $status = $response->getStatusCode();

            // reintento ante 429, 423 o 5xx
            if ($status === 429 || $status === 423 || $status >= 500) {
                $wait = (int) $response->getHeaderLine('Retry-After');
                if ($wait <= 0) {
                    // mínimo 2s para 423, backoff hasta 30s
                    $wait = max(2, min(2 ** $attempt, 30));
                }
                sleep($wait);
                continue;
            }

            $body = (string) $response->getBody();
            $data = $body !== '' ? json_decode($body, true) : [];

            return ['status' => $status, 'data' => is_array($data) ? $data : []];
        }

        throw new \RuntimeException("HubSpot: reintentos agotados en $method $path");
    }

    // recorre todas las empresas con nif_cif_vat informado con paginación
    public function searchCompaniesWithVat(array $properties): \Generator
    {
        $after = null;

        do {
            $body = [
                'filterGroups' => [[
                    'filters' => [[
                        'propertyName' => 'nif_cif_vat',
                        'operator'     => 'HAS_PROPERTY',
                    ]],
                ]],
                'properties' => $properties,
                'sorts'      => [[
                    'propertyName' => 'hs_object_id',
                    'direction'    => 'ASCENDING',
                ]],
                'limit' => 100,
            ];

            if ($after !== null) {
                $body['after'] = $after;
            }

            $res = $this->request('POST', self::OBJECTS . '/search', ['json' => $body]);

            if ($res['status'] !== 200) {
                throw new \RuntimeException(
                    'HubSpot search falló (' . $res['status'] . '): ' . json_encode($res['data'])
                );
            }

            // devuelve empresa por empresa
            foreach ($res['data']['results'] ?? [] as $company) {
                yield $company;
            }

            // siguiente página
            $after = $res['data']['paging']['next']['after'] ?? null;
        } while ($after !== null);
    }

    // actualiza empresas en batch (máx 100 por petición)
    public function batchUpdateCompanies(array $inputs): array
    {
        if (empty($inputs)) {
            return ['status' => 200, 'data' => []];
        }

        $res = $this->request('POST', self::OBJECTS . '/batch/update', ['json' => ['inputs' => $inputs]]);

        return $res;
    }
}
