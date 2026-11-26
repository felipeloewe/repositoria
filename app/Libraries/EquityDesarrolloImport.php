<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Importa desarrollo de crédito desde CSV / Excel (.xlsx) / PDF / imagen (OCR).
 * Solo toma columnas reconocidas presentes en el archivo.
 */
final class EquityDesarrolloImport
{
    /** @var array<string, list<string>> */
    private const ALIASES = [
        'fecha'            => ['fecha', 'date', 'vencimiento', 'fecha vencimiento', 'fecha cuota', 'fecha pago', 'f.venc', 'f venc'],
        'dividendo_n'      => ['dividendo', 'dividendo n', 'n dividendo', 'n° dividendo', 'nro dividendo', 'numero dividendo', 'nº', 'n°', 'nro', 'cuota n', 'n cuota', 'div'],
        'cuota_uf'         => ['cuota uf', 'dividendo uf', 'total dividendo uf', 'valor cuota uf', 'cuota (uf)'],
        'cuota_clp'        => ['cuota', 'cuota clp', 'cuota $', 'cuota pesos', 'dividendo clp', 'dividendo $', 'total dividendo', 'valor cuota', 'monto cuota', 'cuota (clp)', 'cuota ($ )'],
        'amortizacion_uf'  => ['amortizacion uf', 'amortización uf', 'capital uf', 'amort. uf'],
        'amortizacion_clp' => ['amortizacion', 'amortización', 'amortizacion clp', 'amortización clp', 'amortizacion $', 'amortización $', 'capital', 'capital clp', 'amort. $'],
        'interes_uf'       => ['interes uf', 'interés uf', 'int uf'],
        'interes_clp'      => ['interes', 'interés', 'interes clp', 'interés clp', 'interes $', 'interés $', 'int $'],
        'saldo_uf'         => ['saldo uf', 'saldo insoluto uf', 'capital adeudado uf'],
        'saldo_clp'        => ['saldo', 'saldo clp', 'saldo $', 'saldo insoluto', 'saldo insoluto clp', 'capital adeudado'],
    ];

    /**
     * @return array{ok: bool, error?: string, filas?: list<array<string, mixed>>, columnas?: list<string>, detalle?: string}
     */
    public static function parse(string $filename, string $binary, string $mime = ''): array
    {
        $name = strtolower($filename);
        $ext  = pathinfo($name, PATHINFO_EXTENSION);
        $mime = strtolower($mime);

        if ($ext === 'csv' || str_contains($mime, 'csv') || str_contains($mime, 'text/plain')) {
            return self::parseDelimited($binary);
        }
        if ($ext === 'xlsx' || str_contains($mime, 'spreadsheetml') || str_contains($mime, 'excel')) {
            if ($ext === 'xls' && ! str_contains($mime, 'openxml')) {
                return ['ok' => false, 'error' => 'Usa .xlsx o .csv (Excel 97 .xls no soportado).'];
            }

            return self::parseXlsx($binary);
        }
        if ($ext === 'xls') {
            return ['ok' => false, 'error' => 'Usa .xlsx o .csv (Excel 97 .xls no soportado).'];
        }
        if ($ext === 'pdf' || str_contains($mime, 'pdf')) {
            return self::parsePdf($binary, $filename);
        }
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'tif', 'tiff', 'gif'], true)
            || str_starts_with($mime, 'image/')) {
            return self::parseImagen($binary, $filename, $mime);
        }

        // Heurística por contenido
        if (str_starts_with($binary, 'PK')) {
            return self::parseXlsx($binary);
        }
        if (str_starts_with($binary, '%PDF')) {
            return self::parsePdf($binary, $filename);
        }
        if (str_starts_with($binary, "\xFF\xD8\xFF") || str_starts_with($binary, "\x89PNG")) {
            return self::parseImagen($binary, $filename !== '' ? $filename : 'scan.jpg', $mime);
        }

        return self::parseDelimited($binary);
    }

    /**
     * @return array{ok: bool, error?: string, filas?: list<array<string, mixed>>, columnas?: list<string>, detalle?: string}
     */
    private static function parseImagen(string $binary, string $filename, string $mime = ''): array
    {
        $ocr = EquityOcr::textoDesdeBinary($binary, $filename !== '' ? $filename : 'scan.jpg', $mime);
        if (! ($ocr['ok'] ?? false)) {
            return [
                'ok'      => false,
                'error'   => $ocr['error'] ?? 'No se pudo leer la imagen con OCR.',
                'detalle' => 'El archivo se puede guardar igual como respaldo del desarrollo.',
            ];
        }

        return self::parseTextoOcr((string) ($ocr['text'] ?? ''), 'imagen/' . ($ocr['motor'] ?? 'ocr'));
    }

    /**
     * @return array{ok: bool, error?: string, filas?: list<array<string, mixed>>, columnas?: list<string>, detalle?: string}
     */
    private static function parseDelimited(string $binary): array
    {
        $text = self::toUtf8($binary);
        $lines = preg_split('/\R/u', $text) ?: [];
        $lines = array_values(array_filter($lines, static fn ($l) => trim($l) !== ''));
        if ($lines === []) {
            return ['ok' => false, 'error' => 'Archivo vacío.'];
        }

        $sep = self::detectSeparator($lines[0]);
        $rows = [];
        foreach ($lines as $line) {
            $rows[] = str_getcsv($line, $sep);
        }

        return self::rowsToFilas($rows);
    }

    /**
     * @return array{ok: bool, error?: string, filas?: list<array<string, mixed>>, columnas?: list<string>, detalle?: string}
     */
    private static function parseXlsx(string $binary): array
    {
        if (! class_exists(\ZipArchive::class)) {
            return ['ok' => false, 'error' => 'El servidor no tiene ZipArchive para leer .xlsx. Sube CSV.'];
        }
        $tmp = tempnam(sys_get_temp_dir(), 'eqx');
        if ($tmp === false) {
            return ['ok' => false, 'error' => 'No se pudo crear archivo temporal.'];
        }
        file_put_contents($tmp, $binary);
        $zip = new \ZipArchive();
        if ($zip->open($tmp) !== true) {
            @unlink($tmp);

            return ['ok' => false, 'error' => 'No se pudo abrir el .xlsx.'];
        }

        $shared = [];
        $ss = $zip->getFromName('xl/sharedStrings.xml');
        if (is_string($ss) && $ss !== '') {
            $sx = @simplexml_load_string($ss);
            if ($sx !== false) {
                foreach ($sx->si as $si) {
                    if (isset($si->t)) {
                        $shared[] = (string) $si->t;
                    } else {
                        $parts = [];
                        foreach ($si->r as $r) {
                            $parts[] = (string) ($r->t ?? '');
                        }
                        $shared[] = implode('', $parts);
                    }
                }
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if (! is_string($sheetXml) || $sheetXml === '') {
            // primer sheet*
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $n = $zip->getNameIndex($i);
                if (is_string($n) && preg_match('#xl/worksheets/sheet\d+\.xml$#', $n) === 1) {
                    $sheetXml = $zip->getFromName($n);
                    break;
                }
            }
        }
        $zip->close();
        @unlink($tmp);

        if (! is_string($sheetXml) || $sheetXml === '') {
            return ['ok' => false, 'error' => 'No se encontró la hoja del Excel.'];
        }

        $sheet = @simplexml_load_string($sheetXml);
        if ($sheet === false) {
            return ['ok' => false, 'error' => 'XML de hoja inválido.'];
        }

        $grid = [];
        foreach ($sheet->sheetData->row as $row) {
            $rIdx = (int) ($row['r'] ?? 0);
            if ($rIdx <= 0) {
                $rIdx = count($grid) + 1;
            }
            foreach ($row->c as $c) {
                $ref = (string) ($c['r'] ?? '');
                $col = self::colIndexFromRef($ref);
                $type = (string) ($c['t'] ?? '');
                $val = '';
                if ($type === 's') {
                    $si = (int) ($c->v ?? -1);
                    $val = $shared[$si] ?? '';
                } elseif ($type === 'inlineStr') {
                    $val = (string) ($c->is->t ?? '');
                } else {
                    $val = (string) ($c->v ?? '');
                    // Fechas Excel seriales: si el estilo sugiere fecha no lo detectamos fino;
                    // si es número grande típico de fecha serial, convertir.
                    if ($val !== '' && is_numeric($val) && (float) $val > 30000 && (float) $val < 60000 && ! str_contains($val, '.')) {
                        $val = self::excelSerialToDate((float) $val);
                    }
                }
                $grid[$rIdx][$col] = $val;
            }
        }
        if ($grid === []) {
            return ['ok' => false, 'error' => 'La hoja Excel no tiene datos.'];
        }
        ksort($grid);
        $maxCol = 0;
        foreach ($grid as $cols) {
            $maxCol = max($maxCol, max(array_keys($cols)));
        }
        $rows = [];
        foreach ($grid as $cols) {
            $line = [];
            for ($i = 1; $i <= $maxCol; $i++) {
                $line[] = (string) ($cols[$i] ?? '');
            }
            $rows[] = $line;
        }

        return self::rowsToFilas($rows);
    }

    /**
     * PDF best-effort: extrae texto y busca filas con fecha + montos.
     *
     * @return array{ok: bool, error?: string, filas?: list<array<string, mixed>>, columnas?: list<string>, detalle?: string}
     */
    private static function parsePdf(string $binary, string $filename = 'desarrollo.pdf'): array
    {
        $ocr = EquityOcr::textoDesdeBinary($binary, $filename !== '' ? $filename : 'desarrollo.pdf', 'application/pdf');
        if (! ($ocr['ok'] ?? false)) {
            return [
                'ok'      => false,
                'error'   => $ocr['error'] ?? 'No se pudo leer el PDF.',
                'detalle' => 'El archivo se puede guardar igual como respaldo del desarrollo.',
            ];
        }

        return self::parseTextoOcr((string) ($ocr['text'] ?? ''), 'pdf/' . ($ocr['motor'] ?? 'text'));
    }

    /**
     * @return array{ok: bool, error?: string, filas?: list<array<string, mixed>>, columnas?: list<string>, detalle?: string}
     */
    private static function parseTextoOcr(string $text, string $origen): array
    {
        $text = self::toUtf8($text);
        if (trim($text) === '') {
            return [
                'ok'      => false,
                'error'   => 'Sin texto para importar.',
                'detalle' => 'El archivo se puede guardar como respaldo.',
            ];
        }

        $lines = preg_split('/\R/u', $text) ?: [];
        $matrix = [];
        foreach ($lines as $line) {
            $line = trim(preg_replace('/[ \t]+/u', ' ', $line) ?? $line);
            if ($line === '') {
                continue;
            }
            $parts = preg_split('/\s{2,}|\t+/u', $line) ?: [$line];
            if (count($parts) === 1) {
                $parts = preg_split('/\s+/u', $line) ?: [$line];
            }
            $matrix[] = $parts;
        }

        $parsed = self::rowsToFilas($matrix);
        if (($parsed['ok'] ?? false) && ! empty($parsed['filas'])) {
            $parsed['detalle'] = 'Importado desde ' . $origen . ' (best-effort). Revisa y ajusta.';

            return $parsed;
        }

        $filas = [];
        foreach ($lines as $line) {
            if (! preg_match('/(\d{1,2}[\/\-.]\d{1,2}[\/\-.]\d{2,4}|\d{4}-\d{2}-\d{2})/', $line, $fm)) {
                continue;
            }
            $nums = [];
            if (preg_match_all('/\d{1,3}(?:[.\s]\d{3})*(?:,\d+)?|\d+[.,]\d+/u', $line, $nm)) {
                foreach ($nm[0] as $raw) {
                    $n = EquityUf::parseDecimal($raw, 4);
                    if ($n !== null) {
                        $nums[] = $n;
                    }
                }
            }
            if ($nums === []) {
                continue;
            }
            $fila = ['fecha' => $fm[1]];
            if (isset($nums[0]) && $nums[0] < 1000 && abs($nums[0] - (int) $nums[0]) < 0.001) {
                $fila['dividendo_n'] = (int) $nums[0];
                array_shift($nums);
            }
            if (isset($nums[0])) {
                $fila[$nums[0] < 5000 ? 'cuota_uf' : 'cuota_clp'] = $nums[0];
            }
            if (isset($nums[1])) {
                $fila[$nums[1] < 5000 ? 'amortizacion_uf' : 'amortizacion_clp'] = $nums[1];
            }
            if (isset($nums[2])) {
                $fila[$nums[2] < 5000 ? 'interes_uf' : 'interes_clp'] = $nums[2];
            }
            if (isset($nums[3])) {
                $fila[$nums[3] < 5000 ? 'saldo_uf' : 'saldo_clp'] = $nums[3];
            }
            $filas[] = $fila;
        }

        if ($filas === []) {
            return [
                'ok'      => false,
                'error'   => 'No se detectaron filas de desarrollo en el documento. Edita manualmente o sube Excel/CSV.',
                'detalle' => 'El archivo igual se puede guardar como respaldo. Origen: ' . $origen,
            ];
        }

        return [
            'ok'       => true,
            'filas'    => $filas,
            'columnas' => array_values(array_unique(array_merge(...array_map('array_keys', $filas)))),
            'detalle'  => 'Importado desde ' . $origen . ' (best-effort). Revisa montos.',
        ];
    }

    /**
     * @param list<list<string>> $rows
     * @return array{ok: bool, error?: string, filas?: list<array<string, mixed>>, columnas?: list<string>}
     */
    private static function rowsToFilas(array $rows): array
    {
        if ($rows === []) {
            return ['ok' => false, 'error' => 'Sin filas.'];
        }

        $headerIdx = null;
        $map = [];
        for ($i = 0; $i < min(15, count($rows)); $i++) {
            $candidate = self::mapHeaders($rows[$i]);
            if (count($candidate) >= 2 || (count($candidate) === 1 && isset($candidate['fecha']))) {
                $headerIdx = $i;
                $map = $candidate;
                break;
            }
        }
        if ($headerIdx === null || $map === []) {
            return ['ok' => false, 'error' => 'No se reconocieron columnas (fecha, dividendo, cuota, amortización, interés, saldo…).'];
        }

        $filas = [];
        for ($i = $headerIdx + 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            $fila = [];
            foreach ($map as $field => $col) {
                $raw = trim((string) ($row[$col] ?? ''));
                if ($raw === '') {
                    continue;
                }
                if ($field === 'fecha') {
                    $fila['fecha'] = $raw;
                    continue;
                }
                if ($field === 'dividendo_n') {
                    $n = EquityUf::parseDecimal($raw, 0);
                    if ($n !== null) {
                        $fila['dividendo_n'] = (int) $n;
                    }
                    continue;
                }
                $scale = str_ends_with($field, '_uf') ? 4 : 2;
                $n = EquityUf::parseDecimal($raw, $scale);
                if ($n !== null) {
                    $fila[$field] = $n;
                }
            }
            if ($fila !== []) {
                $filas[] = $fila;
            }
        }

        if ($filas === []) {
            return ['ok' => false, 'error' => 'Se encontraron encabezados pero ninguna fila con datos.'];
        }

        return [
            'ok'       => true,
            'filas'    => $filas,
            'columnas' => array_keys($map),
        ];
    }

    /**
     * @param list<string> $header
     * @return array<string, int> field => column index
     */
    private static function mapHeaders(array $header): array
    {
        $map = [];
        foreach ($header as $i => $cell) {
            $norm = self::normalizeHeader((string) $cell);
            if ($norm === '') {
                continue;
            }
            foreach (self::ALIASES as $field => $aliases) {
                if (isset($map[$field])) {
                    continue;
                }
                foreach ($aliases as $a) {
                    if ($norm === $a || str_starts_with($norm, $a . ' ') || str_ends_with($norm, ' ' . $a)) {
                        $map[$field] = $i;
                        break 2;
                    }
                }
            }
        }

        // Preferencia: si hay "cuota" genérica y también cuota_uf, ok;
        // si solo "cuota" sin moneda, ya mapeó a cuota_clp por alias.
        return $map;
    }

    private static function normalizeHeader(string $h): string
    {
        $h = mb_strtolower(trim($h), 'UTF-8');
        $h = strtr($h, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
            'ü' => 'u',
        ]);
        $h = preg_replace('/[^a-z0-9$º°.\s]+/u', ' ', $h) ?? $h;
        $h = preg_replace('/\s+/u', ' ', $h) ?? $h;

        return trim($h);
    }

    private static function detectSeparator(string $line): string
    {
        $counts = [';' => substr_count($line, ';'), ',' => substr_count($line, ','), "\t" => substr_count($line, "\t")];
        arsort($counts);
        $best = array_key_first($counts);

        return ($counts[$best] ?? 0) > 0 ? (string) $best : ';';
    }

    private static function colIndexFromRef(string $ref): int
    {
        if (preg_match('/^([A-Z]+)/i', $ref, $m) !== 1) {
            return 1;
        }
        $letters = strtoupper($m[1]);
        $n = 0;
        $len = strlen($letters);
        for ($i = 0; $i < $len; $i++) {
            $n = $n * 26 + (ord($letters[$i]) - 64);
        }

        return max(1, $n);
    }

    private static function excelSerialToDate(float $serial): string
    {
        // Excel epoch 1899-12-30
        $ts = (int) (($serial - 25569) * 86400);

        return gmdate('Y-m-d', $ts);
    }

    private static function toUtf8(string $binary): string
    {
        if (str_starts_with($binary, "\xEF\xBB\xBF")) {
            $binary = substr($binary, 3);
        }
        if (mb_check_encoding($binary, 'UTF-8')) {
            return $binary;
        }
        $conv = @mb_convert_encoding($binary, 'UTF-8', 'ISO-8859-1,Windows-1252');

        return is_string($conv) ? $conv : $binary;
    }
}
