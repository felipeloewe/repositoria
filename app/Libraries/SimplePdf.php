<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Generador PDF mínimo (PDF 1.4, Helvetica / WinAnsi) sin dependencias Composer.
 * Suficiente para volcar título + filas etiqueta/valor de formularios de socios.
 */
final class SimplePdf
{
    /**
     * @param list<array{label: string, value: string}> $rows
     */
    public static function fromKeyValues(string $title, array $rows, ?string $subtitle = null, ?string $footer = null): string
    {
        $lines = [];
        $lines[] = self::encode($title);
        if ($subtitle !== null && trim($subtitle) !== '') {
            $lines[] = self::encode($subtitle);
        }
        $lines[] = '';
        foreach ($rows as $row) {
            $label = self::encode((string) ($row['label'] ?? ''));
            $value = self::encode((string) ($row['value'] ?? ''));
            $lines[] = $label . ':';
            foreach (self::wrap($value, 90) as $chunk) {
                $lines[] = '  ' . $chunk;
            }
            $lines[] = '';
        }
        if ($footer !== null && trim($footer) !== '') {
            $lines[] = self::encode($footer);
        }

        $content = "BT\n/F1 14 Tf\n50 780 Td\n14 TL\n";
        $first   = true;
        foreach ($lines as $i => $line) {
            $escaped = self::escapePdfString($line);
            if ($i === 0) {
                $content .= "/F1 16 Tf\n({$escaped}) Tj\n/F1 11 Tf\nT*\n";
                $first = false;
                continue;
            }
            if ($first) {
                $content .= "({$escaped}) Tj\n";
                $first = false;
            } else {
                $content .= "T*\n({$escaped}) Tj\n";
            }
        }
        $content .= "ET\n";

        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
        $objects[3] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>';
        $objects[4] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . 'endstream';
        $objects[5] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';

        $pdf  = "%PDF-1.4\n";
        $xref = [];
        foreach ($objects as $num => $body) {
            $xref[$num] = strlen($pdf);
            $pdf .= $num . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xrefPos = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $xref[$i]);
        }
        $pdf .= 'trailer << /Size ' . (count($objects) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefPos}\n%%EOF\n";

        return $pdf;
    }

    private static function encode(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[^\P{C}\t\n]+/u', '', $text) ?? $text;
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
        if ($converted === false) {
            $converted = preg_replace('/[^\x20-\x7E\xA0-\xFF\n\t]/', '?', $text) ?? $text;
        }

        return $converted;
    }

    /** @return list<string> */
    private static function wrap(string $text, int $width): array
    {
        $text = str_replace("\n", ' ', $text);
        if ($text === '') {
            return [''];
        }
        $wrapped = wordwrap($text, $width, "\n", true);

        return explode("\n", $wrapped);
    }

    private static function escapePdfString(string $s): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
    }
}
