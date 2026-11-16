<?php

declare(strict_types=1);

namespace DocuGest\Engine;

/**
 * Extractor y estructurador de texto PDF para DocuGest 2.0 (100% local en el servidor).
 *
 * Estrategia de extracción en cascada:
 * 1. Si está disponible `pypdf` en el servidor Windows local, extrae página por página con fidelidad Unicode completa.
 * 2. Fallback 100% PHP puro: descomprime streams FlateDecode, resuelve ToUnicode CMaps y extrae texto BT..ET.
 * 3. Divide el texto limpio en Secciones Semánticas coherentes para ahorrar tokens en RAG.
 */
final class DocuGestPdfParser
{
    /**
     * Extrae el texto limpio completo de un PDF local.
     */
    public static function extractText(string $pdfPath): string
    {
        if (!is_file($pdfPath) || !is_readable($pdfPath)) {
            return '';
        }

        // Método 1: Extracción local de alta fidelidad con pypdf si está disponible en CLI local
        $text = self::extractViaLocalPypdf($pdfPath);
        if (mb_strlen(trim($text), 'UTF-8') >= 80) {
            return self::cleanExtractedText($text);
        }

        // Método 2: Extractor puro en PHP (FlateDecode + ToUnicode CMap + BT..ET)
        $phpText = self::extractPurePhp($pdfPath);
        return self::cleanExtractedText($phpText);
    }

    /**
     * Divide el texto extraído de un PDF en secciones semánticas sin cortar pasos ni oraciones a la mitad.
     *
     * @return array<int, array{titulo_seccion: string, contenido: string, tokens_aprox: int}>
     */
    public static function splitIntoSemanticSections(string $rawText, string $docTitle = ''): array
    {
        $text = self::cleanExtractedText($rawText);
        if ($text === '') {
            return [];
        }

        $lines = preg_split('/\R/u', $text) ?: [];
        $sections = [];
        $currentHeading = $docTitle !== '' ? "Introducción · {$docTitle}" : 'Contenido General';
        $currentLines = [];

        $flushSection = static function () use (&$sections, &$currentHeading, &$currentLines): void {
            $body = trim(implode("\n", $currentLines));
            if (mb_strlen($body, 'UTF-8') < 25) {
                $currentLines = [];
                return;
            }

            // Si una sección excede 2200 caracteres, subdividir respetando saltos de párrafo / pasos
            if (mb_strlen($body, 'UTF-8') > 2200) {
                $chunks = self::splitLongBodySafely($body, 1700);
                $totalParts = count($chunks);
                foreach ($chunks as $idx => $chunk) {
                    $suffix = $totalParts > 1 ? ' (Parte ' . ($idx + 1) . "/{$totalParts})" : '';
                    $sections[] = [
                        'titulo_seccion' => $currentHeading . $suffix,
                        'contenido'      => $chunk,
                        'tokens_aprox'   => (int)ceil(mb_strlen($chunk, 'UTF-8') / 4),
                    ];
                }
            } else {
                $sections[] = [
                    'titulo_seccion' => $currentHeading,
                    'contenido'      => $body,
                    'tokens_aprox'   => (int)ceil(mb_strlen($body, 'UTF-8') / 4),
                ];
            }

            $currentLines = [];
        };

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            // Saltar encabezados/pies de página repetitivos típicos de instructivos XL
            if (
                preg_match('/^p[aá]gina\s+\d+(\s+de\s+\d+)?$/iu', $trimmed) ||
                preg_match('/^---\s*P[ÁA]GINA\s+\d+\s*---$/iu', $trimmed) ||
                preg_match('/^\d{1,3}$/', $trimmed)
            ) {
                continue;
            }

            if (self::looksLikeSectionHeading($trimmed) && count($currentLines) > 0) {
                $flushSection();
                $currentHeading = $trimmed;
                continue;
            }

            if (count($currentLines) === 0 && self::looksLikeSectionHeading($trimmed)) {
                $currentHeading = $trimmed;
                continue;
            }

            $currentLines[] = $trimmed;
        }

        $flushSection();

        return $sections;
    }

    private static function looksLikeSectionHeading(string $line): bool
    {
        $len = mb_strlen($line, 'UTF-8');
        if ($len < 4 || $len > 95) {
            return false;
        }

        // Ej: "1. OBJETIVO", "2.1 ALCANCE", "3) RESPONSABLES", "PASO 1:"
        if (preg_match('/^(?:\d+(?:\.\d+)*[\.\)\-]\s+|PASO\s+\d+[:\.\-]?|ANEXO\s+[A-Z0-9]+|SECCI[ÓO]N\s+\d+)/iu', $line)) {
            // Evitar tratar un ítem largo con verbo en minúscula como título si termina en punto y es oración
            if (mb_substr($line, -1, 1, 'UTF-8') === '.' && $len > 55) {
                return false;
            }
            return true;
        }

        // Encabezado corto en MAYÚSCULAS (al menos 2 palabras o palabra clave conocida)
        if ($len <= 65 && mb_strtoupper($line, 'UTF-8') === $line && preg_match('/[A-ZÁÉÍÓÚÑ]{3,}/u', $line)) {
            return true;
        }

        return false;
    }

    /**
     * @return string[]
     */
    private static function splitLongBodySafely(string $body, int $targetLen): array
    {
        $lines = preg_split('/\R/u', $body) ?: [$body];
        $parts = [];
        $buf = '';

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if ($buf !== '' && (mb_strlen($buf, 'UTF-8') + mb_strlen($line, 'UTF-8') + 1) > $targetLen) {
                $parts[] = $buf;
                $buf = $line;
            } else {
                $buf = $buf === '' ? $line : ($buf . "\n" . $line);
            }
        }
        if ($buf !== '') {
            $parts[] = $buf;
        }

        return $parts;
    }

    private static function cleanExtractedText(string $text): string
    {
        if ($text === '') {
            return '';
        }
        // Asegurar UTF-8 válido
        $clean = @mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        $clean = str_replace(["\r\n", "\r"], "\n", $clean);
        // Eliminar caracteres de control salvo salto de línea y tabulador
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', $clean) ?? $clean;
        // Normalizar espacios horizontales múltiples
        $clean = preg_replace('/[ \t]+/u', ' ', $clean) ?? $clean;
        // Limpiar múltiples saltos de línea consecutivos
        $clean = preg_replace('/\n{3,}/u', "\n\n", $clean) ?? $clean;
        return trim($clean);
    }

    private static function extractViaLocalPypdf(string $pdfPath): string
    {
        if (!function_exists('exec')) {
            return '';
        }

        $pyCode = 'import sys, pypdf; '
            . 'r = pypdf.PdfReader(sys.argv[1]); '
            . 'out = []; '
            . 'for i, p in enumerate(r.pages): '
            . '    t = (p.extract_text() or "").strip(); '
            . '    if t: out.append(t); '
            . 'sys.stdout.buffer.write(("\n\n".join(out)).encode("utf-8", "ignore"))';

        $cmd = 'python -c ' . escapeshellarg($pyCode) . ' ' . escapeshellarg($pdfPath) . ' 2>NUL';
        $output = [];
        $code = 1;
        @exec($cmd, $output, $code);

        if ($code === 0 && !empty($output)) {
            return implode("\n", $output);
        }

        return '';
    }

    private static function extractPurePhp(string $filePath): string
    {
        $raw = @file_get_contents($filePath);
        if ($raw === false || $raw === '') {
            return '';
        }

        $globalMap = [];
        $streams = [];
        if (preg_match_all('/stream[\r\n]+(.*?)[\r\n]+endstream/s', $raw, $matches)) {
            $streams = $matches[1];
        }

        // Paso 1: Construir tabla ToUnicode CMap si existe
        foreach ($streams as $stream) {
            $data = self::decompressStream($stream);
            if ($data === '') {
                continue;
            }
            if (strpos($data, 'beginbfchar') !== false || strpos($data, 'beginbfrange') !== false) {
                if (preg_match_all('/beginbfchar(.*?)endbfchar/s', $data, $bfc)) {
                    foreach ($bfc[1] as $block) {
                        if (preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/', $block, $pairs, PREG_SET_ORDER)) {
                            foreach ($pairs as $p) {
                                $src = (int)hexdec($p[1]);
                                $dst = @mb_convert_encoding((string)@pack('H*', $p[2]), 'UTF-8', 'UTF-16BE');
                                if ($dst !== false && $dst !== '') {
                                    $globalMap[$src] = $dst;
                                }
                            }
                        }
                    }
                }
                if (preg_match_all('/beginbfrange(.*?)endbfrange/s', $data, $bfr)) {
                    foreach ($bfr[1] as $block) {
                        if (preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/', $block, $ranges, PREG_SET_ORDER)) {
                            foreach ($ranges as $r) {
                                $start = (int)hexdec($r[1]);
                                $end = (int)hexdec($r[2]);
                                $dstStart = (int)hexdec($r[3]);
                                for ($c = $start; $c <= $end && ($c - $start) < 256; $c++) {
                                    $hex = str_pad(dechex($dstStart + ($c - $start)), 4, '0', STR_PAD_LEFT);
                                    $dst = @mb_convert_encoding((string)@pack('H*', $hex), 'UTF-8', 'UTF-16BE');
                                    if ($dst !== false && $dst !== '') {
                                        $globalMap[$c] = $dst;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // Paso 2: Extraer bloques de texto BT ... ET
        $out = [];
        foreach ($streams as $stream) {
            $data = self::decompressStream($stream);
            if ($data === '' || strpos($data, 'beginbfchar') !== false || strpos($data, 'BT') === false) {
                continue;
            }

            if (preg_match_all('/BT(.*?)ET/s', $data, $btMatches)) {
                foreach ($btMatches[1] as $bt) {
                    if (preg_match_all('/\([^\(\)]*\)|<[0-9A-Fa-f]{2,}>/s', $bt, $tokens)) {
                        $line = '';
                        foreach ($tokens[0] as $tok) {
                            if ($tok[0] === '(') {
                                $s = substr($tok, 1, -1);
                                $s = strtr($s, ['\\n' => "\n", '\\r' => "\r", '\\t' => "\t", '\\(' => '(', '\\)' => ')', '\\\\' => '\\']);
                                $line .= (string)@mb_convert_encoding($s, 'UTF-8', 'Windows-1252');
                            } elseif ($tok[0] === '<') {
                                $hex = substr($tok, 1, -1);
                                if (!empty($globalMap) && strlen($hex) % 4 === 0) {
                                    for ($i = 0; $i < strlen($hex); $i += 4) {
                                        $code = (int)hexdec(substr($hex, $i, 4));
                                        $line .= $globalMap[$code] ?? '';
                                    }
                                } elseif (!empty($globalMap) && strlen($hex) % 2 === 0) {
                                    for ($i = 0; $i < strlen($hex); $i += 2) {
                                        $code = (int)hexdec(substr($hex, $i, 2));
                                        $line .= $globalMap[$code] ?? ($code >= 32 && $code <= 126 ? chr($code) : '');
                                    }
                                }
                            }
                        }
                        if (trim($line) !== '') {
                            $out[] = trim($line);
                        }
                    }
                }
            }
        }

        return implode("\n", $out);
    }

    private static function decompressStream(string $stream): string
    {
        $res = @gzuncompress($stream);
        if ($res !== false) {
            return $res;
        }
        $res = @gzinflate($stream);
        if ($res !== false) {
            return $res;
        }
        $trimmed = ltrim($stream, "\r\n");
        $res = @gzuncompress($trimmed);
        if ($res !== false) {
            return $res;
        }
        return $stream;
    }
}
