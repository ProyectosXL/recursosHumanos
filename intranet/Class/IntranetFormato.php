<?php
/**
 * Intranet XL - texto con formato básico de los comunicados
 *
 * El texto se guarda con marcas sencillas (estilo Markdown), nunca como HTML:
 *   **negrita**   *cursiva*   "- " viñeta   "1. " lista numerada   [texto](url)
 * Al mostrar, html() arma el HTML con una LISTA CERRADA de etiquetas: p, br, ul, ol, li, strong,
 * em y a. Todo lo demás se escapa. Los links solo pueden empezar con https://, http:// o /
 * (Intranet::linkSeguro) y se abren en una pestaña nueva; si no cumplen, quedan como texto.
 *
 * Comunicados sin formato (con_formato = 0: todos los anteriores a esta versión): el texto se
 * devuelve escapado tal cual y se muestra con white-space: pre-line, como siempre.
 *
 * Plantillas: los datos a completar van entre corchetes ([Nombre del local]). En la vista previa
 * ($resaltar) se marcan con <mark class="com-dato">; en la pantalla real se ven tal cual.
 */

require_once __DIR__ . '/Intranet.php';

class IntranetFormato
{
    /** Dato a completar de una plantilla: [Nombre del local]. No toma los links [texto](url). */
    const PATRON_DATO = '/\[([^\[\]\n<>]{1,80})\](?!\()/u';

    /** $resaltar: marca los datos a completar (solo la vista previa de la carga). */
    public static function html(string $texto, bool $conFormato, bool $resaltar = false): string
    {
        $texto = str_replace(["\r\n", "\r"], "\n", $texto);
        if (!$conFormato) {
            $html = Intranet::e($texto);
            return $resaltar ? self::resaltarDatos($html) : $html;
        }
        if ($resaltar) {
            self::$resaltar = true;
            try {
                return self::html($texto, true, false);
            } finally {
                self::$resaltar = false;
            }
        }
        $texto = str_replace("\x01", '', $texto); // reservado para marcar links (ver enLinea)

        $html = '';
        $parrafo = [];
        $lista = null;   // 'ul' | 'ol' | null
        $items = [];

        $cerrarParrafo = function () use (&$html, &$parrafo) {
            if ($parrafo) {
                $html .= '<p>' . implode('<br>', array_map([self::class, 'enLinea'], $parrafo)) . '</p>';
                $parrafo = [];
            }
        };
        $cerrarLista = function () use (&$html, &$lista, &$items) {
            if ($lista) {
                $html .= "<$lista>";
                foreach ($items as $item) {
                    $html .= '<li>' . self::enLinea($item) . '</li>';
                }
                $html .= "</$lista>";
                $lista = null;
                $items = [];
            }
        };

        foreach (explode("\n", trim($texto)) as $linea) {
            $l = trim($linea);
            if ($l === '') {
                $cerrarParrafo();
                $cerrarLista();
                continue;
            }
            $tipo = null;
            if (preg_match('/^-\s+(.+)$/u', $l, $m)) {
                $tipo = 'ul';
            } elseif (preg_match('/^\d{1,3}\.\s+(.+)$/u', $l, $m)) {
                $tipo = 'ol';
            }
            if ($tipo) {
                $cerrarParrafo();
                if ($lista !== $tipo) {
                    $cerrarLista();
                }
                $lista = $tipo;
                $items[] = $m[1];
            } else {
                $cerrarLista();
                $parrafo[] = $l;
            }
        }
        $cerrarParrafo();
        $cerrarLista();
        return $html;
    }

    /** Una línea: links, negrita y cursiva. Primero se apartan los links, después se escapa todo. */
    private static function enLinea(string $s): string
    {
        $links = [];
        $s = preg_replace_callback('/\[([^\]\n]+)\]\(([^)\s]+)\)/u', function ($m) use (&$links) {
            $link = Intranet::linkSeguro($m[2]);
            if (!$link) {
                return $m[0]; // link no permitido: queda como texto
            }
            $links[] = [$m[1], $link['url']];
            return "\x01" . (count($links) - 1) . "\x01";
        }, $s) ?? $s;

        $s = self::enfasis(Intranet::e($s));
        if (self::$resaltar) {
            $s = self::resaltarDatos($s); // los links ya están apartados: sus corchetes no cuentan
        }

        return preg_replace_callback('/\x01(\d+)\x01/', function ($m) use ($links) {
            [$texto, $url] = $links[(int)$m[1]];
            return '<a href="' . Intranet::e($url) . '" target="_blank" rel="noopener noreferrer">'
                . self::enfasis(Intranet::e($texto)) . '</a>';
        }, $s) ?? $s;
    }

    private static $resaltar = false;

    /** Marca los datos a completar sobre texto YA escapado (vista previa). */
    public static function resaltarDatos(string $html): string
    {
        return preg_replace(self::PATRON_DATO, '<mark class="com-dato">[$1]</mark>', $html) ?? $html;
    }

    /** **negrita** y *cursiva* sobre texto YA escapado. */
    private static function enfasis(string $s): string
    {
        $s = preg_replace('/\*\*(?=\S)(.+?)(?<=\S)\*\*/u', '<strong>$1</strong>', $s) ?? $s;
        return preg_replace('/(?<![*\w])\*(?=[^\s*])(.+?)(?<=[^\s*])\*(?![*\w])/u', '<em>$1</em>', $s) ?? $s;
    }
}
