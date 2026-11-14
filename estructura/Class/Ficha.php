<?php
/**
 * Estructura XL - validación pura de la ficha de un área (pantalla admin/area.php)
 *
 * Recibe lo que manda la pantalla y lo que hay en la base (como arrays) y devuelve los datos
 * limpios o los errores campo por campo. No lee la base: así se prueba sin conexión y la regla
 * es la misma que se usaría desde cualquier otro lado. "Si algo no pasa, no se guarda nada":
 * Directorio::guardarFicha() sólo escribe si esto no devolvió errores.
 *
 * Personas: gerente, jefe y referentes se eligen entre las personas ACTIVAS de Internos. Si la
 * ficha ya tenía guardada una persona que después se dio de baja, se puede guardar igual sin
 * cambiarla (la pantalla muestra el aviso "… ya no está activa en Internos"): si no, una baja en
 * Internos dejaría la ficha imposible de guardar hasta corregir esa persona.
 */
require_once __DIR__ . '/Reglas.php';

class Ficha
{
    /**
     * @param array $entrada  ['id', 'nombre', 'color', 'resuelve', 'mail', 'celular', 'horario',
     *                         'id_gerente', 'id_jefe', 'temas' => [['id', 'tema', 'id_referente',
     *                         'equipo', 'palabras_clave', 'quitar'], ...]] (temas en el orden de la pantalla)
     * @param array|null $actual  el área como está guardada (Directorio::areas(false)), null si es nueva
     * @param array $otrasAreas   las demás áreas (activas e inactivas): ['id', 'nombre', 'activa']
     * @param array $temasQuitados temas inactivos de esta área: ['id', 'tema']
     * @param array $personas     ID => ['nombre', 'activo']
     * @return array ['ok' => bool, 'errores' => [campo => motivo], 'errores_temas' => [i => [campo => motivo]],
     *                'datos' => área limpia, 'temas' => temas limpios (con 'orden'), 'quitar' => [ids]]
     */
    public static function validar(array $entrada, $actual, array $otrasAreas, array $temasQuitados, array $personas)
    {
        $err = array();
        $errTemas = array();
        $txt = function ($clave) use ($entrada) {
            return isset($entrada[$clave]) ? trim((string)$entrada[$clave]) : '';
        };

        /* ---- Área */
        $nombre = preg_replace('/\s+/u', ' ', $txt('nombre'));
        $claveNombre = Reglas::clave($nombre);
        if ($claveNombre === '') {
            $err['nombre'] = 'Escribí el nombre del área.';
        } elseif (mb_strlen($nombre, 'UTF-8') > Reglas::LARGO_NOMBRE_AREA) {
            $err['nombre'] = 'El nombre es demasiado largo (máximo ' . Reglas::LARGO_NOMBRE_AREA . ' caracteres).';
        } else {
            $idActual = $actual ? (int)$actual['id'] : 0;
            $activa = null;
            $inactiva = null;
            foreach ($otrasAreas as $o) {
                if ((int)$o['id'] === $idActual || Reglas::clave($o['nombre']) !== $claveNombre) {
                    continue;
                }
                if ($o['activa']) {
                    $activa = $o;
                } else {
                    $inactiva = $o;
                }
            }
            if ($activa) {
                $err['nombre'] = 'Ya hay un área activa que se llama "' . $activa['nombre'] . '".';
            } elseif ($inactiva) {
                $err['nombre'] = 'Existe desactivada: reactivala desde el listado.';
            }
        }

        $color = Reglas::normalizarColor($txt('color'));
        if ($color === null) {
            $err['color'] = 'Elegí un color.';
        }

        $resuelve = $txt('resuelve');
        if (mb_strlen($resuelve, 'UTF-8') > Reglas::LARGO_RESUELVE) {
            $err['resuelve'] = 'Tiene que ser una línea: máximo ' . Reglas::LARGO_RESUELVE . ' caracteres.';
        }

        $mail = mb_strtolower($txt('mail'), 'UTF-8');
        $motivo = Reglas::validarMail($mail);
        if ($motivo !== null) {
            $err['mail'] = $motivo;
        }

        $cel = Reglas::limpiarCelular($txt('celular'));
        if ($cel['error'] !== null) {
            $err['celular'] = $cel['error'];
        }

        $horario = $txt('horario');
        if (mb_strlen($horario, 'UTF-8') > Reglas::LARGO_HORARIO) {
            $err['horario'] = 'El horario es demasiado largo (máximo ' . Reglas::LARGO_HORARIO . ' caracteres).';
        }

        $idGerente = self::idPersona(isset($entrada['id_gerente']) ? $entrada['id_gerente'] : null);
        $motivo = self::validarPersona($idGerente, $actual ? $actual['id_gerente'] : null, $personas);
        if ($motivo !== null) {
            $err['id_gerente'] = $motivo;
        }
        $idJefe = self::idPersona(isset($entrada['id_jefe']) ? $entrada['id_jefe'] : null);
        $motivo = self::validarPersona($idJefe, $actual ? $actual['id_jefe'] : null, $personas);
        if ($motivo !== null) {
            $err['id_jefe'] = $motivo;
        }

        /* ---- Temas */
        $guardados = array();
        if ($actual) {
            foreach ($actual['temas'] as $t) {
                $guardados[(int)$t['id']] = $t;
            }
        }
        $quitados = array();
        foreach ($temasQuitados as $t) {
            $quitados[Reglas::clave($t['tema'])] = true;
        }

        $temas = array();
        $quitar = array();
        $vistos = array();
        $orden = 0;
        $listaTemas = isset($entrada['temas']) && is_array($entrada['temas']) ? $entrada['temas'] : array();
        foreach ($listaTemas as $i => $t) {
            $e = array();
            $id = !empty($t['id']) ? (int)$t['id'] : null;
            if ($id !== null && !isset($guardados[$id])) {
                // Un ID que no es de esta área (o ya no está activo): no se toca nada ajeno
                $errTemas[$i] = array('tema' => 'Este tema ya no está en la ficha: recargá la página.');
                continue;
            }
            if (!empty($t['quitar'])) {
                if ($id !== null) {
                    $quitar[] = $id;
                }
                continue;
            }

            $tema = preg_replace('/\s+/u', ' ', trim(isset($t['tema']) ? (string)$t['tema'] : ''));
            $claveTema = Reglas::clave($tema);
            if ($claveTema === '') {
                $e['tema'] = 'Escribí el tema.';
            } elseif (mb_strlen($tema, 'UTF-8') > Reglas::LARGO_TEMA) {
                $e['tema'] = 'El tema es demasiado largo (máximo ' . Reglas::LARGO_TEMA . ' caracteres).';
            } elseif (isset($vistos[$claveTema])) {
                $e['tema'] = 'Este tema está repetido en la ficha.';
            } elseif (isset($quitados[$claveTema])) {
                $e['tema'] = 'Existe un tema quitado con ese nombre: reactivalo desde "Temas quitados".';
            }
            $vistos[$claveTema] = true;

            $equipo = !empty($t['equipo']);
            $idRef = self::idPersona(isset($t['id_referente']) ? $t['id_referente'] : null);
            $motivo = Reglas::validarReferente($idRef, $equipo);
            if ($motivo === null) {
                $motivo = self::validarPersona($idRef, $id !== null ? $guardados[$id]['id_referente'] : null, $personas);
            }
            if ($motivo !== null) {
                $e['referente'] = $motivo;
            }

            $palabras = self::limpiarPalabras(isset($t['palabras_clave']) ? $t['palabras_clave'] : '');
            if (mb_strlen($palabras, 'UTF-8') > Reglas::LARGO_PALABRAS) {
                $e['palabras_clave'] = 'Demasiadas palabras clave (máximo ' . Reglas::LARGO_PALABRAS . ' caracteres).';
            }

            if ($e) {
                $errTemas[$i] = $e;
            }
            $temas[] = array(
                'id'               => $id,
                'tema'             => $tema,
                'orden'            => ++$orden,
                'id_referente'     => $equipo ? null : $idRef,
                'referente_equipo' => $equipo,
                'palabras_clave'   => $palabras,
            );
        }

        // Los temas activos que no vinieron en la pantalla (otra pestaña los agregó) no se tocan,
        // pero su nombre cuenta para no duplicar.
        $enPantalla = array();
        foreach ($temas as $t) {
            if ($t['id'] !== null) {
                $enPantalla[$t['id']] = true;
            }
        }
        foreach ($guardados as $id => $g) {
            if (!isset($enPantalla[$id]) && !in_array($id, $quitar, true) && isset($vistos[Reglas::clave($g['tema'])])) {
                foreach ($temas as $i => $t) {
                    if ($t['id'] === null && Reglas::clave($t['tema']) === Reglas::clave($g['tema'])) {
                        $errTemas[$i]['tema'] = 'Ya hay un tema con ese nombre en esta área: recargá la página.';
                    }
                }
            }
        }

        return array(
            'ok'            => !$err && !$errTemas,
            'errores'       => $err,
            'errores_temas' => $errTemas,
            'datos'         => array(
                'nombre'         => $nombre,
                'color'          => $color,
                'resuelve'       => $resuelve,
                'mail_sector'    => $mail,
                'celular_sector' => $cel['digitos'],
                'horario'        => $horario,
                'id_gerente'     => $idGerente,
                'id_jefe'        => $idJefe,
            ),
            'temas'         => $temas,
            'quitar'        => $quitar,
        );
    }

    /** null si la persona está bien; si no, el motivo. Vacío siempre está bien (es opcional). */
    private static function validarPersona($id, $idGuardado, array $personas)
    {
        if ($id === null) {
            return null;
        }
        $estado = Reglas::estadoPersona($id, $personas);
        if ($estado === 'activa') {
            return null;
        }
        if ($idGuardado !== null && (int)$idGuardado === $id) {
            return null;   // ya estaba guardada: se avisa en pantalla, pero no bloquea
        }
        return $estado === 'inactiva'
            ? $personas[$id]['nombre'] . ' ya no está activa en Internos: elegí otra persona.'
            : 'Elegí una persona de Internos de la lista.';
    }

    private static function idPersona($valor)
    {
        return ($valor !== null && $valor !== '' && ctype_digit((string)$valor) && (int)$valor > 0) ? (int)$valor : null;
    }

    /** "a ,b,, c " → "a, b, c": las palabras clave se guardan prolijas y sin vacías. */
    public static function limpiarPalabras($texto)
    {
        $res = array();
        foreach (explode(',', (string)$texto) as $p) {
            $p = trim(preg_replace('/\s+/u', ' ', $p));
            if ($p !== '') {
                $res[] = $p;
            }
        }
        return implode(', ', $res);
    }
}
