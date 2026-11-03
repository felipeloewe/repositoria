<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\SocioFormCampoModel;
use App\Models\SocioFormInstanciaModel;
use App\Models\SocioFormPlantillaModel;
use App\Models\SocioFormShareModel;
use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * Completar instancias de formularios de socios → PDF → documento ambito=socio.
 * Delega el motor a FormEngine (compartido con formularios de empresa).
 */
final class SocioFormService
{
    public const TIPOS = FormEngine::TIPOS;

    public const ESTADO_PENDIENTE  = FormEngine::ESTADO_PENDIENTE;
    public const ESTADO_COMPLETADO = FormEngine::ESTADO_COMPLETADO;
    public const ESTADO_ANULADO    = FormEngine::ESTADO_ANULADO;

    public const ORIGEN_SOCIO   = 'socio';
    public const ORIGEN_PUBLICO = FormEngine::ORIGEN_PUBLICO;

    public const TIPO_CODIGO = 'FORM_SOCIO';

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

    /**
     * @param list<array<string, mixed>> $campos
     * @param array<string, mixed> $post
     * @param array<string, UploadedFile|null> $files
     * @return array{respuestas: array<string, mixed>, rows: list<array{label: string, value: string}>}
     */
    public function collectRespuestas(
        string $empresaId,
        string $actorUsuarioId,
        array $campos,
        array $post,
        array $files
    ): array {
        $engine = new FormEngine();
        $storeUpload = static function (
            string $empId,
            string $actorId,
            string $titulo,
            UploadedFile $file
        ) use ($engine): string {
            $binary = file_get_contents($file->getTempName());
            if ($binary === false) {
                throw new \RuntimeException('No se pudo leer el archivo subido.');
            }

            return $engine->storeBinaryAsDoc(
                $empId,
                $actorId,
                $titulo,
                $binary,
                $file->getClientName(),
                $file->getMimeType() ?: 'application/octet-stream',
                DocumentAcl::AMBITO_SOCIO,
                self::TIPO_CODIGO,
                'Formulario socio',
                'PDF generado desde Formularios de Socios',
                '',
                'Versión inicial (formulario socio)'
            );
        };

        return $engine->collectRespuestas(
            $empresaId,
            $actorUsuarioId,
            $campos,
            $post,
            $files,
            $storeUpload,
            'adjunto socio'
        );
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
            DocumentAcl::AMBITO_SOCIO,
            self::TIPO_CODIGO,
            'Formulario socio',
            'PDF generado desde Formularios de Socios',
            'Repositoria Socios',
            'adjunto socio',
            'formulario-socio',
            'Formulario socio completado (PDF)',
            'Versión inicial (formulario socio)',
            SocioFormInstanciaModel::class,
            SocioFormShareModel::class
        );
    }

    public function ensureTipoFormulario(string $empresaId): array
    {
        return (new FormEngine())->ensureTipo(
            $empresaId,
            self::TIPO_CODIGO,
            'Formulario socio',
            'PDF generado desde Formularios de Socios'
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function camposOrdenados(string $plantillaId): array
    {
        return model(SocioFormCampoModel::class)
            ->where('plantilla_id', $plantillaId)
            ->orderBy('orden', 'ASC')
            ->orderBy('created_at', 'ASC')
            ->findAll();
    }

    /**
     * Plantilla custom de la empresa, o genérica activa para esa empresa.
     */
    public static function plantillaOfEmpresa(string $id, string $empresaId): ?array
    {
        $row = model(SocioFormPlantillaModel::class)->find($id);
        if (! is_array($row)) {
            return null;
        }

        if (RecursoSistema::isGenerica($row)) {
            $clave = (string) $row['clave_sistema'];
            if ((int) ($row['activo'] ?? 0) !== 1) {
                return null;
            }
            if (! RecursoSistema::isActivo($empresaId, RecursoSistema::TIPO_FORM_PLANTILLA, $clave)) {
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
     * Custom de la empresa + genéricas activas (para listados).
     *
     * @return list<array<string, mixed>>
     */
    public static function plantillasVisibles(string $empresaId): array
    {
        $custom = model(SocioFormPlantillaModel::class)
            ->where('empresa_id', $empresaId)
            ->orderBy('nombre', 'ASC')
            ->findAll();

        $generic = RecursoSistema::plantillasGenericasActivas($empresaId);

        $merged = array_merge($generic, $custom);
        usort($merged, static fn (array $a, array $b): int => strcasecmp((string) $a['nombre'], (string) $b['nombre']));

        return $merged;
    }

    /** ¿La plantilla está usable para rellenar/compartir en esta empresa? */
    public static function plantillaUsable(array $plantilla, string $empresaId): bool
    {
        if ((int) ($plantilla['activo'] ?? 0) !== 1) {
            return false;
        }
        if (RecursoSistema::isGenerica($plantilla)) {
            return RecursoSistema::isActivo(
                $empresaId,
                RecursoSistema::TIPO_FORM_PLANTILLA,
                (string) $plantilla['clave_sistema']
            );
        }

        return (string) ($plantilla['empresa_id'] ?? '') === $empresaId;
    }
}
