<?php
declare(strict_types=1);

namespace Vexco;

use Dotenv\Dotenv;

final class Config
{
    public static function load(string $root): array
    {
        Dotenv::createImmutable($root)->safeLoad();

        $token = $_ENV['HUBSPOT_TOKEN'] ?? '';
        if ($token === '') {
            throw new \RuntimeException('Falta HUBSPOT_TOKEN en el archivo .env');
        }

        return [
            'token'    => $token,
            'base_url' => $_ENV['HUBSPOT_BASE_URL'] ?? 'https://api.hubapi.com',
        ];
    }
}