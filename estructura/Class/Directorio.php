<?php
/**
 * Estructura XL - Directorio: lectura y escritura de áreas, temas y búsquedas (base central)
 *
 * Acá sólo hay base: las reglas (normalizar, validar, ficha completa) están en Reglas.php y
 * Importacion.php, que no tocan la base y se prueban solas. Esta clase arma los datos, llama a
 * las reglas y guarda.
 *
 * "Sin el script, la pantalla no se cae": estado() dice qué script de sql/ falta. Las pantallas
 * nuevas lo muestran en lugar de romperse; Internos no depende de nada de esto.
 *
 * Personas: siempre por RO_T_INTERNOS_PERSONAL.ID. Una persona inactiva o que ya no está se
 * muestra "A definir" en la consulta y con un aviso en la administración.
 */
require_once __DIR__ . '/rutas.php';
require_once __DIR__ . '/Reglas.php';

class Directorio
{
    /** Tablas de sql/02_directorio_tablas.sql */
    const TABLAS = array('RO_T_DIR_AREAS', 'RO_T_DIR_TEMAS', 'RO_T_DIR_BUSQUEDAS', 'RO_T_DIR_BUSQUEDAS_DESCARTADAS', 'RO_T_DIR_IMPORTACIONES');

    /** @var resource|false */
    private $cid;
    private $memo = array();

    public function __construct()
    {
        require_once ESTRUCTURA_RRHH_CLASS_DIR . '/conexion.php';
        $this->cid = (new Conexion())->conectar('central');
        if (!$this->cid) {
            error_log('Directorio: sin conexión a la base central ' . print_r(sqlsrv_errors(), true));
        }
    }

    public function conectado()
    {
        return (bool)$this->cid;
    }

    /* ================================================================ Utilidades de base */

    /** Filas de una consulta parametrizada. Lanza excepción si falla (el que llama decide qué mostrar). */
    private function filas($sql, array $params = array())
    {
        if (!$this->cid) {
            throw new RuntimeException('Sin conexión a la base central');
        }
        $st = sqlsrv_query($this->cid, $sql, $params);
        if ($st === false) {
            throw new RuntimeException('Error SQL: ' . print_r(sqlsrv_errors(), true));
        }
        $res = array();
        while ($f = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) {
            $res[] = $f;
        }
        return $res;
    }

    private function ejecutar($sql, array $params = array())
    {
        if (!$this->cid) {
            throw new RuntimeException('Sin conexión a la base central');
        }
        $st = sqlsrv_query($this->cid, $sql, $params);
        if ($st === false) {
            throw new RuntimeException('Error SQL: ' . print_r(sqlsrv_errors(), true));
        }
        return $st;
    }

    /**
     * INSERT que devuelve el ID generado. Con OUTPUT INSERTED.ID en la misma sentencia: un
     * SELECT SCOPE_IDENTITY() aparte corre en otro lote y devuelve NULL. (OUTPUT sin INTO no
     * anda con triggers; las tablas del Directorio no tienen.) $sql va sin el VALUES: "INSERT INTO t (cols)".
     */
    public function insertarConId($sqlInsert, $sqlValues, array $params)
    {
        $f = $this->filas($sqlInsert . ' OUTPUT INSERTED.ID ' . $sqlValues, $params);
        if (empty($f[0]['ID'])) {
            throw new RuntimeException('El INSERT no devolvió el ID');
        }
        return (int)$f[0]['ID'];
    }

    private static function fecha($valor)
    {
        return $valor instanceof DateTimeInterface ? $valor->format('d/m/Y H:i') : null;
    }

    /* ================================================================ Estado de los scripts */

    /**
     * Qué falta para que funcionen las pantallas nuevas.
     * @return array ['ok' => bool, 'faltan' => [nombres de scripts], 'sin_conexion' => bool]
     */
    public function estado()
    {
        if (isset($this->memo['estado'])) {
            return $this->memo['estado'];
        }
        $estado = array('ok' => false, 'faltan' => array(), 'sin_conexion' => false);
        if (!$this->cid) {
            $estado['sin_conexion'] = true;
            return $this->memo['estado'] = $estado;
        }
        try {
            $existen = array();
            foreach ($this->filas("SELECT name FROM sys.tables WHERE name LIKE 'RO[_]T[_]DIR[_]%'") as $f) {
                $existen[$f['name']] = true;
            }
            foreach (self::TABLAS as $t) {
                if (!isset($existen[$t])) {
                    $estado['faltan'][] = '02_directorio_tablas.sql';
                    break;
                }
            }
            if (!$estado['faltan']) {
                $hayAreas = $this->filas('SELECT TOP 1 1 AS x FROM dbo.RO_T_DIR_AREAS');
                if (!$hayAreas) {
                    $estado['faltan'][] = '03_directorio_carga_inicial.sql';
                }
            }
            // Sin áreas cargadas las pantallas funcionan (vacías): sólo bloquea la falta de tablas
            $estado['ok'] = !in_array('02_directorio_tablas.sql', $estado['faltan'], true);
        } catch (Throwable $e) {
            error_log('Directorio::estado: ' . $e->getMessage());
            $estado['sin_conexion'] = true;
        }
        return $this->memo['estado'] = $estado;
    }

    /* ================================================================ Personas de Internos */

    /** Todas las personas de Internos: ID => ['nombre', 'activo', 'sector', 'interno', 'mail']. */
    public function personas()
    {
        if (isset($this->memo['personas'])) {
            return $this->memo['personas'];
        }
        $res = array();
        $sql = 'SELECT ID, NOMBRE_APELLIDO, SECTOR, ISNULL(INACTIVO, 0) AS INACTIVO,
                       ISNULL(CAST(INTERNO AS NVARCHAR(20)), \'\') AS INTERNO, ISNULL(MAIL, \'\') AS MAIL
                  FROM dbo.RO_T_INTERNOS_PERSONAL
                 ORDER BY NOMBRE_APELLIDO';
        foreach ($this->filas($sql) as $f) {
            $res[(int)$f['ID']] = array(
                'nombre'  => trim((string)$f['NOMBRE_APELLIDO']),
                'activo'  => !(int)$f['INACTIVO'],
                'sector'  => trim((string)$f['SECTOR']),
                'interno' => trim((string)$f['INTERNO']),
                'mail'    => trim((string)$f['MAIL']),
            );
        }
        return $this->memo['personas'] = $res;
    }

    /* ================================================================ Lectura de áreas y temas */

    /**
     * Áreas (con sus temas activos) ordenadas por ORDEN.
     * @param bool $soloActivas true para la consulta; false para la administración
     */
    public function areas($soloActivas = true)
    {
        $memo = 'areas' . ($soloActivas ? '1' : '0');
        if (isset($this->memo[$memo])) {
            return $this->memo[$memo];
        }
        $where = $soloActivas ? 'WHERE ACTIVA = 1' : '';
        $areas = array();
        foreach ($this->filas("SELECT * FROM dbo.RO_T_DIR_AREAS $where ORDER BY ACTIVA DESC, ORDEN, NOMBRE") as $f) {
            $areas[(int)$f['ID']] = self::areaDesdeFila($f);
        }
        if ($areas) {
            $sql = 'SELECT t.* FROM dbo.RO_T_DIR_TEMAS t
                      JOIN dbo.RO_T_DIR_AREAS a ON a.ID = t.ID_AREA
                     WHERE t.ACTIVO = 1 ' . ($soloActivas ? 'AND a.ACTIVA = 1' : '') . '
                     ORDER BY t.ID_AREA, t.ORDEN, t.ID';
            foreach ($this->filas($sql) as $f) {
                if (isset($areas[(int)$f['ID_AREA']])) {
                    $areas[(int)$f['ID_AREA']]['temas'][] = self::temaDesdeFila($f);
                }
            }
        }
        return $this->memo[$memo] = array_values($areas);
    }

    private static function areaDesdeFila(array $f)
    {
        return array(
            'id'             => (int)$f['ID'],
            'nombre'         => (string)$f['NOMBRE'],
            'color'          => (string)$f['COLOR'],
            'orden'          => (int)$f['ORDEN'],
            'resuelve'       => (string)$f['RESUELVE'],
            'mail_sector'    => (string)$f['MAIL_SECTOR'],
            'celular_sector' => (string)$f['CELULAR_SECTOR'],
            'horario'        => (string)$f['HORARIO'],
            'id_gerente'     => $f['ID_GERENTE'] !== null ? (int)$f['ID_GERENTE'] : null,
            'id_jefe'        => $f['ID_JEFE'] !== null ? (int)$f['ID_JEFE'] : null,
            'activa'         => (bool)$f['ACTIVA'],
            'usuario_modif'  => (string)($f['USUARIO_MODIF'] !== null ? $f['USUARIO_MODIF'] : $f['USUARIO_ALTA']),
            'fecha_modif'    => self::fecha($f['FECHA_MODIF'] !== null ? $f['FECHA_MODIF'] : $f['FECHA_ALTA']),
            'temas'          => array(),
        );
    }

    private static function temaDesdeFila(array $f)
    {
        return array(
            'id'               => (int)$f['ID'],
            'id_area'          => (int)$f['ID_AREA'],
            'tema'             => (string)$f['TEMA'],
            'orden'            => (int)$f['ORDEN'],
            'id_referente'     => $f['ID_REFERENTE'] !== null ? (int)$f['ID_REFERENTE'] : null,
            'referente_equipo' => (bool)$f['REFERENTE_EQUIPO'],
            'palabras_clave'   => (string)$f['PALABRAS_CLAVE'],
            'activo'           => (bool)$f['ACTIVO'],
        );
    }

    /**
     * Datos para las pantallas de consulta (Guía y Contactos): sólo lo activo, con las personas
     * ya resueltas a nombre (o null = "A definir") y el celular formateado con su link de WhatsApp.
     * No se mandan IDs de personas ni nada de la administración.
     */
    public function datosConsulta()
    {
        $personas = $this->personas();
        $areas = array();
        $temas = array();
        foreach ($this->areas(true) as $a) {
            $areas[] = array(
                'id'        => $a['id'],
                'nombre'    => $a['nombre'],
                'color'     => $a['color'],
                'resuelve'  => $a['resuelve'],
                'mail'      => $a['mail_sector'],
                'celular'   => $a['celular_sector'] !== '' ? Reglas::formatearCelular($a['celular_sector']) : '',
                'whatsapp'  => Reglas::linkWhatsapp($a['celular_sector']),
                'horario'   => $a['horario'],
                'gerente'   => self::nombreActivo($a['id_gerente'], $personas),
                'jefe'      => self::nombreActivo($a['id_jefe'], $personas),
            );
            foreach ($a['temas'] as $t) {
                $esPersona = !$t['referente_equipo'] && Reglas::estadoPersona($t['id_referente'], $personas) === 'activa';
                $p = $esPersona ? $personas[$t['id_referente']] : null;
                $temas[] = array(
                    'id'             => $t['id'],
                    'id_area'        => $a['id'],
                    'area'           => $a['nombre'],
                    'tema'           => $t['tema'],
                    'palabras_clave' => $t['palabras_clave'],
                    'referente'      => Reglas::textoReferente($t['id_referente'], $t['referente_equipo'], $a['nombre'], $personas),
                    // Sólo si es una persona: "Ver en Internos" la busca por nombre
                    'persona'        => $p ? $p['nombre'] : null,
                    // Interno y mail del referente (los mismos que muestra Internos, que también es pública)
                    'interno'        => $p ? $p['interno'] : '',
                    'mail_persona'   => $p ? $p['mail'] : '',
                );
            }
        }
        return array('areas' => $areas, 'temas' => $temas);
    }

    private static function nombreActivo($id, array $personas)
    {
        return Reglas::estadoPersona($id, $personas) === 'activa' ? $personas[(int)$id]['nombre'] : null;
    }

    /* ================================================================ Administración: lectura */

    /** Temas quitados (baja lógica) por área: ID_AREA => [['id', 'tema', 'fecha_baja', 'usuario_baja']] */
    public function temasQuitados()
    {
        $res = array();
        $sql = 'SELECT ID, ID_AREA, TEMA, USUARIO_BAJA, FECHA_BAJA FROM dbo.RO_T_DIR_TEMAS WHERE ACTIVO = 0 ORDER BY TEMA';
        foreach ($this->filas($sql) as $f) {
            $res[(int)$f['ID_AREA']][] = array('id' => (int)$f['ID'], 'tema' => (string)$f['TEMA'],
                'usuario_baja' => (string)$f['USUARIO_BAJA'], 'fecha_baja' => self::fecha($f['FECHA_BAJA']));
        }
        return $res;
    }

    /** Áreas (activas e inactivas) con sus temas quitados: el estado contra el que se valida. */
    public function areasCompletas()
    {
        $quitados = $this->temasQuitados();
        $areas = $this->areas(false);
        foreach ($areas as $i => $a) {
            $areas[$i]['temas_quitados'] = isset($quitados[$a['id']]) ? $quitados[$a['id']] : array();
        }
        return $areas;
    }

    public function area($id)
    {
        foreach ($this->areasCompletas() as $a) {
            if ($a['id'] === (int)$id) {
                return $a;
            }
        }
        return null;
    }

    /**
     * Búsquedas de los últimos $dias días sin resultado, agrupadas por texto normalizado, de la
     * más buscada a la menos. Las descartadas no aparecen, salvo que se hayan vuelto a buscar
     * DESPUÉS de descartarlas (es una señal nueva).
     */
    public function busquedasSinResultado($dias = 30)
    {
        $sql = 'SELECT b.TEXTO_NORMALIZADO, COUNT(*) AS VECES, MAX(b.FECHA) AS ULTIMA, MAX(b.TEXTO) AS EJEMPLO
                  FROM dbo.RO_T_DIR_BUSQUEDAS b
                  LEFT JOIN dbo.RO_T_DIR_BUSQUEDAS_DESCARTADAS d ON d.TEXTO_NORMALIZADO = b.TEXTO_NORMALIZADO
                 WHERE b.CANT_RESULTADOS = 0
                   AND b.FECHA >= DATEADD(DAY, -?, GETDATE())
                   AND (d.ID IS NULL OR b.FECHA > d.FECHA_DESCARTE)
                 GROUP BY b.TEXTO_NORMALIZADO
                 ORDER BY COUNT(*) DESC, MAX(b.FECHA) DESC';
        $res = array();
        foreach ($this->filas($sql, array((int)$dias)) as $f) {
            $res[] = array('normalizado' => (string)$f['TEXTO_NORMALIZADO'], 'veces' => (int)$f['VECES'],
                'ultima' => self::fecha($f['ULTIMA']), 'ejemplo' => (string)$f['EJEMPLO']);
        }
        return $res;
    }

    /* ================================================================ Administración: escritura */

    /** Corre $fn dentro de una transacción: o todo, o nada. */
    private function enTransaccion($fn)
    {
        if (!$this->cid || !sqlsrv_begin_transaction($this->cid)) {
            throw new RuntimeException('No se pudo iniciar la transacción');
        }
        try {
            $r = $fn();
            sqlsrv_commit($this->cid);
            $this->memo = array();
            return $r;
        } catch (Throwable $e) {
            sqlsrv_rollback($this->cid);
            throw $e;
        }
    }

    /** Descarta una búsqueda del panel (sin borrarla): esconde las hechas hasta ahora. */
    public function descartarBusqueda($normalizado, $usuario)
    {
        $this->ejecutar(
            'IF EXISTS (SELECT 1 FROM dbo.RO_T_DIR_BUSQUEDAS_DESCARTADAS WHERE TEXTO_NORMALIZADO = ?)
                 UPDATE dbo.RO_T_DIR_BUSQUEDAS_DESCARTADAS
                    SET FECHA_DESCARTE = GETDATE(), USUARIO_MODIF = ?, FECHA_MODIF = GETDATE()
                  WHERE TEXTO_NORMALIZADO = ?
             ELSE
                 INSERT INTO dbo.RO_T_DIR_BUSQUEDAS_DESCARTADAS (TEXTO_NORMALIZADO, FECHA_DESCARTE, USUARIO_ALTA)
                 VALUES (?, GETDATE(), ?)',
            array($normalizado, $usuario, $normalizado, $normalizado, $usuario)
        );
    }

    /**
     * Desactiva (baja lógica: deja de verse en la consulta) o reactiva un área.
     * @return string|null el motivo si no se pudo
     */
    public function cambiarEstadoArea($id, $activar, $usuario)
    {
        $area = $this->area($id);
        if (!$area) {
            return 'El área no existe.';
        }
        if ($activar) {
            if ($area['activa']) {
                return null;
            }
            foreach ($this->areas(true) as $o) {
                if (Reglas::clave($o['nombre']) === Reglas::clave($area['nombre'])) {
                    return 'Ya hay otra área activa que se llama "' . $o['nombre'] . '".';
                }
            }
            $orden = 1;
            foreach ($this->areas(true) as $o) {
                $orden = max($orden, $o['orden'] + 1);
            }
            // Vuelve al final: su orden viejo puede estar ocupado
            $this->ejecutar('UPDATE dbo.RO_T_DIR_AREAS SET ACTIVA = 1, ORDEN = ?, USUARIO_BAJA = NULL, FECHA_BAJA = NULL,
                                    USUARIO_MODIF = ?, FECHA_MODIF = GETDATE() WHERE ID = ?', array($orden, $usuario, (int)$id));
        } else {
            $this->ejecutar('UPDATE dbo.RO_T_DIR_AREAS SET ACTIVA = 0, USUARIO_BAJA = ?, FECHA_BAJA = GETDATE(),
                                    USUARIO_MODIF = ?, FECHA_MODIF = GETDATE() WHERE ID = ? AND ACTIVA = 1', array($usuario, $usuario, (int)$id));
        }
        $this->memo = array();
        return null;
    }

    /**
     * Sube o baja un área un lugar entre las activas. Renumera 1..n antes de intercambiar: así un
     * orden repetido o salteado (por la importación o por reactivar) no deja el botón sin efecto.
     */
    public function moverArea($id, $direccion, $usuario)
    {
        $ids = array();
        foreach ($this->areas(true) as $a) {
            $ids[] = $a['id'];
        }
        $pos = array_search((int)$id, $ids, true);
        if ($pos === false) {
            return 'El área no está activa.';
        }
        $otro = $direccion === 'subir' ? $pos - 1 : $pos + 1;
        if ($otro < 0 || $otro >= count($ids)) {
            return null;   // ya está en el borde
        }
        $tmp = $ids[$pos];
        $ids[$pos] = $ids[$otro];
        $ids[$otro] = $tmp;
        $self = $this;
        $this->enTransaccion(function () use ($self, $ids, $id, $usuario) {
            foreach ($ids as $i => $idArea) {
                $self->ejecutarPublico('UPDATE dbo.RO_T_DIR_AREAS SET ORDEN = ? WHERE ID = ?', array($i + 1, $idArea));
            }
            $self->ejecutarPublico('UPDATE dbo.RO_T_DIR_AREAS SET USUARIO_MODIF = ?, FECHA_MODIF = GETDATE() WHERE ID = ?', array($usuario, (int)$id));
        });
        return null;
    }

    /** Para los closures de enTransaccion (PHP 7.3: no hay $this tipado en closures estáticas). */
    public function ejecutarPublico($sql, array $params)
    {
        return $this->ejecutar($sql, $params);
    }

    /** Reactiva un tema quitado (vuelve al final de su área). @return string|null motivo */
    public function reactivarTema($idTema, $usuario)
    {
        $f = $this->filas('SELECT ID, ID_AREA, TEMA, ACTIVO FROM dbo.RO_T_DIR_TEMAS WHERE ID = ?', array((int)$idTema));
        if (!$f) {
            return 'El tema no existe.';
        }
        $area = $this->area((int)$f[0]['ID_AREA']);
        foreach ($area ? $area['temas'] : array() as $t) {
            if (Reglas::clave($t['tema']) === Reglas::clave($f[0]['TEMA'])) {
                return 'Ya hay un tema activo con ese nombre en el área.';
            }
        }
        $orden = 1;
        foreach ($area ? $area['temas'] : array() as $t) {
            $orden = max($orden, $t['orden'] + 1);
        }
        $this->ejecutar('UPDATE dbo.RO_T_DIR_TEMAS SET ACTIVO = 1, ORDEN = ?, USUARIO_BAJA = NULL, FECHA_BAJA = NULL,
                                USUARIO_MODIF = ?, FECHA_MODIF = GETDATE() WHERE ID = ?', array($orden, $usuario, (int)$idTema));
        $this->ejecutar('UPDATE dbo.RO_T_DIR_AREAS SET USUARIO_MODIF = ?, FECHA_MODIF = GETDATE() WHERE ID = ?',
            array($usuario, (int)$f[0]['ID_AREA']));
        $this->memo = array();
        return null;
    }

    /**
     * Valida (Ficha::validar) y guarda la ficha de un área en una transacción. Si algo no pasa,
     * no se guarda nada y se devuelven los errores campo por campo.
     * @return array ['ok', 'id', 'errores', 'errores_temas']
     */
    public function guardarFicha(array $entrada, $usuario)
    {
        require_once __DIR__ . '/Ficha.php';
        $id = !empty($entrada['id']) ? (int)$entrada['id'] : null;
        $areas = $this->areasCompletas();
        $actual = null;
        foreach ($areas as $a) {
            if ($a['id'] === $id) {
                $actual = $a;
            }
        }
        if ($id !== null && (!$actual || !$actual['activa'])) {
            return array('ok' => false, 'id' => $id, 'errores' => array('general' => 'El área no existe o está desactivada.'), 'errores_temas' => array());
        }
        $v = Ficha::validar($entrada, $actual, $areas, $actual ? $actual['temas_quitados'] : array(), $this->personas());
        if (!$v['ok']) {
            return array('ok' => false, 'id' => $id, 'errores' => $v['errores'], 'errores_temas' => $v['errores_temas']);
        }

        $self = $this;
        $areasActivas = $this->areas(true);
        $idGuardado = $this->enTransaccion(function () use ($self, $v, $id, $usuario, $areasActivas) {
            $d = $v['datos'];
            $vacio = function ($s) { return ($s === null || $s === '') ? null : $s; };
            if ($id === null) {
                $orden = 1;
                foreach ($areasActivas as $a) {
                    $orden = max($orden, $a['orden'] + 1);
                }
                $id = $self->insertarConId(
                    'INSERT INTO dbo.RO_T_DIR_AREAS (NOMBRE, COLOR, ORDEN, RESUELVE, MAIL_SECTOR, CELULAR_SECTOR, HORARIO, ID_GERENTE, ID_JEFE, USUARIO_ALTA)',
                    'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    array($d['nombre'], $d['color'], $orden, $vacio($d['resuelve']), $vacio($d['mail_sector']),
                        $vacio($d['celular_sector']), $vacio($d['horario']), $d['id_gerente'], $d['id_jefe'], $usuario));
            } else {
                $self->ejecutarPublico(
                    'UPDATE dbo.RO_T_DIR_AREAS
                        SET NOMBRE = ?, COLOR = ?, RESUELVE = ?, MAIL_SECTOR = ?, CELULAR_SECTOR = ?, HORARIO = ?,
                            ID_GERENTE = ?, ID_JEFE = ?, USUARIO_MODIF = ?, FECHA_MODIF = GETDATE()
                      WHERE ID = ? AND ACTIVA = 1',
                    array($d['nombre'], $d['color'], $vacio($d['resuelve']), $vacio($d['mail_sector']), $vacio($d['celular_sector']),
                        $vacio($d['horario']), $d['id_gerente'], $d['id_jefe'], $usuario, $id));
            }
            // Bajas primero: así un tema nuevo puede llevar el nombre de uno que se quitó en la misma edición
            foreach ($v['quitar'] as $idTema) {
                $self->ejecutarPublico('UPDATE dbo.RO_T_DIR_TEMAS SET ACTIVO = 0, USUARIO_BAJA = ?, FECHA_BAJA = GETDATE()
                                         WHERE ID = ? AND ID_AREA = ? AND ACTIVO = 1', array($usuario, $idTema, $id));
            }
            foreach ($v['temas'] as $t) {
                $params = array($t['tema'], $t['orden'], $t['id_referente'], $t['referente_equipo'] ? 1 : 0, $vacio($t['palabras_clave']), $usuario);
                if ($t['id'] === null) {
                    $self->ejecutarPublico('INSERT INTO dbo.RO_T_DIR_TEMAS (TEMA, ORDEN, ID_REFERENTE, REFERENTE_EQUIPO, PALABRAS_CLAVE, USUARIO_ALTA, ID_AREA)
                                             VALUES (?, ?, ?, ?, ?, ?, ?)', array_merge($params, array($id)));
                } else {
                    $self->ejecutarPublico('UPDATE dbo.RO_T_DIR_TEMAS
                                               SET TEMA = ?, ORDEN = ?, ID_REFERENTE = ?, REFERENTE_EQUIPO = ?, PALABRAS_CLAVE = ?,
                                                   USUARIO_MODIF = ?, FECHA_MODIF = GETDATE()
                                             WHERE ID = ? AND ID_AREA = ?', array_merge($params, array($t['id'], $id)));
                }
            }
            return $id;
        });
        return array('ok' => true, 'id' => $idGuardado, 'errores' => array(), 'errores_temas' => array());
    }

    /**
     * Aplica un plan de Importacion::planificar() SIN errores, en una transacción, y deja el
     * registro en RO_T_DIR_IMPORTACIONES. El plan se calcula de nuevo justo antes (no se usa el de
     * la vista previa): si alguien cambió algo en el medio, se aplica sobre lo último.
     * @return array resumen
     */
    public function aplicarImportacion(array $plan, $archivo, $usuario)
    {
        if ($plan['errores']) {
            throw new RuntimeException('El plan tiene errores');
        }
        $self = $this;
        return $this->enTransaccion(function () use ($self, $plan, $archivo, $usuario) {
            $ids = array();   // clave de área → ID (para los temas de las áreas nuevas)
            foreach ($plan['areas'] as $a) {
                $val = $a['valores'];
                if ($a['accion'] === 'crear') {
                    $ids[$a['clave']] = $self->insertarConId(
                        'INSERT INTO dbo.RO_T_DIR_AREAS (NOMBRE, COLOR, ORDEN, RESUELVE, MAIL_SECTOR, CELULAR_SECTOR, HORARIO, ID_GERENTE, ID_JEFE, USUARIO_ALTA)',
                        'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                        array($val['nombre'], $val['color'], $val['orden'], $val['resuelve'], $val['mail_sector'], $val['celular_sector'],
                            $val['horario'], $val['id_gerente'], $val['id_jefe'], $usuario));
                    continue;
                }
                $ids[$a['clave']] = $a['id'];
                if ($a['accion'] === 'actualizar') {
                    $columnas = array('orden' => 'ORDEN', 'color' => 'COLOR', 'resuelve' => 'RESUELVE', 'mail_sector' => 'MAIL_SECTOR',
                        'celular_sector' => 'CELULAR_SECTOR', 'horario' => 'HORARIO', 'id_gerente' => 'ID_GERENTE', 'id_jefe' => 'ID_JEFE');
                    $set = array();
                    $params = array();
                    foreach ($val as $campo => $valor) {
                        $set[] = $columnas[$campo] . ' = ?';
                        $params[] = $valor;
                    }
                    $params[] = $usuario;
                    $params[] = $a['id'];
                    $self->ejecutarPublico('UPDATE dbo.RO_T_DIR_AREAS SET ' . implode(', ', $set) . ', USUARIO_MODIF = ?, FECHA_MODIF = GETDATE()
                                             WHERE ID = ? AND ACTIVA = 1', $params);
                }
            }
            foreach ($plan['temas'] as $t) {
                $val = $t['valores'];
                if ($t['accion'] === 'crear') {
                    $self->ejecutarPublico(
                        'INSERT INTO dbo.RO_T_DIR_TEMAS (ID_AREA, TEMA, ORDEN, ID_REFERENTE, REFERENTE_EQUIPO, PALABRAS_CLAVE, USUARIO_ALTA)
                         VALUES (?, ?, ?, ?, ?, ?, ?)',
                        array($ids[$t['clave_area']], $val['tema'], $val['orden'], $val['id_referente'], $val['referente_equipo'] ? 1 : 0,
                            $val['palabras_clave'] !== '' ? $val['palabras_clave'] : null, $usuario));
                } elseif ($t['accion'] === 'actualizar') {
                    $set = array();
                    $params = array();
                    if (array_key_exists('id_referente', $val)) {
                        $set[] = 'ID_REFERENTE = ?, REFERENTE_EQUIPO = ?';
                        $params[] = $val['id_referente'];
                        $params[] = $val['referente_equipo'] ? 1 : 0;
                    }
                    if (array_key_exists('palabras_clave', $val)) {
                        $set[] = 'PALABRAS_CLAVE = ?';
                        $params[] = $val['palabras_clave'];
                    }
                    $params[] = $usuario;
                    $params[] = $t['id'];
                    $self->ejecutarPublico('UPDATE dbo.RO_T_DIR_TEMAS SET ' . implode(', ', $set) . ', USUARIO_MODIF = ?, FECHA_MODIF = GETDATE()
                                             WHERE ID = ? AND ACTIVO = 1', $params);
                }
            }
            $r = $plan['resumen'];
            $detalle = json_encode(array(
                'areas' => array_values(array_filter($plan['areas'], function ($x) { return $x['accion'] !== 'igual'; })),
                'temas' => array_values(array_filter($plan['temas'], function ($x) { return $x['accion'] !== 'igual'; })),
                'avisos' => $plan['avisos'],
            ), JSON_UNESCAPED_UNICODE);
            $self->ejecutarPublico(
                'INSERT INTO dbo.RO_T_DIR_IMPORTACIONES (ARCHIVO, AREAS_CREADAS, AREAS_ACTUALIZADAS, TEMAS_CREADOS, TEMAS_ACTUALIZADOS,
                                                         FILAS_OMITIDAS, AVISOS, DETALLE, USUARIO_ALTA)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                array(mb_substr((string)$archivo, 0, 255, 'UTF-8'), $r['areas_crear'], $r['areas_actualizar'], $r['temas_crear'],
                    $r['temas_actualizar'], $plan['omitidas'], count($plan['avisos']), $detalle, $usuario));
            return $r;
        });
    }

    /* ================================================================ Registro de búsquedas */

    /**
     * Guarda una búsqueda de la Guía. La cantidad de resultados la calcula el servidor con la
     * misma regla que el navegador (no se confía en lo que manda la página).
     * @return bool false si no se guardó (el que llama no le avisa nada al usuario)
     */
    public function registrarBusqueda($texto, $idArea, $usuario, $codClient)
    {
        $texto = mb_substr(trim((string)$texto), 0, Reglas::LARGO_BUSQUEDA, 'UTF-8');
        $normalizado = mb_substr(Reglas::normalizarBusqueda($texto), 0, Reglas::LARGO_BUSQUEDA, 'UTF-8');

        $datos = $this->datosConsulta();
        $idsAreas = array();
        foreach ($datos['areas'] as $a) {
            $idsAreas[$a['id']] = true;
        }
        $idArea = ($idArea !== null && isset($idsAreas[(int)$idArea])) ? (int)$idArea : null;
        $cant = count(Reglas::buscar($texto, $datos['temas'], $idArea));

        $this->ejecutar(
            'INSERT INTO dbo.RO_T_DIR_BUSQUEDAS (TEXTO, TEXTO_NORMALIZADO, CANT_RESULTADOS, ID_AREA_FILTRO, USUARIO, COD_CLIENT)
             VALUES (?, ?, ?, ?, ?, ?)',
            array($texto, $normalizado, $cant, $idArea,
                $usuario !== null ? mb_substr((string)$usuario, 0, 100, 'UTF-8') : null,
                $codClient !== null ? substr((string)$codClient, 0, 20) : null)
        );
        return true;
    }
}
