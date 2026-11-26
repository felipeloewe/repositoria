<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * OCR / extracción de texto para desarrollo de créditos.
 * Usa proc_open (disponible en bookletcl aunque exec esté deshabilitado).
 * Cuando Softnet instale tesseract/pdftotext, funciona sin más cambios de código.
 */
final class EquityOcr
{
    /**
     * @return array{
     *   proc_open: bool,
     *   tesseract: ?string,
     *   pdftotext: ?string,
     *   pdftoppm: ?string,
     *   listo: bool,
     *   mensaje: string
     * }
     */
    public static function estado(): array
    {
        $proc = function_exists('proc_open');
        $tess = $proc ? self::which('tesseract') : null;
        $pdf  = $proc ? self::which('pdftotext') : null;
        $ppm  = $proc ? self::which('pdftoppm') : null;
        $listo = $proc && ($tess !== null || $pdf !== null);

        if (! $proc) {
            $msg = 'El hosting bloquea la ejecución de binarios (proc_open).';
        } elseif ($tess === null && $pdf === null) {
            $msg = 'OCR pendiente: Tesseract / pdftotext aún no están instalados en el servidor. Puedes subir el archivo como respaldo; cuando Softnet los active, la lectura automática quedará operativa.';
        } elseif ($tess !== null && $pdf !== null) {
            $msg = 'OCR listo (Tesseract + pdftotext).';
        } elseif ($tess !== null) {
            $msg = 'OCR de imágenes listo (Tesseract). PDF digital limitado sin pdftotext.';
        } else {
            $msg = 'Lectura de PDF con texto lista (pdftotext). Para JPG escaneado falta Tesseract.';
        }

        return [
            'proc_open'  => $proc,
            'tesseract'  => $tess,
            'pdftotext'  => $pdf,
            'pdftoppm'   => $ppm,
            'listo'      => $listo,
            'mensaje'    => $msg,
        ];
    }

    /**
     * @return array{ok: bool, text?: string, error?: string, motor?: string}
     */
    public static function textoDesdeArchivo(string $path, string $mimeOrExt = ''): array
    {
        if (! is_file($path)) {
            return ['ok' => false, 'error' => 'Archivo temporal no encontrado.'];
        }
        $hint = strtolower($mimeOrExt . ' ' . $path);
        $isPdf = str_contains($hint, 'pdf') || str_ends_with(strtolower($path), '.pdf');
        $isImg = (bool) preg_match('/jpe?g|png|webp|gif|tiff?/i', $hint);

        if ($isPdf) {
            $text = self::pdfTexto($path);
            if (trim($text) !== '') {
                return ['ok' => true, 'text' => $text, 'motor' => 'pdftotext|embedded'];
            }
            // Escaneado: rasterizar + OCR si hay herramientas
            $ocr = self::pdfOcr($path);
            if (($ocr['ok'] ?? false) && trim((string) ($ocr['text'] ?? '')) !== '') {
                return $ocr;
            }

            return [
                'ok'    => false,
                'error' => self::estado()['tesseract'] === null
                    ? 'PDF sin texto seleccionable. Cuando Softnet instale Tesseract se podrá OCR. Mientras, el archivo queda como respaldo o sube Excel/CSV.'
                    : 'No se pudo OCR el PDF escaneado. Revisa el archivo o sube Excel/CSV.',
            ];
        }

        if ($isImg || self::pareceImagen($path)) {
            return self::imagenOcr($path);
        }

        return ['ok' => false, 'error' => 'Tipo no soportado para OCR.'];
    }

    /**
     * @return array{ok: bool, text?: string, error?: string, motor?: string}
     */
    public static function textoDesdeBinary(string $binary, string $filename, string $mime = ''): array
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION) ?: 'bin');
        $tmp = tempnam(sys_get_temp_dir(), 'eqocr');
        if ($tmp === false) {
            return ['ok' => false, 'error' => 'No se pudo crear temporal.'];
        }
        $path = $tmp . '.' . preg_replace('/[^a-z0-9]/', '', $ext);
        @unlink($tmp);
        file_put_contents($path, $binary);
        try {
            return self::textoDesdeArchivo($path, $mime . ' ' . $ext);
        } finally {
            @unlink($path);
        }
    }

    private static function pareceImagen(string $path): bool
    {
        if (! function_exists('finfo_open')) {
            return false;
        }
        $f = finfo_open(FILEINFO_MIME_TYPE);
        if ($f === false) {
            return false;
        }
        $mime = (string) finfo_file($f, $path);
        finfo_close($f);

        return str_starts_with($mime, 'image/');
    }

    private static function pdfTexto(string $path): string
    {
        $bin = self::which('pdftotext');
        if ($bin !== null) {
            $out = $path . '.txt';
            $r = self::run($bin . ' -layout ' . escapeshellarg($path) . ' ' . escapeshellarg($out));
            $text = is_file($out) ? (string) file_get_contents($out) : '';
            @unlink($out);
            if (trim($text) !== '') {
                return $text;
            }
            unset($r);
        }

        // Fallback embebido (limitado)
        $binary = (string) file_get_contents($path);
        $chunks = [];
        if (preg_match_all('/\((?:\\\\.|[^\\\\)]){2,}\)/s', $binary, $m)) {
            foreach ($m[0] as $tok) {
                $s = substr($tok, 1, -1);
                $s = str_replace(['\\n', '\\r', '\\t', '\\(', '\\)'], ["\n", "\r", "\t", '(', ')'], $s);
                if (preg_match('/[A-Za-zÁÉÍÓÚáéíóúñÑ0-9]/u', $s) === 1) {
                    $chunks[] = $s;
                }
            }
        }

        return implode("\n", $chunks);
    }

    /**
     * @return array{ok: bool, text?: string, error?: string, motor?: string}
     */
    private static function pdfOcr(string $path): array
    {
        $tess = self::which('tesseract');
        if ($tess === null) {
            return ['ok' => false, 'error' => 'Tesseract no instalado.'];
        }
        $ppm = self::which('pdftoppm');
        if ($ppm === null) {
            return [
                'ok'    => false,
                'error' => 'Para OCR de PDF escaneado hace falta pdftoppm (poppler). Tesseract solo no basta.',
            ];
        }
        $prefix = sys_get_temp_dir() . '/eqocr_' . bin2hex(random_bytes(4));
        // Máx. 8 páginas para no saturar el hosting
        $r = self::run($ppm . ' -png -r 200 -f 1 -l 8 ' . escapeshellarg($path) . ' ' . escapeshellarg($prefix));
        $pages = glob($prefix . '*.png') ?: [];
        if ($pages === []) {
            return ['ok' => false, 'error' => 'No se pudo rasterizar el PDF para OCR. ' . ($r['err'] ?? '')];
        }
        sort($pages);
        $texts = [];
        foreach ($pages as $img) {
            $one = self::imagenOcr($img);
            if (($one['ok'] ?? false) && trim((string) ($one['text'] ?? '')) !== '') {
                $texts[] = (string) $one['text'];
            }
            @unlink($img);
        }
        if ($texts === []) {
            return ['ok' => false, 'error' => 'OCR no extrajo texto del PDF escaneado.'];
        }

        return ['ok' => true, 'text' => implode("\n\n", $texts), 'motor' => 'pdftoppm+tesseract'];
    }

    /**
     * @return array{ok: bool, text?: string, error?: string, motor?: string}
     */
    private static function imagenOcr(string $path): array
    {
        $tess = self::which('tesseract');
        if ($tess === null) {
            return [
                'ok'    => false,
                'error' => 'Tesseract aún no está instalado en el servidor. El archivo se puede guardar como respaldo; reintenta el import cuando Softnet confirme la instalación.',
            ];
        }
        $outBase = sys_get_temp_dir() . '/eqtess_' . bin2hex(random_bytes(4));
        // spa+eng si están; si no, eng
        $langs = self::tesseractLangs($tess);
        $cmd = $tess . ' ' . escapeshellarg($path) . ' ' . escapeshellarg($outBase)
            . ' -l ' . escapeshellarg($langs) . ' --psm 6';
        $r = self::run($cmd);
        $txtFile = $outBase . '.txt';
        $text = is_file($txtFile) ? (string) file_get_contents($txtFile) : '';
        @unlink($txtFile);
        if (trim($text) === '') {
            return [
                'ok'    => false,
                'error' => 'OCR sin texto. ' . trim(($r['err'] ?? '') . ' ' . ($r['out'] ?? '')),
            ];
        }

        return ['ok' => true, 'text' => $text, 'motor' => 'tesseract:' . $langs];
    }

    private static function tesseractLangs(string $tessBin): string
    {
        $r = self::run($tessBin . ' --list-langs');
        $blob = strtolower(($r['out'] ?? '') . "\n" . ($r['err'] ?? ''));
        $hasSpa = str_contains($blob, 'spa');
        $hasEng = str_contains($blob, 'eng');
        if ($hasSpa && $hasEng) {
            return 'spa+eng';
        }
        if ($hasSpa) {
            return 'spa';
        }
        if ($hasEng) {
            return 'eng';
        }

        return 'eng';
    }

    private static function which(string $bin): ?string
    {
        if (! function_exists('proc_open')) {
            return null;
        }
        foreach (['/usr/bin/' . $bin, '/usr/local/bin/' . $bin] as $abs) {
            if (is_file($abs)) {
                return $abs;
            }
        }
        $r = self::run('command -v ' . escapeshellarg($bin));
        $path = trim((string) ($r['out'] ?? ''));
        if ($path !== '' && str_contains($path, '/')) {
            return explode("\n", $path)[0];
        }

        return null;
    }

    /**
     * @return array{code: int, out: string, err: string}
     */
    private static function run(string $cmd): array
    {
        if (! function_exists('proc_open')) {
            return ['code' => 127, 'out' => '', 'err' => 'proc_open unavailable'];
        }
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = @proc_open($cmd, $descriptors, $pipes, null, null, ['bypass_shell' => false]);
        if (! is_resource($proc)) {
            return ['code' => 127, 'out' => '', 'err' => 'proc_open failed'];
        }
        fclose($pipes[0]);
        $out = stream_get_contents($pipes[1]) ?: '';
        fclose($pipes[1]);
        $err = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[2]);
        $code = proc_close($proc);

        return ['code' => $code, 'out' => $out, 'err' => $err];
    }
}
