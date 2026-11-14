<?php
/**
 * Estructura XL - importación del listado en Excel: validar y resolver (lógica pura)
 *
 * El navegador lee el Excel con SheetJS y manda las filas tal cual. TODA la validación y la
 * resolución se hacen acá, en el servidor, contra el estado actual (áreas, temas y personas que
 * le pasa Directorio). El resultado es un "plan": qué se crea, qué se actualiza (campo, de y a),
 * los avisos y los errores. La vista previa muestra el plan; "Confirmar" vuelve a calcularlo con
 * las mismas filas y, si no hay errores, Directorio lo aplica en una transacción.
 *
 * Reglas (definidas por negocio):
 *   - Agrega y actualiza; nunca borra ni desactiva. Una celda vacía no borra un dato existente.
 *   - El área se identifica por su nombre y el tema por su nombre dentro del área, con la clave
 *     normalizada (Reglas::clave): "Logistica" = "Logística", sin duplicados.
 *   - Bloquean (con fila y motivo): fila sin área o sin tema, mail inválido, celular inválido,
 *     color que no es hex, orden que no es número, textos demasiado largos, y un área o tema
 *     que existe desactivado (se reactiva desde la pantalla, no importando).
 *   - Avisan sin bloquear: persona que no está en Internos, inactiva o repetida (se importa vacía);
 *     un dato del área distinto en dos filas (gana la primera); área y tema repetidos en el archivo
 *     (gana la primera fila); áreas y temas activos que no vienen ("no se tocan").
 *   - Filas omitidas: las vacías y las repetidas.
 */
require_once __DIR__ . '/Reglas.php';

class Importacion
{
    /** Columnas de la plantilla, en orden. La clave es la que se compara con el encabezado. */
    const COLUMNAS = array(
        'area'      => 'Área',
        'orden'     => 'Orden',
        'color'     => 'Color',
        'resuelve'  => 'Qué resuelve',
        'mail'      => 'Mail de sector',
        'celular'   => 'Celular de sector',
        'horario'   => 'Horario',
        'gerente'   => 'Gerente',
        'jefe'      => 'Jefe',
        'tema'      => 'Tema',
        'referente' => 'Referente',
        'palabras'  => 'Palabras clave',
    );

    /** Campos del área que vienen repetidos en cada fila del área */
    const CAMPOS_AREA = array('orden', 'color', 'resuelve', 'mail', 'celular', 'horario', 'gerente', 'jefe');

    const MAX_FILAS = 2000;

    /**
     * @param array $filas     filas del Excel (la primera es el encabezado), cada una un array de celdas
     * @param array $areas     áreas actuales, activas e inactivas, con 'temas' activos y 'temas_quitados'
     * @param array $personas  ID => ['nombre', 'activo']
     * @return array plan (ver arriba)
     */
    public static function planificar(array $filas, array $areas, array $personas)
    {
        $plan = array(
            'errores' => array(), 'avisos' => array(),
            'areas' => array(), 'temas' => array(),
            'no_incluidos' => array('areas' => array(), 'temas' => array()),
            'omitidas' => 0,
            'resumen' => array('areas_crear' => 0, 'areas_actualizar' => 0, 'temas_crear' => 0, 'temas_actualizar' => 0),
        );

        if (!$filas) {
            $plan['errores'][] = array('fila' => null, 'motivo' => 'El archivo está vacío.');
            return $plan;
        }
        if (count($filas) - 1 > self::MAX_FILAS) {
            $plan['errores'][] = array('fila' => null, 'motivo' => 'El archivo tiene más de ' . self::MAX_FILAS . ' filas.');
            return $plan;
        }

        /* ---- Encabezado: cada columna se reconoce por su nombre, en cualquier orden */
        $cols = array();
        $porClave = array();
        foreach (self::COLUMNAS as $campo => $titulo) {
            $porClave[Reglas::clave($titulo)] = $campo;
        }
        foreach (array_values((array)$filas[0]) as $i => $titulo) {
            $k = Reglas::clave($titulo);
            if (isset($porClave[$k]) && !isset($cols[$porClave[$k]])) {
                $cols[$porClave[$k]] = $i;
            }
        }
        foreach (array('area', 'tema') as $obligatoria) {
            if (!isset($cols[$obligatoria])) {
                $plan['errores'][] = array('fila' => 1, 'motivo' => 'Falta la columna "' . self::COLUMNAS[$obligatoria] . '" en la primera fila.');
            }
        }
        if ($plan['errores']) {
            return $plan;
        }

        /* ---- Estado actual indexado por clave */
        $areasActivas = array();
        $areasInactivas = array();
        $maxOrden = 0;
        $coloresUsados = array();
        foreach ($areas as $a) {
            $k = Reglas::clave($a['nombre']);
            if ($a['activa']) {
                $areasActivas[$k] = $a;
                $maxOrden = max($maxOrden, (int)$a['orden']);
                $coloresUsados[strtoupper($a['color'])] = true;
            } else {
                $areasInactivas[$k] = $a;
            }
        }
        $porNombrePersona = array();
        foreach ($personas as $id => $p) {
            $porNombrePersona[Reglas::clave($p['nombre'])][] = (int)$id;
        }

        /* ---- Filas */
        $grupos = array();      // clave de área → ['nombre', 'fila', 'valores', 'temas' => [clave → fila]]
        foreach (array_slice($filas, 1, null, true) as $idx => $celdas) {
            $nro = $idx + 1;   // número de fila en Excel (la 1 es el encabezado)
            $f = array();
            foreach (self::COLUMNAS as $campo => $titulo) {
                $f[$campo] = isset($cols[$campo], $celdas[$cols[$campo]]) ? trim((string)$celdas[$cols[$campo]]) : '';
            }
            if (implode('', $f) === '') {
                $plan['omitidas']++;
                continue;
            }
            if ($f['area'] === '') {
                $plan['errores'][] = array('fila' => $nro, 'motivo' => 'Falta el área.');
                continue;
            }
            if ($f['tema'] === '') {
                $plan['errores'][] = array('fila' => $nro, 'motivo' => 'Falta el tema.');
                continue;
            }

            $v = self::validarFila($f, $nro, $plan['errores']);
            $ka = Reglas::clave($f['area']);
            $kt = Reglas::clave($f['tema']);

            if (!isset($grupos[$ka])) {
                $grupos[$ka] = array('nombre' => $f['area'], 'fila' => $nro, 'valores' => $v, 'temas' => array());
            } else {
                // Un dato del área distinto en otra fila: gana la primera, se avisa
                foreach (self::CAMPOS_AREA as $campo) {
                    $primero = $grupos[$ka]['valores'][$campo];
                    if ($v[$campo] !== null && $primero !== null && $v[$campo] !== $primero) {
                        $plan['avisos'][] = array('fila' => $nro, 'motivo' => self::etiqueta($campo) . ' de "' . $f['area']
                            . '" viene distinto que en la fila ' . $grupos[$ka]['fila'] . ': se toma el de la fila ' . $grupos[$ka]['fila'] . '.');
                    } elseif ($primero === null && $v[$campo] !== null) {
                        $grupos[$ka]['valores'][$campo] = $v[$campo];   // la primera fila lo tenía vacío
                    }
                }
            }
            if (isset($grupos[$ka]['temas'][$kt])) {
                $plan['avisos'][] = array('fila' => $nro, 'motivo' => '"' . $f['area'] . ' › ' . $f['tema'] . '" está repetido (primero en la fila '
                    . $grupos[$ka]['temas'][$kt]['fila'] . '): se omite esta fila.');
                $plan['omitidas']++;
                continue;
            }
            $grupos[$ka]['temas'][$kt] = array('fila' => $nro, 'tema' => $f['tema'], 'referente' => $v['referente'], 'palabras' => $v['palabras']);
        }

        /* ---- Resolver áreas y temas contra lo actual */
        $proximoOrden = $maxOrden;
        foreach ($grupos as $ka => $g) {
            $v = $g['valores'];
            if (!isset($areasActivas[$ka]) && isset($areasInactivas[$ka])) {
                $plan['errores'][] = array('fila' => $g['fila'], 'motivo' => 'El área "' . $g['nombre'] . '" existe desactivada: reactivala desde el listado.');
                continue;
            }
            $actual = isset($areasActivas[$ka]) ? $areasActivas[$ka] : null;

            $gerente = self::resolverPersona($v['gerente'], 'Gerente', $g, $actual ? $actual['id_gerente'] : null, $porNombrePersona, $personas, $plan['avisos'], false);
            $jefe = self::resolverPersona($v['jefe'], 'Jefe', $g, $actual ? $actual['id_jefe'] : null, $porNombrePersona, $personas, $plan['avisos'], false);

            if ($actual === null) {
                // Área nueva: lo vacío queda vacío; sin color, el primero de la paleta que no se use
                $color = $v['color'];
                if ($color === null) {
                    foreach (Reglas::PALETA as $c) {
                        if (!isset($coloresUsados[$c])) {
                            $color = $c;
                            break;
                        }
                    }
                    if ($color === null) {
                        $color = Reglas::PALETA[count($plan['areas']) % count(Reglas::PALETA)];
                    }
                }
                $coloresUsados[$color] = true;
                $valores = array(
                    'nombre' => $g['nombre'], 'color' => $color,
                    'orden' => $v['orden'] !== null ? $v['orden'] : ++$proximoOrden,
                    'resuelve' => $v['resuelve'], 'mail_sector' => $v['mail'], 'celular_sector' => $v['celular'],
                    'horario' => $v['horario'], 'id_gerente' => $gerente['id'], 'id_jefe' => $jefe['id'],
                );
                $plan['areas'][] = array('accion' => 'crear', 'id' => null, 'clave' => $ka, 'nombre' => $g['nombre'],
                    'fila' => $g['fila'], 'cambios' => array(), 'valores' => $valores);
                $plan['resumen']['areas_crear']++;
            } else {
                $cambios = array();
                $valores = array();
                $comparar = array(
                    'orden' => array($v['orden'], (int)$actual['orden']),
                    'color' => array($v['color'], strtoupper($actual['color'])),
                    'resuelve' => array($v['resuelve'], $actual['resuelve']),
                    'mail_sector' => array($v['mail'], $actual['mail_sector']),
                    'celular_sector' => array($v['celular'], $actual['celular_sector']),
                    'horario' => array($v['horario'], $actual['horario']),
                    'id_gerente' => array($gerente['id'], $actual['id_gerente']),
                    'id_jefe' => array($jefe['id'], $actual['id_jefe']),
                );
                foreach ($comparar as $campo => $par) {
                    // Celda vacía (null): no cambia nada
                    if ($par[0] !== null && (string)$par[0] !== (string)$par[1]) {
                        $valores[$campo] = $par[0];
                        $cambios[] = array('campo' => self::etiqueta($campo),
                            'de' => self::mostrar($campo, $par[1], $personas), 'a' => self::mostrar($campo, $par[0], $personas));
                    }
                }
                $plan['areas'][] = array('accion' => $cambios ? 'actualizar' : 'igual', 'id' => (int)$actual['id'], 'clave' => $ka,
                    'nombre' => $actual['nombre'], 'fila' => $g['fila'], 'cambios' => $cambios, 'valores' => $valores);
                if ($cambios) {
                    $plan['resumen']['areas_actualizar']++;
                }
            }

            /* Temas del área */
            $temasActivos = array();
            $temasQuitados = array();
            $maxOrdenTema = 0;
            if ($actual) {
                foreach ($actual['temas'] as $t) {
                    $temasActivos[Reglas::clave($t['tema'])] = $t;
                    $maxOrdenTema = max($maxOrdenTema, (int)$t['orden']);
                }
                foreach (isset($actual['temas_quitados']) ? $actual['temas_quitados'] : array() as $t) {
                    $temasQuitados[Reglas::clave($t['tema'])] = $t;
                }
            }
            $nombreArea = $actual ? $actual['nombre'] : $g['nombre'];
            foreach ($g['temas'] as $kt => $t) {
                $temaActual = isset($temasActivos[$kt]) ? $temasActivos[$kt] : null;
                if ($temaActual === null && isset($temasQuitados[$kt])) {
                    $plan['errores'][] = array('fila' => $t['fila'], 'motivo' => 'El tema "' . $t['tema'] . '" de "' . $nombreArea
                        . '" existe quitado: reactivalo desde la ficha del área.');
                    continue;
                }
                $ref = self::resolverReferente($t['referente'], $t, $nombreArea, $temaActual, $porNombrePersona, $personas, $plan['avisos']);

                if ($temaActual === null) {
                    $plan['temas'][] = array('accion' => 'crear', 'id' => null, 'clave_area' => $ka, 'area' => $nombreArea,
                        'tema' => $t['tema'], 'fila' => $t['fila'], 'cambios' => array(),
                        'valores' => array('tema' => $t['tema'], 'orden' => ++$maxOrdenTema, 'id_referente' => $ref['id'],
                            'referente_equipo' => $ref['equipo'], 'palabras_clave' => (string)$t['palabras']));
                    $plan['resumen']['temas_crear']++;
                    continue;
                }
                $cambios = array();
                $valores = array();
                if ($ref['cambia'] && ($ref['equipo'] !== (bool)$temaActual['referente_equipo'] || $ref['id'] !== $temaActual['id_referente'])) {
                    $valores['id_referente'] = $ref['id'];
                    $valores['referente_equipo'] = $ref['equipo'];
                    $cambios[] = array('campo' => 'Referente',
                        'de' => self::mostrarReferente($temaActual['id_referente'], $temaActual['referente_equipo'], $personas),
                        'a' => self::mostrarReferente($ref['id'], $ref['equipo'], $personas));
                }
                if ($t['palabras'] !== null && $t['palabras'] !== $temaActual['palabras_clave']) {
                    $valores['palabras_clave'] = $t['palabras'];
                    $cambios[] = array('campo' => 'Palabras clave', 'de' => $temaActual['palabras_clave'], 'a' => $t['palabras']);
                }
                $plan['temas'][] = array('accion' => $cambios ? 'actualizar' : 'igual', 'id' => (int)$temaActual['id'], 'clave_area' => $ka,
                    'area' => $nombreArea, 'tema' => $temaActual['tema'], 'fila' => $t['fila'], 'cambios' => $cambios, 'valores' => $valores);
                if ($cambios) {
                    $plan['resumen']['temas_actualizar']++;
                }
            }
            // Temas activos del área que no vienen en el archivo: no se tocan
            foreach ($temasActivos as $kt => $t) {
                if (!isset($g['temas'][$kt])) {
                    $plan['no_incluidos']['temas'][] = $nombreArea . ' › ' . $t['tema'];
                }
            }
        }
        foreach ($areasActivas as $ka => $a) {
            if (!isset($grupos[$ka])) {
                $plan['no_incluidos']['areas'][] = $a['nombre'];
            }
        }

        usort($plan['errores'], function ($x, $y) { return (int)$x['fila'] - (int)$y['fila']; });
        return $plan;
    }

    /**
     * Valida y limpia una fila. Lo vacío queda null ("no cambia"). Los errores se agregan con la
     * fila; un valor inválido queda null para seguir revisando el resto del archivo.
     */
    private static function validarFila(array $f, $nro, array &$errores)
    {
        $v = array();
        $largo = function ($campo, $max) use ($f, $nro, &$errores) {
            if ($f[$campo] === '') {
                return null;
            }
            if (mb_strlen($f[$campo], 'UTF-8') > $max) {
                $errores[] = array('fila' => $nro, 'motivo' => self::etiqueta($campo) . ' es demasiado largo (máximo ' . $max . ' caracteres).');
                return null;
            }
            return $f[$campo];
        };
        $largo('area', Reglas::LARGO_NOMBRE_AREA);
        $largo('tema', Reglas::LARGO_TEMA);
        $v['resuelve'] = $largo('resuelve', Reglas::LARGO_RESUELVE);
        $v['horario'] = $largo('horario', Reglas::LARGO_HORARIO);
        $v['gerente'] = $f['gerente'] !== '' ? $f['gerente'] : null;
        $v['jefe'] = $f['jefe'] !== '' ? $f['jefe'] : null;
        $v['referente'] = $f['referente'] !== '' ? $f['referente'] : null;
        $v['palabras'] = null;
        if ($f['palabras'] !== '') {
            $palabras = Ficha::limpiarPalabras($f['palabras']);
            if (mb_strlen($palabras, 'UTF-8') > Reglas::LARGO_PALABRAS) {
                $errores[] = array('fila' => $nro, 'motivo' => 'Palabras clave es demasiado largo (máximo ' . Reglas::LARGO_PALABRAS . ' caracteres).');
            } else {
                $v['palabras'] = $palabras;
            }
        }

        $v['orden'] = null;
        if ($f['orden'] !== '') {
            if (preg_match('/^\d{1,4}$/', $f['orden'])) {
                $v['orden'] = (int)$f['orden'];
            } else {
                $errores[] = array('fila' => $nro, 'motivo' => 'El orden "' . $f['orden'] . '" no es un número.');
            }
        }

        $v['color'] = null;
        if ($f['color'] !== '') {
            $v['color'] = Reglas::normalizarColor($f['color']);
            if ($v['color'] === null) {
                $errores[] = array('fila' => $nro, 'motivo' => 'El color "' . $f['color'] . '" no es un color hex (por ejemplo #2F5FB3).');
            }
        }

        $v['mail'] = null;
        if ($f['mail'] !== '') {
            $motivo = Reglas::validarMail($f['mail']);
            if ($motivo !== null) {
                $errores[] = array('fila' => $nro, 'motivo' => 'Mail "' . $f['mail'] . '": ' . $motivo);
            } else {
                $v['mail'] = mb_strtolower($f['mail'], 'UTF-8');
            }
        }

        $v['celular'] = null;
        if ($f['celular'] !== '') {
            $cel = Reglas::limpiarCelular($f['celular']);
            if ($cel['error'] !== null) {
                $errores[] = array('fila' => $nro, 'motivo' => 'Celular "' . $f['celular'] . '": ' . $cel['error']);
            } else {
                $v['celular'] = $cel['digitos'];
            }
        }
        return $v;
    }

    /**
     * Persona por nombre (como figura en Internos, sin mayúsculas ni tildes). Si no se puede
     * resolver se avisa y queda vacía: en un área nueva queda sin persona; en una existente no
     * se cambia la que tenía (una celda que no se entiende no borra un dato).
     * @return array ['id' => int|null]
     */
    private static function resolverPersona($nombre, $etiqueta, array $grupo, $idActual, array $porNombre, array $personas, array &$avisos, $esReferente)
    {
        if ($nombre === null) {
            return array('id' => null);
        }
        $motivo = null;
        $ids = isset($porNombre[Reglas::clave($nombre)]) ? $porNombre[Reglas::clave($nombre)] : array();
        $activos = array();
        foreach ($ids as $id) {
            if ($personas[$id]['activo']) {
                $activos[] = $id;
            }
        }
        if (count($activos) === 1) {
            return array('id' => $activos[0]);
        }
        if (count($activos) > 1) {
            $motivo = 'está más de una vez activo en Internos';
        } elseif ($ids) {
            $motivo = 'está inactivo en Internos';
        } elseif (Reglas::clave($nombre) === 'equipo') {
            $motivo = 'no es una persona ("Equipo" sólo vale como referente de un tema)';
        } else {
            $motivo = 'no está en Internos';
        }
        $queda = $idActual !== null ? 'se deja el que tenía' : 'queda vacío';
        $avisos[] = array('fila' => $grupo['fila'], 'motivo' => $etiqueta . ' de "' . $grupo['nombre'] . '": "' . $nombre . '" ' . $motivo . ': ' . $queda . '.');
        return array('id' => $idActual);
    }

    /** @return array ['id' => int|null, 'equipo' => bool, 'cambia' => bool] */
    private static function resolverReferente($nombre, array $tema, $nombreArea, $temaActual, array $porNombre, array $personas, array &$avisos)
    {
        $sinCambio = array(
            'id' => $temaActual ? $temaActual['id_referente'] : null,
            'equipo' => $temaActual ? (bool)$temaActual['referente_equipo'] : false,
            'cambia' => false,
        );
        if ($nombre === null) {
            return $sinCambio;   // celda vacía: no borra
        }
        if (Reglas::clave($nombre) === 'equipo') {
            return array('id' => null, 'equipo' => true, 'cambia' => true);
        }
        $grupo = array('fila' => $tema['fila'], 'nombre' => $nombreArea . ' › ' . $tema['tema']);
        $antes = count($avisos);
        $r = self::resolverPersona($nombre, 'Referente', $grupo, $temaActual ? $temaActual['id_referente'] : null, $porNombre, $personas, $avisos, true);
        if (count($avisos) > $antes) {
            // No se pudo resolver: el tema nuevo queda sin referente, el existente no cambia
            if ($temaActual && $temaActual['referente_equipo']) {
                $avisos[count($avisos) - 1]['motivo'] = str_replace('queda vacío.', 'se deja "Equipo".', $avisos[count($avisos) - 1]['motivo']);
            }
            return $sinCambio;
        }
        return array('id' => $r['id'], 'equipo' => false, 'cambia' => true);
    }

    private static function etiqueta($campo)
    {
        $e = array(
            'area' => 'Área', 'tema' => 'Tema', 'orden' => 'Orden', 'color' => 'Color', 'resuelve' => 'Qué resuelve',
            'mail' => 'Mail de sector', 'mail_sector' => 'Mail de sector', 'celular' => 'Celular de sector',
            'celular_sector' => 'Celular de sector', 'horario' => 'Horario', 'gerente' => 'Gerente', 'id_gerente' => 'Gerente',
            'jefe' => 'Jefe', 'id_jefe' => 'Jefe', 'palabras' => 'Palabras clave',
        );
        return isset($e[$campo]) ? $e[$campo] : $campo;
    }

    /** Valor para mostrar en la vista previa ("de" y "a"). */
    private static function mostrar($campo, $valor, array $personas)
    {
        if ($valor === null || $valor === '') {
            return '';
        }
        if ($campo === 'id_gerente' || $campo === 'id_jefe') {
            return isset($personas[(int)$valor]) ? $personas[(int)$valor]['nombre'] : '';
        }
        if ($campo === 'celular_sector') {
            return Reglas::formatearCelular($valor);
        }
        return (string)$valor;
    }

    private static function mostrarReferente($id, $equipo, array $personas)
    {
        if ($equipo) {
            return 'Equipo';
        }
        return ($id !== null && isset($personas[(int)$id])) ? $personas[(int)$id]['nombre'] : '';
    }

    /**
     * Filas de la plantilla con los datos actuales: una por tema activo; los datos del área se
     * repiten. Referente: el nombre como figura en Internos o "Equipo". Una persona inactiva va
     * vacía (si se vuelve a subir, no la borra: una celda vacía no borra). Las áreas sin temas no
     * entran: una fila sin tema bloquearía la importación (y un área que no viene no se toca).
     */
    public static function filasPlantilla(array $areasActivas, array $personas)
    {
        $nombre = function ($id) use ($personas) {
            return ($id !== null && isset($personas[(int)$id]) && $personas[(int)$id]['activo']) ? $personas[(int)$id]['nombre'] : '';
        };
        $filas = array(array_values(self::COLUMNAS));
        foreach ($areasActivas as $a) {
            foreach ($a['temas'] as $t) {
                $filas[] = array(
                    $a['nombre'], (string)$a['orden'], $a['color'], $a['resuelve'], $a['mail_sector'],
                    $a['celular_sector'] !== '' ? Reglas::formatearCelular($a['celular_sector']) : '',
                    $a['horario'], $nombre($a['id_gerente']), $nombre($a['id_jefe']),
                    $t['tema'], $t['referente_equipo'] ? 'Equipo' : $nombre($t['id_referente']), $t['palabras_clave'],
                );
            }
        }
        return $filas;
    }
}

require_once __DIR__ . '/Ficha.php';
