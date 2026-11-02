<?php

declare(strict_types=1);

namespace App\Commands;

use App\Libraries\PromoverSucursalService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * Admin-central CLI: promote a Sucursal/Filial into its own Empresa tenant.
 *
 * Usage:
 *   php spark repositoria:promover-sucursal <sucursalId> [--nombre=] [--slug=] [--actor=]
 */
class PromoverSucursal extends BaseCommand
{
    protected $group       = 'Repositoria';
    protected $name        = 'repositoria:promover-sucursal';
    protected $description = 'Promueve una Sucursal/Filial (modo b) a Empresa tenant (modo a).';
    protected $usage       = 'repositoria:promover-sucursal <sucursalId> [options]';
    protected $arguments   = [
        'sucursalId' => 'ID de la sucursal/filial a promover',
    ];
    protected $options = [
        '--nombre' => 'Nombre de la nueva empresa (default: nombre de la sucursal)',
        '--slug'   => 'Slug único (default: slugify del nombre)',
        '--actor'  => 'usuario_id del admin central que ejecuta (auditoría)',
    ];

    public function run(array $params)
    {
        $sucursalId = $params[0] ?? CLI::getOption('sucursal') ?? null;
        if (! is_string($sucursalId) || trim($sucursalId) === '') {
            CLI::error('Uso: php spark repositoria:promover-sucursal <sucursalId> [--nombre=] [--slug=] [--actor=]');

            return EXIT_ERROR;
        }

        $options = [
            'nombre'   => CLI::getOption('nombre') ?: null,
            'slug'     => CLI::getOption('slug') ?: null,
            'actor_id' => CLI::getOption('actor') ?: null,
        ];
        // Drop nulls so service defaults apply
        $options = array_filter($options, static fn ($v) => $v !== null && $v !== '');

        try {
            $result = (new PromoverSucursalService())->promover(trim($sucursalId), $options);
        } catch (Throwable $e) {
            CLI::error('Promoción fallida: ' . $e->getMessage());

            return EXIT_ERROR;
        }

        CLI::write('Promoción OK', 'green');
        CLI::write('  origen_sucursal_id    = ' . $result['origen_sucursal_id']);
        CLI::write('  origen_empresa_id     = ' . $result['origen_empresa_id']);
        CLI::write('  nueva_empresa_id      = ' . $result['nueva_empresa_id']);
        CLI::write('  nueva_departamento_id = ' . $result['nueva_departamento_id']);
        CLI::write('  nueva_sucursal_id     = ' . $result['nueva_sucursal_id']);
        CLI::write('  documentos_movidos    = ' . $result['documentos_movidos']);
        CLI::write('  secciones_movidas     = ' . $result['secciones_movidas']);
        CLI::write('  membresias_creadas    = ' . $result['membresias_creadas']);
        CLI::write('  audit_id              = ' . $result['audit_id']);

        return EXIT_SUCCESS;
    }
}
