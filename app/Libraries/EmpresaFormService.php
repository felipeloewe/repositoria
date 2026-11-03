<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\EmpresaFormCampoModel;
use App\Models\EmpresaFormInstanciaModel;
use App\Models\EmpresaFormPlantillaModel;
use App\Models\EmpresaFormShareModel;
use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * Formularios de empresa (menú Formulario) → PDF → documento ambito=general.
 */
final class EmpresaFormService
{
    public const TIPOS = FormEngine::TIPOS;

    public const ESTADO_PENDIENTE  = FormEngine::ESTADO_PENDIENTE;
    public const ESTADO_COMPLETADO = FormEngine::ESTADO_COMPLETADO;
    public const ESTADO_ANULADO    = FormEngine::ESTADO_ANULADO;

    public const ORIGEN_INTERNO = 'empresa';
    public const ORIGEN_PUBLICO = FormEngine::ORIGEN_PUBLICO;

    public const TIPO_CODIGO = 'FORM_EMPRESA';

    public static function slugifyClave(string $etiqueta): string
    {
        return FormEngine::slugifyClave($etiqueta);
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public static function shareIsUsable(array $share): array
    {
        return FormEngine::shareIsUsable($share);
    }

    public static function mintPassword(): string
    {
        return FormEngine::mintPassword();
    }

    /** ¿El usuario puede ver/usar el menú Formulario de empresa? */
    public static function canAccess(array $memb): bool
    {
        return Roles::canUploadDocs((string) ($memb['rol'] ?? ''));
    }

    /**
     * @param list<array<string, mixed>> $campos
     * @param array<string, mixed> $post
     * @param array<string, UploadedFile|null> $files
     * @return array{instancia: array<string, mixed>, documento_id: string}
     */
    public function completeFill(
        array $plantilla,
        array $campos,
        string $titulo,
        string $origen,
        ?string $filledByUsuarioId,
        string $actorUsuarioId,
        ?string $shareId,
        array $post,
        array $files,
        ?string $empresaIdOverride = null
    ): array {
        return (new FormEngine())->completeFill(
            $plantilla,
            $campos,
            $titulo,
            $origen,
            $filledByUsuarioId,
            $actorUsuarioId,
            $shareId,
            $post,
            $files,
            $empresaIdOverride,
            DocumentAcl::AMBITO_GENERAL,
            self::TIPO_CODIGO,
            'Formulario empresa',
            'PDF generado desde Formulario (empresa)',
            'Repositoria Formulario',
            'adjunto',
            'formulario-empresa',
            'Formulario empresa completado (PDF)',
            'Versión inicial (formulario empresa)',
            EmpresaFormInstanciaModel::class,
            EmpresaFormShareModel::class
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function camposOrdenados(string $plantillaId): array
    {
        return model(EmpresaFormCampoModel::class)
            ->where('plantilla_id', $plantillaId)
            ->orderBy('orden', 'ASC')
            ->orderBy('created_at', 'ASC')
            ->findAll();
    }

    public static function plantillaOfEmpresa(string $id, string $empresaId): ?array
    {
        $row = model(EmpresaFormPlantillaModel::class)->find($id);
        if (! is_array($row)) {
            return null;
        }

        if (RecursoSistema::isGenerica($row)) {
            $clave = (string) $row['clave_sistema'];
            if ((int) ($row['activo'] ?? 0) !== 1) {
                return null;
            }
            if (! RecursoSistema::isActivo($empresaId, RecursoSistema::TIPO_FORM_PLANTILLA_EMPRESA, $clave)) {
                return null;
            }

            return $row;
        }

        if ((string) ($row['empresa_id'] ?? '') !== $empresaId) {
            return null;
        }

        return $row;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function plantillasVisibles(string $empresaId): array
    {
        $custom = model(EmpresaFormPlantillaModel::class)
            ->where('empresa_id', $empresaId)
            ->orderBy('nombre', 'ASC')
            ->findAll();

        $generic = RecursoSistema::plantillasGenericasEmpresaActivas($empresaId);

        $merged = array_merge($generic, $custom);
        usort($merged, static fn (array $a, array $b): int => strcasecmp((string) $a['nombre'], (string) $b['nombre']));

        return $merged;
    }

    public static function plantillaUsable(array $plantilla, string $empresaId): bool
    {
        if ((int) ($plantilla['activo'] ?? 0) !== 1) {
            return false;
        }
        if (RecursoSistema::isGenerica($plantilla)) {
            return RecursoSistema::isActivo(
                $empresaId,
                RecursoSistema::TIPO_FORM_PLANTILLA_EMPRESA,
                (string) $plantilla['clave_sistema']
            );
        }

        return (string) ($plantilla['empresa_id'] ?? '') === $empresaId;
    }
}
