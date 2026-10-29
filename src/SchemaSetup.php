<?php
declare(strict_types=1);

namespace Vexco;

final class SchemaSetup
{
    public function __construct(private HubSpotClient $hs, private string $firstName)
    {
    }

    public function run(): void
    {
        $name = trim($this->firstName);
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '', $name));
        $displayName = ucfirst($name);

        $groupDisplayName = 'Ejercicio Vexco – ' . $displayName;
        $groupInternalName = 'vies_' . $slug . '_group';

        
        $groupBody = [
            'name'         => $groupInternalName,
            'label'        => $groupDisplayName,
            'displayOrder' => 10,
        ];

        $resGroup = $this->hs->request('POST', 'crm/v3/properties/companies/groups', ['json' => $groupBody]);

        if ($resGroup['status'] === 201) {
            echo "Grupo creado: {$groupDisplayName}\n";
        } elseif ($resGroup['status'] === 409) {
            echo "Grupo ya existente: {$groupDisplayName} (OK)\n";
        } else {
            throw new \RuntimeException('Error creando grupo (' . $resGroup['status'] . '): ' . json_encode($resGroup['data']));
        }

       
        $prefix = 'vies_' . $slug . '_';

        $this->createProperty($groupInternalName, $prefix . 'estado', 'VIES ' . $displayName . ' – Estado');
        $this->createProperty($groupInternalName, $prefix . 'fecha',  'VIES ' . $displayName . ' – Fecha validación');
        $this->createProperty($groupInternalName, $prefix . 'motivo', 'VIES ' . $displayName . ' – Motivo');
    }

    private function createProperty(string $groupInternalName, string $internalName, string $label): void
    {
        $body = [
            'name' => $internalName,
            'label' => $label,
            'type' => 'string',
            'fieldType' => 'text',
            'groupName' => $groupInternalName,
            'description' => 'Resultado validación VIES - Ejercicio',
            'options' => [],
        ];

        $res = $this->hs->request('POST', 'crm/v3/properties/companies', ['json' => $body]);

        if ($res['status'] === 201) {
            echo "Propiedad creada: {$internalName} ({$label})\n";
        } elseif ($res['status'] === 409) {
            echo "Propiedad ya existente: {$internalName} (OK)\n";
        } else {
            throw new \RuntimeException('Error creando propiedad ' . $internalName . ' (' . $res['status'] . '): ' . json_encode($res['data']));
        }
    }
}