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

    /** ID generado por el último INSERT de esta conexión. */
    private function ultimoId()
    {
        $f = $this->filas('SELECT CAST(SCOPE_IDENTITY() AS INT) AS id');
        return isset($f[0]['id']) ? (int)$f[0]['id'] : 0;
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

    /** Todas las personas de Internos: ID => ['nombre', 'activo', 'sector']. */
    public function personas()
    {
        if (isset($this->memo['personas'])) {
            return $this->memo['personas'];
        }
        $res = array();
        $sql = 'SELECT ID, NOMBRE_APELLIDO, SECTOR, ISNULL(INACTIVO, 0) AS INACTIVO
                  FROM dbo.RO_T_INTERNOS_PERSONAL
                 ORDER BY NOMBRE_APELLIDO';
        foreach ($this->filas($sql) as $f) {
            $res[(int)$f['ID']] = array(
                'nombre' => trim((string)$f['NOMBRE_APELLIDO']),
                'activo' => !(int)$f['INACTIVO'],
                'sector' => trim((string)$f['SECTOR']),
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
                $temas[] = array(
                    'id'             => $t['id'],
                    'id_area'        => $a['id'],
                    'area'           => $a['nombre'],
                    'tema'           => $t['tema'],
                    'palabras_clave' => $t['palabras_clave'],
                    'referente'      => Reglas::textoReferente($t['id_referente'], $t['referente_equipo'], $a['nombre'], $personas),
                    // Sólo si es una persona: "Ver en Internos" la busca por nombre
                    'persona'        => $esPersona ? $personas[$t['id_referente']]['nombre'] : null,
                );
            }
        }
        return array('areas' => $areas, 'temas' => $temas);
    }

    private static function nombreActivo($id, array $personas)
    {
        return Reglas::estadoPersona($id, $personas) === 'activa' ? $personas[(int)$id]['nombre'] : null;
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
