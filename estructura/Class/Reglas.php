<?php
/**
 * Estructura XL - reglas puras del Directorio
 *
 * Todo lo que es "una regla" (normalizar texto, buscar, validar mail, celular y color, armar el
 * link de WhatsApp, decidir si una ficha está completa o si una búsqueda se registra) vive acá,
 * en métodos estáticos que no leen la base ni la sesión. Por qué: para probarlas sin conexión
 * (tests/run.php) y para que la pantalla, el endpoint y la importación usen exactamente la misma.
 *
 * Normalización: es UNA sola regla. El JS (assets/normalizar.js) no tiene su propia lista de
 * palabras vacías ni su mapa de tildes: los recibe de configJs() al cargar la página. La base
 * tiene un espejo de clave() en dbo.RO_FN_DIR_CLAVE (sql/02) para el índice único y la carga inicial.
 *
 * Compatible con PHP 7.3 (composer.json): sin funciones flecha, sin propiedades tipadas.
 */
class Reglas
{
    /** Letras con tilde → sin tilde. Igual en RO_FN_DIR_CLAVE. Se aplica después de pasar a minúsculas. */
    const TILDES = array(
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ñ' => 'n', 'ç' => 'c',
    );

    /** Palabras vacías de la búsqueda (definidas por negocio). Único lugar donde están. */
    const PALABRAS_VACIAS = array(
        'la', 'el', 'los', 'las', 'de', 'del', 'mi', 'me', 'un', 'una', 'que', 'no', 'se',
        'con', 'por', 'para', 'en', 'y', 'tengo', 'hay', 'como',
    );

    /** Con menos caracteres útiles la Guía pide "Escribí un poco más…" en vez de mostrar resultados. */
    const MIN_CARACTERES_BUSQUEDA = 2;
    /** Con menos caracteres útiles una búsqueda no se registra (no le sirve al chatbot). */
    const MIN_CARACTERES_REGISTRO = 3;

    /** Colores para elegir en la ficha (los 6 de las áreas iniciales + 2). El primero libre va a un área nueva importada. */
    const PALETA = array('#2F5FB3', '#1F7A8C', '#B23A2E', '#8E3B6E', '#9A4A1F', '#5B5BA8', '#3F7D3A', '#B7791F');

    /** Largos máximos: los de las columnas (sql/02). La app avisa antes de que la base corte. */
    const LARGO_NOMBRE_AREA = 100;
    const LARGO_RESUELVE = 200;
    const LARGO_MAIL = 150;
    const LARGO_HORARIO = 100;
    const LARGO_TEMA = 150;
    const LARGO_PALABRAS = 500;
    const LARGO_BUSQUEDA = 200;

    /* ================================================================ Texto */

    /**
     * Clave de comparación: minúsculas, sin tildes, todo lo que no es letra o número es un
     * separador, espacios simples sin bordes. "  Cajas / POS " → "cajas pos".
     * Se usa para comparar nombres de áreas, temas y personas ("Logistica" = "Logística") y es
     * el primer paso de normalizarBusqueda(). Espejo en SQL: dbo.RO_FN_DIR_CLAVE.
     */
    public static function clave($texto)
    {
        $s = mb_strtolower((string)$texto, 'UTF-8');
        $s = strtr($s, self::TILDES);
        $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
        return trim($s);
    }

    /** Palabras útiles de una búsqueda: clave() partida en palabras, sin las palabras vacías. */
    public static function palabras($texto)
    {
        $clave = self::clave($texto);
        if ($clave === '') {
            return array();
        }
        $vacias = array_flip(self::PALABRAS_VACIAS);
        $res = array();
        foreach (explode(' ', $clave) as $p) {
            if (!isset($vacias[$p])) {
                $res[] = $p;
            }
        }
        return $res;
    }

    /** Texto normalizado de búsqueda: "Me FALTA mercadería" → "falta mercaderia". */
    public static function normalizarBusqueda($texto)
    {
        return implode(' ', self::palabras($texto));
    }

    /** Caracteres útiles (sin espacios) de lo que queda después de normalizar. */
    public static function caracteresUtiles($texto)
    {
        return strlen(str_replace(' ', '', self::normalizarBusqueda($texto)));
    }

    /** Texto contra el que se busca un tema: su nombre, sus palabras clave y el nombre del área. */
    public static function textoBuscable($tema, $palabrasClave, $area)
    {
        return self::normalizarBusqueda($tema . ' ' . $palabrasClave . ' ' . $area);
    }

    /**
     * Un tema coincide si TODAS las palabras útiles de la consulta están contenidas en su texto
     * normalizado. Contenidas, no iguales: "imprim" encuentra "impresora no imprime".
     */
    public static function coincide(array $palabrasConsulta, $textoBuscable)
    {
        if (!$palabrasConsulta) {
            return false;
        }
        foreach ($palabrasConsulta as $p) {
            if (strpos($textoBuscable, $p) === false) {
                return false;
            }
        }
        return true;
    }

    /**
     * Temas que encuentra una consulta, en el orden recibido (el de áreas y temas).
     * Con menos de MIN_CARACTERES_BUSQUEDA caracteres útiles no devuelve nada: la pantalla
     * muestra "Escribí un poco más…". Misma lógica que buscar() de assets/normalizar.js.
     *
     * @param array    $temas   cada uno con 'id_area', 'tema', 'palabras_clave', 'area'
     * @param int|null $idArea  chip de área elegido (null = todas)
     */
    public static function buscar($consulta, array $temas, $idArea = null)
    {
        if (self::caracteresUtiles($consulta) < self::MIN_CARACTERES_BUSQUEDA) {
            return array();
        }
        $palabras = self::palabras($consulta);
        $res = array();
        foreach ($temas as $t) {
            if ($idArea !== null && (int)$t['id_area'] !== (int)$idArea) {
                continue;
            }
            $texto = self::textoBuscable($t['tema'], isset($t['palabras_clave']) ? $t['palabras_clave'] : '', $t['area']);
            if (self::coincide($palabras, $texto)) {
                $res[] = $t;
            }
        }
        return $res;
    }

    /**
     * ¿Se registra esta búsqueda? Sólo si tiene al menos MIN_CARACTERES_REGISTRO caracteres útiles
     * y no es igual (normalizada) a la última registrada en la misma pantalla. Así no se guarda
     * una fila por tecla ni la misma búsqueda dos veces seguidas.
     */
    public static function debeRegistrarBusqueda($texto, $ultimaNormalizada)
    {
        if (self::caracteresUtiles($texto) < self::MIN_CARACTERES_REGISTRO) {
            return false;
        }
        return self::normalizarBusqueda($texto) !== (string)$ultimaNormalizada;
    }

    /** Lo que necesita el JS para normalizar igual que PHP (va en window.ESTRUCTURA.reglas). */
    public static function configJs()
    {
        return array(
            'tildes'         => self::TILDES,
            'palabrasVacias' => self::PALABRAS_VACIAS,
            'minBusqueda'    => self::MIN_CARACTERES_BUSQUEDA,
            'minRegistro'    => self::MIN_CARACTERES_REGISTRO,
        );
    }

    /* ================================================================ Mail, celular, color */

    /** null si el mail es válido (o está vacío: es opcional); si no, el motivo. */
    public static function validarMail($mail)
    {
        $mail = trim((string)$mail);
        if ($mail === '') {
            return null;
        }
        if (strlen($mail) > self::LARGO_MAIL) {
            return 'El mail es demasiado largo (máximo ' . self::LARGO_MAIL . ' caracteres).';
        }
        // filter_var acepta dominios sin punto ("a@b"); para un mail de la empresa pedimos dominio con punto
        if (filter_var($mail, FILTER_VALIDATE_EMAIL) === false || !preg_match('/@[^@\s]+\.[^@\s]+$/', $mail)) {
            return 'El mail no es válido. Tiene que ser como sector@xl.com.ar.';
        }
        return null;
    }

    /**
     * Limpia y valida un celular de sector. Se carga con código de área y sin 0 ni 15
     * ("11 2345 6789"); se guardan sólo los dígitos. Tienen que quedar exactamente 10
     * (código de área + número: así son todos los números de Argentina sin 0 ni 15).
     *
     * @return array ['digitos' => string|null, 'error' => string|null]. Vacío es válido (opcional).
     */
    public static function limpiarCelular($celular)
    {
        $digitos = preg_replace('/\D+/', '', (string)$celular);
        if ($digitos === '') {
            return array('digitos' => '', 'error' => null);
        }

        $conCero = $digitos[0] === '0';
        $sinCero = $conCero ? substr($digitos, 1) : $digitos;
        // Un 15 de más: quedan 12 dígitos y hay un "15" justo después del código de área (2 a 4 dígitos)
        $conQuince = strlen($sinCero) === 12
            && (substr($sinCero, 2, 2) === '15' || substr($sinCero, 3, 2) === '15' || substr($sinCero, 4, 2) === '15');

        $ejemplo = ' Cargalo con código de área, sin 0 ni 15: por ejemplo 11 2345 6789.';
        if ($conCero && $conQuince) {
            return array('digitos' => null, 'error' => 'Sobran el 0 del principio y el 15.' . $ejemplo);
        }
        if ($conCero) {
            return array('digitos' => null, 'error' => 'Sobra el 0 del principio.' . $ejemplo);
        }
        if ($conQuince) {
            return array('digitos' => null, 'error' => 'Sobra el 15.' . $ejemplo);
        }
        if (strlen($digitos) !== 10) {
            return array('digitos' => null, 'error' => 'Tiene ' . strlen($digitos) . ' dígitos y tienen que ser 10.' . $ejemplo);
        }
        return array('digitos' => $digitos, 'error' => null);
    }

    /**
     * Celular para mostrar. Sin una tabla de códigos de área no se puede saber dónde cortar:
     * el 11 (AMBA, el más común) va 11 2345 6789; el resto, 3 + 3 + 4 (351 234 5678).
     */
    public static function formatearCelular($digitos)
    {
        $d = (string)$digitos;
        if (strlen($d) !== 10) {
            return $d;
        }
        if (substr($d, 0, 2) === '11') {
            return '11 ' . substr($d, 2, 4) . ' ' . substr($d, 6);
        }
        return substr($d, 0, 3) . ' ' . substr($d, 3, 3) . ' ' . substr($d, 6);
    }

    /** Link de WhatsApp Web: 549 (Argentina, celular) + los 10 dígitos. null si no hay celular. */
    public static function linkWhatsapp($digitos)
    {
        $d = (string)$digitos;
        if (strlen($d) !== 10 || !ctype_digit($d)) {
            return null;
        }
        return 'https://web.whatsapp.com/send?phone=549' . $d;
    }

    /** Color '#RRGGBB' en mayúsculas, o null si no es un hex válido. Acepta sin '#'. */
    public static function normalizarColor($color)
    {
        $c = strtoupper(trim((string)$color));
        if ($c !== '' && $c[0] !== '#') {
            $c = '#' . $c;
        }
        return preg_match('/^#[0-9A-F]{6}$/', $c) ? $c : null;
    }

    /* ================================================================ Referentes y fichas */

    /** null si está bien; el motivo si es persona y equipo a la vez (también lo impide la base). */
    public static function validarReferente($idReferente, $equipo)
    {
        if (!empty($idReferente) && !empty($equipo)) {
            return 'El referente es una persona o el equipo del área, no las dos cosas.';
        }
        return null;
    }

    /**
     * Estado de una persona elegida (referente, gerente o jefe) contra Internos.
     * @param array $personas ID => ['nombre' => ..., 'activo' => bool]
     * @return string 'vacio' | 'activa' | 'inactiva' | 'inexistente'
     */
    public static function estadoPersona($id, array $personas)
    {
        if (empty($id)) {
            return 'vacio';
        }
        if (!isset($personas[(int)$id])) {
            return 'inexistente';
        }
        return $personas[(int)$id]['activo'] ? 'activa' : 'inactiva';
    }

    /**
     * Cómo se muestra el referente de un tema en la consulta: el nombre si es una persona activa,
     * "Equipo de <área>", o null = "A definir" (sin referente, o la persona ya no está activa).
     */
    public static function textoReferente($idReferente, $equipo, $nombreArea, array $personas)
    {
        if (!empty($equipo)) {
            return 'Equipo de ' . $nombreArea;
        }
        if (self::estadoPersona($idReferente, $personas) === 'activa') {
            return $personas[(int)$idReferente]['nombre'];
        }
        return null;
    }

    /** Aviso para la administración si una persona elegida ya no está activa (o no existe). */
    public static function avisoPersona($id, array $personas)
    {
        $estado = self::estadoPersona($id, $personas);
        if ($estado === 'inactiva') {
            return $personas[(int)$id]['nombre'] . ' ya no está activa en Internos';
        }
        if ($estado === 'inexistente') {
            return 'La persona elegida ya no está en Internos';
        }
        return null;
    }

    /**
     * ¿La ficha de un área está completa? Lo está si tiene qué resuelve, mail, celular y horario,
     * tiene temas y todos tienen un referente activo o el equipo. Gerente y jefe no cuentan: hay
     * áreas que no los tienen.
     *
     * @param array $area   'resuelve', 'mail_sector', 'celular_sector', 'horario'
     * @param array $temas  activos, cada uno con 'id_referente' y 'referente_equipo'
     * @return array ['completa' => bool, 'texto' => 'Completa' | lo que falta]
     */
    public static function fichaCompleta(array $area, array $temas, array $personas)
    {
        $faltanCampos = array();
        $campos = array('resuelve' => 'qué resuelve', 'mail_sector' => 'mail', 'celular_sector' => 'celular', 'horario' => 'horario');
        foreach ($campos as $campo => $texto) {
            if (!isset($area[$campo]) || trim((string)$area[$campo]) === '') {
                $faltanCampos[] = $texto;
            }
        }

        $sinReferente = 0;
        $inactivos = 0;
        foreach ($temas as $t) {
            if (!empty($t['referente_equipo'])) {
                continue;
            }
            $estado = self::estadoPersona(isset($t['id_referente']) ? $t['id_referente'] : null, $personas);
            if ($estado === 'vacio') {
                $sinReferente++;
            } elseif ($estado !== 'activa') {
                $inactivos++;
            }
        }

        $partes = array();
        if ($faltanCampos) {
            $partes[] = 'Falta ' . self::enumerar($faltanCampos);
        }
        if (!$temas) {
            $partes[] = 'Sin temas';
        }
        if ($sinReferente) {
            $partes[] = $sinReferente . ($sinReferente === 1 ? ' tema sin referente' : ' temas sin referente');
        }
        if ($inactivos) {
            $partes[] = $inactivos . ($inactivos === 1 ? ' referente inactivo en Internos' : ' referentes inactivos en Internos');
        }

        return $partes
            ? array('completa' => false, 'texto' => implode(' · ', $partes))
            : array('completa' => true, 'texto' => 'Completa');
    }

    /** "a", "a y b", "a, b y c" */
    public static function enumerar(array $items)
    {
        if (count($items) <= 1) {
            return implode('', $items);
        }
        $ultimo = array_pop($items);
        return implode(', ', $items) . ' y ' . $ultimo;
    }
}
