# HubSpot VIES Validator

Script en PHP que recorre las empresas de HubSpot con la propiedad `nif_cif_vat`, valida cada número contra la API pública VIES de la Comisión Europea y guarda el resultado en la propia empresa, en tres propiedades nuevas creadas por API. Al terminar muestra un resumen por consola.

Prueba técnica del proceso de selección de Vexco. Autor: Joan.

## Requisitos

- PHP 8.0 o superior con las extensiones `curl`, `mbstring` y `openssl` activas
- [Composer](https://getcomposer.org/)
- Conexión a internet
- Tener una **service key** de hubspot

## Instalación

```bash
composer install
```

Crea un archivo .env en la raiz con el token y la url de la api

```env
HUBSPOT_TOKEN=[service_key]
HUBSPOT_BASE_URL=https://api.hubapi.com
```


## Ejecución

### 1. Crear el grupo y las propiedades (una vez)

```bash
php setup.php
```

Crea por API el grupo **Ejercicio Vexco – [nombre_deseado]** y estas propiedades de texto de una línea en *Companies*:

| Nombre interno | Etiqueta |
| --- | --- |
| `vies_[nombre]_estado` | VIES [nombre] – Estado |
| `vies_[nombre]_fecha` | VIES [nombre] – Fecha validación |
| `vies_[nombre]_motivo` | VIES [nombre] – Motivo |

Si ya existen, lo detecta (HTTP 409) y continúa, así que se puede repetir sin problema.

### 2. Validar y guardar los resultados

```bash
php run.php
```

Se puede ejecutar todas las veces que haga falta . Al final imprime el resumen:

```
========================================
RESUMEN VALIDACIÓN VIES
========================================
Total empresas con nif_cif_vat: ...
Válidas (VALIDO):                ...
Inválidas (INVALIDO):            ...
Con error (ERROR_TEMPORAL):      ...
Omitidas (OMITIDO):              ...
========================================
```

### Prueba manual (opcional)

```bash
php test_vies.php
```

Pasa una lista de casos reales y de borde por `VatParser` y, si procede, por VIES, e imprime el resultado de cada uno. Hace llamadas a VIES.

## Estados guardados

| Estado | Cuándo | Qué se guarda |
| --- | --- | --- |
| `VALIDO` | VIES devuelve `valid: true` | Estado y fecha de validación en ISO 8601 |
| `INVALIDO` | VIES devuelve `valid: false` | Estado y motivo |
| `ERROR_TEMPORAL` | Timeout, sin conexión, HTTP 5xx o 429, respuesta vacía o inesperada | Estado y motivo. **No** se marca como inválida y se reintenta en la siguiente ejecución |
| `OMITIDO` | Dato vacío, basura, sin prefijo de país o país fuera de VIES | Estado y motivo |

## Cómo funciona

- **Lectura:** búsqueda de empresas con `nif_cif_vat` informado (`HAS_PROPERTY`), paginada con `after`, 100 por página y ordenada por `hs_object_id`.
- **Normalización (`VatParser`):** pasa a mayúsculas y quita espacios, puntos, guiones y cualquier otro carácter que no sea letra o número. Después decide si se valida con VIES o se omite.
- **Validación (`ViesClient`):** `POST https://ec.europa.eu/taxation_customs/vies/rest-api/check-vat-number` con el código de país y el número.
- **Escritura:** `batch/update` de HubSpot en lotes de 100.
- **Rate limit y errores:** `HubSpotClient` reintenta hasta 5 veces ante HTTP 429, 423 y 5xx y ante errores de conexión, respetando `Retry-After` o con espera exponencial (mínimo 2 s, máximo 30 s).

## Idempotencia

- Si una empresa ya tiene `VALIDO`, `INVALIDO` u `OMITIDO`, no se vuelve a consultar ni se pisa su resultado.
- Solo se reprocesan las empresas sin estado y las que están en `ERROR_TEMPORAL`.
- Los datos de partida quedan intactos: solo se escriben las propiedades `vies_joan_*`, nunca `nif_cif_vat` ni ninguna otra.

## Estructura del proyecto

```
.
├── setup.php             # crea grupo y propiedades por API
├── run.php               # valida y guarda resultados
├── test_vies.php         # prueba manual del parser y de VIES
├── composer.json
├── .env.template         # plantilla de variables de entorno
└── src/
    ├── Config.php        # carga .env
    ├── HubSpotClient.php # cliente HubSpot: paginación, batch, 429/423/5xx
    ├── ViesClient.php    # cliente VIES
    ├── VatParser.php     # normalización y decisión validar/omitir
    ├── SchemaSetup.php   # creación de grupo y propiedades
    └── Processor.php     # flujo principal y resumen
```

## Documentación

El planteamiento, los scopes elegidos, el tratamiento de errores y las decisiones están en el documento PDF adjunto en el repo [Documento](docs/Documentacion-HubSpot-VIES-Joan.pdf)
