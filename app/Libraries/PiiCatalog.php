<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Inventario de PII / datos identificables.
 *
 * Capa de identidad (vault lógico): usuarios, empresas, equity_personas.
 * Dominio operativo debe preferir solo IDs; lo listado en collateral
 * son fugas conocidas a limpiar en fases siguientes.
 */
final class PiiCatalog
{
    public const LAYER_IDENTITY   = 'identity';
    public const LAYER_COLLATERAL = 'collateral';

    /**
     * Campos PII de la capa de identidad (borrables en scrub).
     *
     * @return array<string, list<string>>
     */
    public static function identityFields(): array
    {
        return [
            'usuarios' => [
                'email', 'nombre', 'alias', 'cargo', 'rut',
                'password_hash', 'email_verify_token',
            ],
            'empresas' => [
                'nombre', 'slug', 'rut',
            ],
            'equity_personas' => [
                'nombre', 'apellido', 'apellido_materno', 'rut', 'email', 'cargo', 'notas',
                'fecha_nacimiento', 'sexo', 'nacionalidad', 'nacionalidad_detalle',
                'estado_civil', 'regimen_patrimonial', 'lugar_habita',
                'nivel_estudios', 'profesion', 'universidad_instituto',
                'dir_calle', 'dir_numero', 'dir_otros', 'dir_comuna', 'dir_ciudad', 'dir_region',
                'telefono_particular', 'telefono_celular',
                'rut_empleador', 'nombre_empleador', 'giro_empleador',
                'dir_com_calle', 'dir_com_numero', 'dir_com_otros',
                'dir_com_comuna', 'dir_com_ciudad', 'dir_com_region',
                'tel_comercial', 'tel_anexo',
                'segunda_actividad',
                'empleo_ant_cargo', 'empleo_ant_empleador',
            ],
        ];
    }

    /**
     * Fugas colaterales (aún no scrub automático): no viven en el vault
     * pero pueden reidentificar si se conservan tras la baja.
     *
     * @return list<array{table: string, fields: list<string>, note: string}>
     */
    public static function collateralLeaks(): array
    {
        return [
            [
                'table'  => 'equity_ingresos_contratos',
                'fields' => ['contraparte_nombre', 'contraparte_rut'],
                'note'   => 'Terceros / contraparto en ingresos',
            ],
            [
                'table'  => 'equity_bienes_raices',
                'fields' => ['direccion', 'descripcion', 'arrendatario', 'rol'],
                'note'   => 'Dirección y arrendatario pueden ser identificables',
            ],
            [
                'table'  => 'equity_sociedades',
                'fields' => ['nombre_sociedad', 'rut_sociedad'],
                'note'   => 'Razón social / RUT societario',
            ],
            [
                'table'  => 'equity_pasivos',
                'fields' => ['direccion_garantia', 'tomador_nombre', 'tomador_rut'],
                'note'   => 'Dirección de garantía y tomador de deuda indirecta (nombre/RUT de tercero)',
            ],
            [
                'table'  => 'documentos / equity_respaldos',
                'fields' => ['archivo', 'nombre'],
                'note'   => 'PDFs y respaldos pueden contener PII; requieren retención aparte',
            ],
            [
                'table'  => 'correo / invitaciones',
                'fields' => ['destinatario'],
                'note'   => 'Historial de mails y enlaces de verificación',
            ],
            [
                'table'  => 'equity_persona_bancos / cuentas / tarjetas',
                'fields' => ['banco_nombre', 'numero_cuenta', 'ultimos_4', 'fecha_vencimiento', 'monto_aprobado_clp', 'monto_aprobado_usd'],
                'note'   => 'Antecedentes bancarios: se borran al scrub de la persona',
            ],
        ];
    }

    /**
     * @return list<array{layer: string, table: string, fields: list<string>, note?: string}>
     */
    public static function inventory(): array
    {
        $out = [];
        foreach (self::identityFields() as $table => $fields) {
            $out[] = [
                'layer'  => self::LAYER_IDENTITY,
                'table'  => $table,
                'fields' => $fields,
                'note'   => 'Vault lógico: scrub al dar de baja',
            ];
        }
        foreach (self::collateralLeaks() as $row) {
            $out[] = [
                'layer'  => self::LAYER_COLLATERAL,
                'table'  => $row['table'],
                'fields' => $row['fields'],
                'note'   => $row['note'],
            ];
        }

        return $out;
    }
}
