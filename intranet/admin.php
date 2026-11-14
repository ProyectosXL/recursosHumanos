<?php
/**
 * Intranet XL - carga de novedades
 * Acceso: intranet.comunicados.publicar, intranet.fechas.publicar o intranet.plantillas.administrar
 * (sin permiso, vuelve a la intranet).
 * Solapas según permiso: Comunicados, Plantillas y Fechas. Alta, edición y baja lógica, con auditoría.
 * Los feriados se muestran solo para consulta: se cargan por fuera de esta pantalla.
 *
 * Plantillas (recursosHumanos/intranet/sql/11): un comunicado marcado como plantilla, con el mismo formulario. Nunca
 * se publica. "Nuevo comunicado" ofrece primero elegir una plantilla activa o empezar de cero; al
 * elegirla, el formulario se abre con su contenido y una copia de sus archivos (temporales: si se
 * cancela, no queda nada).
 */
require_once __DIR__ . '/Class/bootstrap.php';
require_once __DIR__ . '/Class/IntranetArchivos.php';

Auth::exigir();

$puedeCom = Intranet::puedePublicarComunicados();
$puedeFec = Intranet::puedePublicarFechas();
$puedePla = Intranet::puedeAdministrarPlantillas(); // incluye que recursosHumanos/intranet/sql/11 esté corrido
if (!$puedeCom && !$puedeFec && !$puedePla) {
    header('Location: ' . Intranet::URL_INTRANET);
    exit;
}

$e = [Intranet::class, 'e'];
$hoy = Intranet::hoy();
$usuario = (string)Auth::username();
$tagTipo = ['FERIADO' => 'intra-tag-feriado', 'COMERCIAL' => 'intra-tag-comercial', 'OPERATIVA' => 'intra-tag-operativa'];

$solapas = [];
if ($puedeCom) $solapas['comunicados'] = 'Comunicados';
if ($puedePla) $solapas['plantillas'] = 'Plantillas';
if ($puedeFec) $solapas['fechas'] = 'Fechas';
$iconosSolapa = ['comunicados' => 'fa-bullhorn', 'plantillas' => 'fa-layer-group', 'fechas' => 'fa-calendar-days'];
$solapa = $_POST['solapa'] ?? $_GET['solapa'] ?? '';
if (!isset($solapas[$solapa])) {
    $solapa = array_key_first($solapas);
}

// Protección contra envíos falsificados desde otro sitio
$csrf = Intranet::csrf();
$conInteraccion = Intranet::tieneInteraccion(); // comentarios y reacciones (recursosHumanos/intranet/sql/10)

// Imágenes y PDF: solo con la carpeta configurada y recursosHumanos/intranet/sql/09 corrido
$archivosOk = ($puedeCom || $puedePla) && IntranetArchivos::disponible();
$conPlantillas = Intranet::tienePlantillas(); // recursosHumanos/intranet/sql/11
$v2 = Intranet::tieneV2(); // esquemas, formato, borradores (recursosHumanos/intranet/sql/08)
if ($archivosOk) {
    IntranetArchivos::limpiarTemporales(); // lo subido y nunca guardado, a las 24 h (como mucho una vez por hora)
}

function intraVolver(string $solapa, string $mensaje, string $tipo = 'success', array $extra = []): void
{
    $_SESSION['intra_flash'] = ['tipo' => $tipo, 'mensaje' => $mensaje];
    header('Location: ' . Intranet::URL_ADMIN . '?' . http_build_query(array_merge(['solapa' => $solapa], $extra)));
    exit;
}

// ['tipo' => 'comunicado'|'plantilla'|'fecha', 'id' => ?int, 'datos' => [...], 'errores' => [...],
//  'origen' => ['id', 'nombre'] (plantilla de la que sale un comunicado)]
$form = null;
$elegirPlantilla = null; // plantillas activas para el paso previo a "Nuevo comunicado"

// ==================== Acciones (POST) ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        intraVolver($solapa, 'La sesión venció. Probá de nuevo.', 'danger');
    }
    $accion = (string)($_POST['accion'] ?? '');
    $id = (int)($_POST['id'] ?? 0) ?: null;

    if ($accion === 'guardar_comunicado' && $puedeCom) {
        // Estado actual (de la base, no del formulario): define qué botones hay y qué se guarda
        $existente = $id ? IntranetAdmin::comunicado($id) : null;
        if ($id && !$existente) {
            intraVolver('comunicados', 'No encontramos ese comunicado.', 'warning');
        }
        $estadoActual = $existente ? (string)($existente['estado'] ?? 'PUBLICADO') : null;
        // Basado en una plantilla: solo al crear (después queda guardado en el comunicado)
        $origen = null;
        if ($existente && !empty($existente['plantilla_origen_id'])) {
            $origen = ['id' => (int)$existente['plantilla_origen_id'], 'nombre' => (string)$existente['plantilla_origen_nombre']];
        } elseif (!$id && ($oid = (int)($_POST['plantilla_origen_id'] ?? 0)) && ($pOrigen = IntranetAdmin::comunicado($oid, true))) {
            $origen = ['id' => $oid, 'nombre' => (string)$pOrigen['plantilla_nombre']];
        }
        // "Guardar borrador" solo existe en uno nuevo o en un borrador: un publicado se despublica desde el listado
        $estadoNuevo = ($v2 && ($_POST['modo'] ?? '') === 'borrador' && $estadoActual !== 'PUBLICADO') ? 'BORRADOR' : 'PUBLICADO';

        // Lote del formulario: lo subido mientras estaba abierto (se conserva si vuelve con errores)
        $lote = IntranetArchivos::loteValido($_POST['lote'] ?? '') ?? IntranetArchivos::loteNuevo();
        [$archivos, $errArchivos] = IntranetArchivos::delFormulario($_POST['archivos'] ?? [], $lote, $id, $usuario);
        $cantImagenes = count(array_filter($archivos, fn($a) => $a['tipo'] === 'IMAGEN'));
        [$datos, $errores] = IntranetAdmin::validarComunicado($_POST, $cantImagenes, $archivosOk, $estadoNuevo);
        $errores += $errArchivos;
        $datos['plantilla_origen_id'] = $origen['id'] ?? null;
        if (!$errores) {
            $guardadoId = IntranetAdmin::guardarComunicado($id, $datos, $usuario);
            if ($guardadoId) {
                $desde = IntranetAdmin::fechaInput($datos['vigente_desde']);
                if ($estadoNuevo === 'BORRADOR') {
                    $msg = 'Borrador guardado. No se muestra hasta que lo publiques.';
                } elseif ($desde > $hoy) {
                    $msg = 'Comunicado programado: aparece el ' . Intranet::ddmm($desde) . '.';
                } else {
                    $msg = $estadoActual === 'PUBLICADO' ? 'Comunicado actualizado.' : 'Comunicado publicado.';
                }
                if ($archivosOk && !IntranetArchivos::vincular($guardadoId, $lote, $archivos, $usuario)) {
                    intraVolver('comunicados', $msg . ' Pero algunos archivos no se pudieron guardar: editalo y revisalos.', 'warning');
                }
                intraVolver('comunicados', $msg);
            }
            $errores['_general'] = 'No se pudo guardar. Probá de nuevo en unos minutos.';
        }
        $form = ['tipo' => 'comunicado', 'id' => $id, 'datos' => $datos, 'errores' => $errores,
                 'lote' => $lote, 'archivos' => $archivos, 'estado' => $estadoActual, 'origen' => $origen];

    } elseif ($accion === 'guardar_plantilla' && $puedePla) {
        $existente = $id ? IntranetAdmin::comunicado($id, true) : null;
        if ($id && !$existente) {
            intraVolver('plantillas', 'No encontramos esa plantilla.', 'warning');
        }
        $lote = IntranetArchivos::loteValido($_POST['lote'] ?? '') ?? IntranetArchivos::loteNuevo();
        [$archivos, $errArchivos] = IntranetArchivos::delFormulario($_POST['archivos'] ?? [], $lote, $id, $usuario);
        $cantImagenes = count(array_filter($archivos, fn($a) => $a['tipo'] === 'IMAGEN'));
        [$datos, $errores] = IntranetAdmin::validarPlantilla($_POST, $cantImagenes, $archivosOk);
        $errores += $errArchivos;
        if (!$errores) {
            $guardadoId = IntranetAdmin::guardarComunicado($id, $datos, $usuario);
            if ($guardadoId) {
                $msg = $id ? 'Plantilla actualizada.' : 'Plantilla creada. Ya se ofrece al crear un comunicado.';
                if ($archivosOk && !IntranetArchivos::vincular($guardadoId, $lote, $archivos, $usuario)) {
                    intraVolver('plantillas', $msg . ' Pero algunos archivos no se pudieron guardar: editala y revisalos.', 'warning');
                }
                intraVolver('plantillas', $msg);
            }
            $errores['_general'] = 'No se pudo guardar. Probá de nuevo en unos minutos.';
        }
        $form = ['tipo' => 'plantilla', 'id' => $id, 'datos' => $datos, 'errores' => $errores,
                 'lote' => $lote, 'archivos' => $archivos, 'estado' => null, 'origen' => null];

    } elseif ($accion === 'duplicar_plantilla' && $puedePla && $id) {
        $nuevo = IntranetAdmin::duplicarPlantilla($id, $usuario);
        if (!$nuevo) {
            intraVolver('plantillas', 'No se pudo duplicar. Probá de nuevo en unos minutos.', 'danger');
        }
        intraVolver('plantillas', 'Copia creada, inactiva y al final de la lista. Revisala y reactivala cuando esté lista.', 'success', ['editar' => $nuevo]);

    } elseif ($accion === 'mover_plantilla' && $puedePla && $id) {
        $ok = IntranetAdmin::moverPlantilla($id, (int)($_POST['paso'] ?? 0), $usuario);
        intraVolver('plantillas', $ok ? 'Orden actualizado.' : 'No se pudo cambiar el orden. Probá de nuevo en unos minutos.', $ok ? 'success' : 'danger');

    } elseif ($accion === 'estado_plantilla' && $puedePla && $id) {
        $activar = !empty($_POST['activo']);
        $ok = IntranetAdmin::comunicado($id, true) && IntranetAdmin::cambiarActivo('comunicados', $id, $activar, $usuario);
        intraVolver('plantillas', $ok
            ? ($activar ? 'Plantilla reactivada: se vuelve a ofrecer.' : 'Plantilla desactivada: ya no se ofrece.')
            : 'No se pudo guardar el cambio. Probá de nuevo en unos minutos.', $ok ? 'success' : 'danger');

    } elseif ($accion === 'publicar_comunicado' && $puedeCom && $v2 && $id) {
        // Publicar un borrador desde el listado: valida todo; si falta algo, abre el formulario con los errores
        $c = IntranetAdmin::comunicado($id);
        if (!$c) {
            intraVolver('comunicados', 'No encontramos ese comunicado.', 'warning');
        }
        $archivos = IntranetArchivos::delComunicado($id);
        $cantImagenes = count(array_filter($archivos, fn($a) => $a['tipo'] === 'IMAGEN'));
        $in = IntranetAdmin::datosDeComunicado($c);
        [$datos, $errores] = IntranetAdmin::validarComunicado($in, $cantImagenes, $archivosOk, 'PUBLICADO');
        if (!$errores) {
            if (IntranetAdmin::cambiarEstado($id, 'PUBLICADO', $usuario)) {
                $desde = IntranetAdmin::fechaInput($datos['vigente_desde']);
                intraVolver('comunicados', $desde > $hoy
                    ? 'Comunicado programado: aparece el ' . Intranet::ddmm($desde) . '.'
                    : 'Comunicado publicado.');
            }
            intraVolver('comunicados', 'No se pudo publicar. Probá de nuevo en unos minutos.', 'danger');
        }
        $errores['_general'] = 'Para publicarlo falta completar algunos datos. Corregilos y tocá «Publicar».';
        $form = ['tipo' => 'comunicado', 'id' => $id, 'datos' => $datos, 'errores' => $errores,
                 'lote' => IntranetArchivos::loteNuevo(), 'archivos' => $archivos, 'estado' => 'BORRADOR',
                 'origen' => !empty($c['plantilla_origen_id']) ? ['id' => (int)$c['plantilla_origen_id'], 'nombre' => (string)$c['plantilla_origen_nombre']] : null];

    } elseif ($accion === 'a_borrador_comunicado' && $puedeCom && $v2 && $id) {
        $ok = IntranetAdmin::comunicado($id) && IntranetAdmin::cambiarEstado($id, 'BORRADOR', $usuario);
        intraVolver('comunicados', $ok ? 'El comunicado volvió a borrador: ya no se muestra.' : 'No se pudo guardar el cambio. Probá de nuevo en unos minutos.',
            $ok ? 'success' : 'danger');

    } elseif ($accion === 'duplicar_comunicado' && $puedeCom && $v2 && $id) {
        $nuevo = IntranetAdmin::duplicarComunicado($id, $usuario);
        if (!$nuevo) {
            intraVolver('comunicados', 'No se pudo duplicar. Probá de nuevo en unos minutos.', 'danger');
        }
        intraVolver('comunicados', 'Copia creada como borrador. Revisala y publicala cuando esté lista.', 'success', ['editar' => $nuevo]);

    } elseif ($accion === 'guardar_fecha' && $puedeFec) {
        [$datos, $errores] = IntranetAdmin::validarFecha($_POST);
        if (!$errores) {
            if (IntranetAdmin::guardarFecha($id, $datos, $usuario)) {
                intraVolver('fechas', $id ? 'Fecha actualizada.' : 'Fecha cargada.');
            }
            $errores['_general'] = 'No se pudo guardar. Probá de nuevo en unos minutos.';
        }
        $form = ['tipo' => 'fecha', 'id' => $id, 'datos' => $datos, 'errores' => $errores];

    } elseif (in_array($accion, ['estado_comunicado', 'estado_fecha'], true) && $id) {
        $tabla = $accion === 'estado_comunicado' ? 'comunicados' : 'fechas';
        if (($tabla === 'comunicados' && !$puedeCom) || ($tabla === 'fechas' && !$puedeFec)) {
            intraVolver($solapa, 'No tenés permiso para esa acción.', 'danger');
        }
        $activar = !empty($_POST['activo']);
        // Una plantilla no se activa ni desactiva desde la solapa Comunicados
        $ok = ($tabla !== 'comunicados' || IntranetAdmin::comunicado($id)) && IntranetAdmin::cambiarActivo($tabla, $id, $activar, $usuario);
        $que = $tabla === 'comunicados' ? 'Comunicado' : 'Fecha';
        $hecho = $tabla === 'comunicados' ? ($activar ? 'reactivado' : 'desactivado') : ($activar ? 'reactivada' : 'desactivada');
        // Al desactivar un comunicado se vuelve con el filtro "Todos" para que siga a la vista (y se pueda reactivar)
        intraVolver($tabla, $ok ? "$que $hecho." : 'No se pudo guardar el cambio. Probá de nuevo en unos minutos.', $ok ? 'success' : 'danger',
            ($tabla === 'comunicados' && !$activar) ? ['estado' => 'todos'] : []);

    } else {
        intraVolver($solapa, 'No tenés permiso para esa acción.', 'danger');
    }
}

// ==================== Formulario a mostrar (GET) ====================
if ($form === null && (isset($_GET['nuevo']) || isset($_GET['editar']))) {
    $idEditar = (int)($_GET['editar'] ?? 0) ?: null;
    $vacio = ['titulo' => '', 'texto' => '', 'texto_inferior' => '', 'esquema' => 'SIN_IMAGEN', 'categoria' => '',
              'estilo' => 'NORMAL', 'link_url' => '', 'link_texto' => '',
              'fijado' => false, 'vigente_desde' => $hoy->format('Y-m-d'), 'vigente_hasta' => '',
              'permite_comentarios' => false, 'permite_reacciones' => false,
              'plantilla_nombre' => '', 'plantilla_descripcion' => ''];
    if ($solapa === 'comunicados') {
        $datos = $vacio;
        $estadoActual = null;
        $origen = null;
        $lote = IntranetArchivos::loteNuevo();
        $archivos = [];
        $elegida = $_GET['plantilla'] ?? null; // null: todavía no eligió; "0": de cero; id: esa plantilla
        if ($idEditar) {
            $c = IntranetAdmin::comunicado($idEditar);
            if (!$c) {
                intraVolver('comunicados', 'No encontramos ese comunicado.', 'warning');
            }
            $datos = IntranetAdmin::datosDeComunicado($c);
            $estadoActual = (string)($c['estado'] ?? 'PUBLICADO');
            $archivos = IntranetArchivos::delComunicado($idEditar);
            if (!empty($c['plantilla_origen_id'])) {
                $origen = ['id' => (int)$c['plantilla_origen_id'], 'nombre' => (string)$c['plantilla_origen_nombre']];
            }
        } elseif ($elegida === null && $conPlantillas) {
            // Paso previo: elegir una plantilla activa o empezar de cero (si no hay, directo al formulario)
            $elegirPlantilla = IntranetAdmin::listarPlantillas(true) ?: null;
        } elseif ((int)$elegida > 0) {
            $p = IntranetAdmin::comunicado((int)$elegida, true);
            if (!$p || empty($p['activo'])) {
                intraVolver('comunicados', 'Esa plantilla ya no está disponible. Elegí otra o empezá de cero.', 'warning', ['nuevo' => 1]);
            }
            // Todo el contenido; las imágenes y los PDF se copian (cambiar la plantilla no afecta al comunicado)
            $datos = IntranetAdmin::datosDesdePlantilla($p);
            $archivos = IntranetArchivos::copiarALote((int)$p['id'], $lote, $usuario);
            $origen = ['id' => (int)$p['id'], 'nombre' => (string)$p['plantilla_nombre']];
        }
        if (!$elegirPlantilla) {
            $form = ['tipo' => 'comunicado', 'id' => $idEditar, 'datos' => $datos, 'errores' => [],
                     'lote' => $lote, 'archivos' => $archivos, 'estado' => $estadoActual, 'origen' => $origen];
        }
    } elseif ($solapa === 'plantillas') {
        $datos = $vacio;
        $lote = IntranetArchivos::loteNuevo();
        $archivos = [];
        $desde = (int)($_GET['desde'] ?? 0);
        if ($idEditar) {
            $p = IntranetAdmin::comunicado($idEditar, true);
            if (!$p) {
                intraVolver('plantillas', 'No encontramos esa plantilla.', 'warning');
            }
            $datos = IntranetAdmin::datosDeComunicado($p);
            $archivos = IntranetArchivos::delComunicado($idEditar);
        } elseif ($desde) {
            // "Guardar como plantilla": copia todo el comunicado; no se crea nada hasta guardar
            $c = IntranetAdmin::comunicado($desde);
            if (!$c) {
                intraVolver('comunicados', 'No encontramos ese comunicado.', 'warning');
            }
            $datos = IntranetAdmin::datosDeComunicado($c);
            $datos['plantilla_nombre'] = mb_substr($datos['titulo'], 0, IntranetAdmin::MAX_PLANTILLA_NOMBRE, 'UTF-8');
            $datos['plantilla_descripcion'] = '';
            $datos['fijado'] = false;
            $datos['aviso_hasta'] = '';
            $archivos = IntranetArchivos::copiarALote($desde, $lote, $usuario);
        }
        $form = ['tipo' => 'plantilla', 'id' => $idEditar, 'datos' => $datos, 'errores' => [],
                 'lote' => $lote, 'archivos' => $archivos, 'estado' => null, 'origen' => null];
    } else {
        $datos = ['fecha_desde' => '', 'fecha_hasta' => '', 'titulo' => '', 'tipo' => '', 'detalle' => ''];
        if ($idEditar) {
            $f = IntranetAdmin::fecha($idEditar);
            if (!$f) {
                intraVolver('fechas', 'No encontramos esa fecha.', 'warning');
            }
            $datos = [
                'fecha_desde' => $f['fecha_desde']->format('Y-m-d'),
                'fecha_hasta' => $f['fecha_hasta'] ? $f['fecha_hasta']->format('Y-m-d') : '',
                'titulo' => $f['titulo'], 'tipo' => $f['tipo'], 'detalle' => (string)$f['detalle'],
            ];
        }
        $form = ['tipo' => 'fecha', 'id' => $idEditar, 'datos' => $datos, 'errores' => []];
    }
}

$flash = $_SESSION['intra_flash'] ?? null;
unset($_SESSION['intra_flash']);

// ==================== Listados ====================
$filtroEstado = $_GET['estado'] ?? 'activos';
$filtrosEstado = $v2
    ? ['activos' => 'Vigentes, programados y borradores', 'VIGENTE' => 'Vigentes', 'PROGRAMADO' => 'Programados',
       'BORRADOR' => 'Borradores', 'VENCIDO' => 'Vencidos', 'INACTIVO' => 'Inactivos', 'todos' => 'Todos']
    : ['activos' => 'Vigentes y programados', 'VIGENTE' => 'Vigentes', 'PROGRAMADO' => 'Programados',
       'VENCIDO' => 'Vencidos', 'INACTIVO' => 'Inactivos', 'todos' => 'Todos'];
if (!isset($filtrosEstado[$filtroEstado])) {
    $filtroEstado = 'activos';
}
$verPasadas = !empty($_GET['pasadas']);

$comunicados = $proximas = $pasadas = [];
$errorListado = false;

if (!$form && $solapa === 'comunicados') {
    $todos = IntranetAdmin::listarComunicados();
    $errorListado = $todos === null;
    $comunicados = array_values(array_filter($todos ?? [], function ($c) use ($filtroEstado) {
        if ($filtroEstado === 'todos') return true;
        if ($filtroEstado === 'activos') return in_array($c['estado'], ['VIGENTE', 'PROGRAMADO', 'BORRADOR'], true);
        return $c['estado'] === $filtroEstado;
    }));
}

$plantillas = [];
if (!$form && $solapa === 'plantillas') {
    $plantillas = IntranetAdmin::listarPlantillas();
    $errorListado = $plantillas === null;
    $plantillas = $plantillas ?? [];
}

if (!$form && $solapa === 'fechas') {
    $fechas = IntranetAdmin::listarFechas();
    $errorListado = $fechas === null;
    $filas = [];
    foreach ($fechas ?? [] as $f) {
        $f['origen'] = 'INTRANET';
        $filas[] = $f;
    }
    foreach (IntranetAdmin::listarFeriados($verPasadas ? $hoy->modify('-1 year') : $hoy) as $f) {
        $f['origen'] = 'FERIADOS';
        $f['tipo'] = 'FERIADO';
        $f['activo'] = 1;
        $f['detalle'] = '';
        $filas[] = $f;
    }
    foreach ($filas as $f) {
        if ($f['fecha_fin'] >= $hoy) {
            $proximas[] = $f;
        } else {
            $pasadas[] = $f;
        }
    }
    usort($proximas, fn($a, $b) => [$a['fecha_desde'], $a['titulo']] <=> [$b['fecha_desde'], $b['titulo']]);
    usort($pasadas, fn($a, $b) => [$b['fecha_desde'], $a['titulo']] <=> [$a['fecha_desde'], $b['titulo']]);
}

$err = $form['errores'] ?? [];
$val = $form['datos'] ?? [];
$inv = fn(string $campo) => isset($err[$campo]) ? ' is-invalid' : '';
$feedback = fn(string $campo) => isset($err[$campo]) ? '<div class="invalid-feedback">' . Intranet::e($err[$campo]) . '</div>' : '';
$estadoClase = ['BORRADOR' => 'text-bg-warning', 'VIGENTE' => 'text-bg-success', 'PROGRAMADO' => 'text-bg-primary',
                'VENCIDO' => 'text-bg-secondary', 'INACTIVO' => 'text-bg-light border'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cargar novedades · Intranet XL</title>
    <link rel="shortcut icon" href="/sistemas/assets/css/icono.jpg" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="/recursosHumanos/intranet/assets/intranet.css?v=<?= @filemtime(__DIR__ . '/assets/intranet.css') ?>" rel="stylesheet">
    <style>
        .intra-page {
            width: 100%;
            max-width: 1240px;
            margin: 0 auto;
            padding: 1.25rem 1.25rem 2.5rem;
        }

        .intra-volver {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            min-height: 44px;
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--text-muted);
            text-decoration: none;
        }

        .intra-volver:hover {
            color: var(--primary);
        }

        .intra-titulo {
            font-size: 1.5rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            color: var(--text-title);
            margin: 0;
        }

        .intra-solapas {
            display: flex;
            gap: 0.5rem;
            border-bottom: 1px solid var(--border-subtle);
            margin: 1rem 0 1.25rem;
        }

        .intra-solapa {
            min-height: 44px;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 0 1rem;
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--text-muted);
            text-decoration: none;
            border-bottom: 3px solid transparent;
            margin-bottom: -1px;
        }

        .intra-solapa.activa {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }

        .intra-tabla {
            font-size: 0.85rem;
            margin: 0;
        }

        .intra-tabla th {
            font-size: 0.7rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            white-space: nowrap;
        }

        .intra-tabla td {
            vertical-align: middle;
        }

        .intra-tabla .intra-btn {
            padding: 0 0.8rem;
            font-size: 0.8rem;
        }

        .fila-inactiva td {
            color: #94a3b8;
        }

        .form-label {
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--text-title);
        }

        .form-control, .form-select {
            min-height: 44px;
            border-radius: 10px;
            font-size: 0.9rem;
        }

        .form-check-input {
            width: 1.3em;
            height: 1.3em;
        }

        .intra-contador {
            font-size: 0.72rem;
            color: var(--text-muted);
            text-align: right;
        }

        .intra-contador.excedido {
            color: #b91c1c;
            font-weight: 700;
        }

        /* Formulario de comunicado a lo ancho, por secciones */
        .intra-form-seccion {
            padding: 1.1rem 0;
            border-top: 1px solid var(--border-subtle);
        }

        .intra-form-seccion:first-of-type {
            border-top: 0;
            padding-top: 0.5rem;
        }

        .intra-form-seccion-titulo {
            font-size: 0.95rem;
            font-weight: 800;
            color: var(--text-title);
            margin: 0 0 0.6rem;
            padding: 0;
        }

        .intra-form-botones {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-subtle);
        }

        .intra-acciones-fila {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 0.35rem;
        }

        /* Vista previa: mismo fondo y anchos que la pantalla real */
        .intra-previa-cuerpo {
            background: var(--bg-page);
        }

        .intra-previa-intranet {
            max-width: 820px;
            margin: 0 auto;
        }

        .intra-previa-hub {
            width: 340px;
            max-width: 100%;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid var(--border-subtle);
            border-radius: 14px;
            padding: 1.1rem;
        }

        @media (max-width: 767.98px) {
            .intra-page {
                padding: 1rem 0.85rem 2rem;
            }
        }
    </style>
</head>
<body>

    <?php $topbarEtiqueta = 'Intranet'; require XL_HUB_DIR . '/partials/topbar.php'; ?>

    <main class="intra-page">
        <a href="<?= $e(Intranet::URL_INTRANET) ?>" class="intra-volver"><i class="fa-solid fa-arrow-left"></i> Volver a la Intranet</a>
        <h1 class="intra-titulo">Cargar novedades</h1>

        <nav class="intra-solapas" aria-label="Solapas">
            <?php foreach ($solapas as $cod => $nom): ?>
                <a href="?solapa=<?= $e($cod) ?>" class="intra-solapa<?= $cod === $solapa ? ' activa' : '' ?>">
                    <i class="fa-solid <?= $iconosSolapa[$cod] ?>"></i> <?= $e($nom) ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php if ($flash): ?>
            <div class="alert alert-<?= $e($flash['tipo']) ?> alert-dismissible fade show" role="alert">
                <?= $e($flash['mensaje']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($err['_general'])): ?>
            <div class="alert alert-danger"><?= $e($err['_general']) ?></div>
        <?php endif; ?>

        <?php if ($elegirPlantilla): ?>
        <!-- ==================== Nuevo comunicado: elegir plantilla ==================== -->
        <div class="intra-card">
            <h2 class="h5 fw-bold mb-1">Nuevo comunicado</h2>
            <p class="text-muted small mb-3">Empezá de cero o elegí una plantilla: el formulario se abre con su contenido y lo podés cambiar todo antes de guardar.</p>
            <div class="intra-plantillas-elegir">
                <a class="intra-plantilla-opcion es-cero" href="?solapa=comunicados&amp;nuevo=1&amp;plantilla=0">
                    <span class="intra-plantilla-icono"><i class="fa-regular fa-file"></i></span>
                    <span class="intra-plantilla-nombre">Empezar de cero</span>
                    <span class="intra-plantilla-desc">Formulario en blanco.</span>
                </a>
                <?php foreach ($elegirPlantilla as $p):
                    $estiloP = Intranet::estiloValido($p['estilo'] ?? 'NORMAL');
                    $icoP = Intranet::ESTILOS[$estiloP][1] ?: 'fa-file-lines'; ?>
                <a class="intra-plantilla-opcion" href="?solapa=comunicados&amp;nuevo=1&amp;plantilla=<?= (int)$p['id'] ?>">
                    <span class="intra-plantilla-icono<?= $estiloP !== 'NORMAL' ? ' intra-tag-estilo-' . strtolower($estiloP) : '' ?>" title="Estilo <?= $e(Intranet::ESTILOS[$estiloP][0]) ?>"><i class="fa-solid <?= $e($icoP) ?>"></i></span>
                    <span class="intra-plantilla-nombre"><?= $e($p['plantilla_nombre']) ?></span>
                    <?php if (($p['plantilla_descripcion'] ?? '') !== ''): ?>
                        <span class="intra-plantilla-desc"><?= $e($p['plantilla_descripcion']) ?></span>
                    <?php endif; ?>
                    <span class="intra-tag intra-tag-cat"><?= $e(Intranet::categoria($p['categoria'])) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
            <div class="mt-3"><a href="?solapa=comunicados" class="intra-btn">Cancelar</a></div>
        </div>

        <?php elseif ($form && in_array($form['tipo'], ['comunicado', 'plantilla'], true)): ?>
        <!-- ==================== Formulario de comunicado (o de plantilla: el mismo) ==================== -->
        <?php
        $esPla = $form['tipo'] === 'plantilla';
        $esquemaVal = Intranet::esquemaValido($val['esquema'] ?? 'SIN_IMAGEN');
        $maxTexto = IntranetAdmin::maxTexto();
        $hayImagenes = (bool)array_filter($form['archivos'] ?? [], fn($a) => $a['tipo'] === 'IMAGEN');
        $estadoForm = $form['estado'] ?? null; // null = nuevo
        $conBorrador = !$esPla && $v2 && $estadoForm !== 'PUBLICADO';
        $origenForm = $form['origen'] ?? null;
        // Dibujo esquemático de cada esquema (64 x 44): imagen en índigo, texto en gris
        $img = fn($x, $y, $w, $h) => "<rect x='$x' y='$y' width='$w' height='$h' rx='3' fill='#a5b4fc'/>";
        $lin = fn($x, $y, $w) => "<rect x='$x' y='$y' width='$w' height='3' rx='1.5' fill='#cbd5e1'/>";
        $dibujos = [
            'SIN_IMAGEN' => $lin(4, 6, 40) . $lin(4, 14, 56) . $lin(4, 21, 56) . $lin(4, 28, 56) . $lin(4, 35, 34),
            'ARRIBA'     => $img(4, 3, 56, 20) . $lin(4, 28, 40) . $lin(4, 34, 56) . $lin(4, 40, 30),
            'IZQUIERDA'  => $img(3, 6, 24, 32) . $lin(32, 9, 28) . $lin(32, 16, 28) . $lin(32, 23, 28) . $lin(32, 30, 18),
            'DERECHA'    => $img(37, 6, 24, 32) . $lin(4, 9, 28) . $lin(4, 16, 28) . $lin(4, 23, 28) . $lin(4, 30, 18),
            'CENTRO'     => $lin(4, 3, 40) . $lin(4, 9, 56) . $img(12, 15, 40, 16) . $lin(4, 35, 56) . $lin(4, 41, 30),
            'ABAJO'      => $lin(4, 3, 40) . $lin(4, 9, 56) . $lin(4, 15, 46) . $img(4, 21, 56, 21),
            'FLYER'      => $img(10, 2, 44, 34) . $lin(18, 40, 28),
        ];
        $barraFormato = function (string $para) {
            $botones = [
                'negrita' => ['fa-bold', 'Negrita'], 'cursiva' => ['fa-italic', 'Cursiva'],
                'vinetas' => ['fa-list-ul', 'Lista con viñetas'], 'numerada' => ['fa-list-ol', 'Lista numerada'],
                'link' => ['fa-link', 'Link'],
            ];
            $html = '<div class="intra-formato" role="toolbar" aria-label="Formato del texto" data-formato-para="' . $para . '">';
            foreach ($botones as $cod => [$ico, $nom]) {
                $html .= '<button type="button" data-formato="' . $cod . '" title="' . $nom . '" aria-label="' . $nom . '"><i class="fa-solid ' . $ico . '"></i></button>';
            }
            return $html . '</div>';
        };
        $etiquetaTexto = ['CENTRO' => 'Texto antes de la imagen', 'FLYER' => 'Texto (opcional, hasta ' . IntranetAdmin::MAX_FLYER . ' caracteres)'];
        $jsonArchivos = function (string $tipo) use ($form) {
            $lista = array_values(array_filter($form['archivos'] ?? [], fn($a) => $a['tipo'] === $tipo));
            return json_encode(array_map([IntranetArchivos::class, 'paraCliente'], $lista), JSON_UNESCAPED_UNICODE);
        };
        // Botones de guardado: abajo del formulario y en la vista previa (con form="formComunicado")
        $botonesGuardar = function () use ($conBorrador, $form, $v2, $esPla) {
            if ($esPla) { ?>
                <button type="submit" form="formComunicado" name="modo" value="plantilla" class="intra-btn intra-btn-primario"><i class="fa-solid fa-check"></i> Guardar plantilla</button>
            <?php } elseif ($conBorrador) { ?>
                <button type="submit" form="formComunicado" name="modo" value="borrador" class="intra-btn"><i class="fa-regular fa-floppy-disk"></i> Guardar borrador</button>
                <button type="submit" form="formComunicado" name="modo" value="publicar" class="intra-btn intra-btn-primario"><i class="fa-solid fa-paper-plane"></i> Publicar</button>
            <?php } else { ?>
                <button type="submit" form="formComunicado" name="modo" value="publicar" class="intra-btn intra-btn-primario"><i class="fa-solid fa-check"></i> <?= $form['id'] ? 'Guardar cambios' : 'Publicar' ?></button>
            <?php }
        };
        ?>
        <form method="post" class="intra-card intra-form-com" id="formComunicado" novalidate data-previa="<?= $e(Intranet::URL_PREVIA) ?>"<?= $esPla ? '' : ' data-revisar-datos' ?>>
            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
            <input type="hidden" name="solapa" value="<?= $esPla ? 'plantillas' : 'comunicados' ?>">
            <input type="hidden" name="accion" value="<?= $esPla ? 'guardar_plantilla' : 'guardar_comunicado' ?>">
            <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">
            <input type="hidden" name="lote" value="<?= $e($form['lote']) ?>">
            <?php if ($esPla): ?>
                <input type="hidden" name="es_plantilla" value="1">
            <?php elseif ($origenForm && !$form['id']): ?>
                <input type="hidden" name="plantilla_origen_id" value="<?= (int)$origenForm['id'] ?>">
            <?php endif; ?>

            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                <h2 class="h5 fw-bold m-0"><?= $esPla ? ($form['id'] ? 'Editar plantilla' : 'Nueva plantilla') : ($form['id'] ? 'Editar comunicado' : 'Nuevo comunicado') ?></h2>
                <?php if ($estadoForm === 'BORRADOR'): ?><span class="badge rounded-pill text-bg-warning">Borrador: no se muestra</span><?php endif; ?>
                <?php if ($esPla): ?><span class="badge rounded-pill text-bg-light border">Plantilla: nunca se publica</span><?php endif; ?>
            </div>
            <?php if ($origenForm): ?>
                <div class="intra-basado"><i class="fa-solid fa-layer-group"></i> Basado en la plantilla: <strong><?= $e($origenForm['nombre']) ?></strong></div>
            <?php endif; ?>

            <?php if ($esPla): ?>
            <!-- 0. Datos de la plantilla -->
            <section class="intra-form-seccion">
                <div class="row g-3">
                    <div class="col-12 col-md-5">
                        <label class="form-label" for="f_pla_nombre">Nombre de la plantilla</label>
                        <input type="text" class="form-control<?= $inv('plantilla_nombre') ?>" id="f_pla_nombre" name="plantilla_nombre" maxlength="<?= IntranetAdmin::MAX_PLANTILLA_NOMBRE ?>" value="<?= $e($val['plantilla_nombre'] ?? '') ?>" placeholder="Por ejemplo: Apertura de local" required data-contador>
                        <?= $feedback('plantilla_nombre') ?>
                    </div>
                    <div class="col-12 col-md-7">
                        <label class="form-label" for="f_pla_desc">Cuándo usarla <span class="fw-normal text-muted">(descripción corta)</span></label>
                        <input type="text" class="form-control<?= $inv('plantilla_descripcion') ?>" id="f_pla_desc" name="plantilla_descripcion" maxlength="<?= IntranetAdmin::MAX_PLANTILLA_DESCRIPCION ?>" value="<?= $e($val['plantilla_descripcion'] ?? '') ?>" placeholder="Por ejemplo: Para anunciar la inauguración de un local nuevo." data-contador>
                        <?= $feedback('plantilla_descripcion') ?>
                    </div>
                </div>
                <div class="form-text">Marcá entre corchetes los datos a completar, por ejemplo <code>[Nombre del local]</code>. Al publicar, se avisa si quedó alguno.</div>
            </section>
            <?php endif; ?>

            <!-- 1. Título, categoría y estilo -->
            <section class="intra-form-seccion">
                <div class="row g-3">
                    <div class="col-12 col-md-8">
                        <label class="form-label" for="f_titulo">Título</label>
                        <input type="text" class="form-control<?= $inv('titulo') ?>" id="f_titulo" name="titulo" maxlength="<?= IntranetAdmin::MAX['titulo'] ?>" value="<?= $e($val['titulo']) ?>" required data-contador>
                        <?= $feedback('titulo') ?>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="f_categoria">Categoría</label>
                        <select class="form-select<?= $inv('categoria') ?>" id="f_categoria" name="categoria" required>
                            <option value="">Elegí una categoría</option>
                            <?php foreach (IntranetAdmin::CATEGORIAS_CARGA as $cod): ?>
                                <option value="<?= $e($cod) ?>"<?= $val['categoria'] === $cod ? ' selected' : '' ?>><?= $e(Intranet::categoria($cod)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= $feedback('categoria') ?>
                    </div>
                </div>

                <?php if (Intranet::tieneEstilo()): ?>
                <fieldset class="mt-3">
                    <legend class="form-label mb-2">Estilo <span class="fw-normal text-muted">(colores de la tarjeta)</span></legend>
                    <div class="intra-estilos">
                        <?php foreach (Intranet::ESTILOS as $cod => [$nomEstilo, $icoEstilo]): ?>
                        <label class="intra-estilo-opcion">
                            <input type="radio" name="estilo" value="<?= $e($cod) ?>"<?= ($val['estilo'] ?? 'NORMAL') === $cod ? ' checked' : '' ?>>
                            <span class="<?= $cod !== 'NORMAL' ? 'com-estilo-' . strtolower($cod) : '' ?>">
                                <?php if ($icoEstilo !== ''): ?><i class="fa-solid <?= $icoEstilo ?>"></i><?php else: ?><i class="fa-regular fa-square"></i><?php endif; ?>
                                <?= $e($nomEstilo) ?>
                            </span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <?= isset($err['estilo']) ? '<div class="text-danger small mt-1">' . $e($err['estilo']) . '</div>' : '' ?>
                </fieldset>
                <?php endif; ?>
            </section>

            <!-- 2. Esquema -->
            <?php if ($v2 && $archivosOk): ?>
            <section class="intra-form-seccion">
                <fieldset>
                    <legend class="intra-form-seccion-titulo">Esquema <span class="fw-normal text-muted">(dónde van las imágenes)</span></legend>
                    <div class="intra-esquemas">
                        <?php foreach (Intranet::ESQUEMAS as $cod => $nomEsquema): ?>
                        <label class="intra-esquema-opcion">
                            <input type="radio" name="esquema" value="<?= $e($cod) ?>"<?= $esquemaVal === $cod ? ' checked' : '' ?>>
                            <span>
                                <svg viewBox="0 0 64 44" aria-hidden="true"><?= $dibujos[$cod] ?></svg>
                                <?= $e($nomEsquema) ?>
                            </span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="form-text" id="notaEsquema" hidden>Sin imágenes, el esquema pasó a «Solo texto». Si subís una imagen, vuelve al que tenías.</div>
                    <?= isset($err['esquema']) ? '<div class="text-danger small mt-1 fw-bold">' . $e($err['esquema']) . '</div>' : '' ?>
                </fieldset>
            </section>
            <?php elseif ($v2): ?>
                <!-- Sin carpeta de archivos no se elige esquema: se conserva el que tenga -->
                <input type="hidden" name="esquema" value="<?= $e($esquemaVal) ?>">
            <?php endif; ?>

            <!-- 3. Texto o textos según el esquema -->
            <section class="intra-form-seccion">
                <div>
                    <label class="form-label" for="f_texto" id="lbl_texto"><?= $e($etiquetaTexto[$esquemaVal] ?? 'Texto') ?></label>
                    <?= $v2 ? $barraFormato('f_texto') : '' ?>
                    <textarea class="form-control<?= $inv('texto') ?>" id="f_texto" name="texto" rows="7" maxlength="<?= $maxTexto ?>" required data-contador><?= $e($val['texto']) ?></textarea>
                    <?= $feedback('texto') ?>
                    <div class="text-danger small fw-bold mt-1" id="avisoFlyer" hidden>Con «Solo imagen» el texto puede tener hasta <?= IntranetAdmin::MAX_FLYER ?> caracteres: acortalo.</div>
                </div>
                <?php if ($v2): ?>
                <div class="mt-3" id="bloqueTextoInferior"<?= $esquemaVal !== 'CENTRO' ? ' hidden' : '' ?>>
                    <label class="form-label" for="f_texto_inferior">Texto después de la imagen</label>
                    <?= $barraFormato('f_texto_inferior') ?>
                    <textarea class="form-control<?= $inv('texto_inferior') ?>" id="f_texto_inferior" name="texto_inferior" rows="5" maxlength="<?= $maxTexto ?>" data-contador><?= $e($val['texto_inferior'] ?? '') ?></textarea>
                    <?= $feedback('texto_inferior') ?>
                </div>
                <?php endif; ?>
            </section>

            <?php if ($archivosOk): ?>
            <?php if (!empty($err['archivos'])): ?>
                <div class="alert alert-danger py-2 small"><?= $e($err['archivos']) ?></div>
            <?php endif; ?>

            <!-- 4. Imágenes (solo si el esquema las usa) -->
            <section class="intra-form-seccion intra-archivos" id="zonaImagenes" data-archivos data-tipo="IMAGEN" data-max="<?= IntranetArchivos::MAX_IMAGENES ?>"
                     data-peso-max="<?= IntranetArchivos::PESO_MAX_IMAGEN ?>" data-url="<?= $e(Intranet::URL_SUBIR) ?>"
                     data-items="<?= $e($jsonArchivos('IMAGEN')) ?>"<?= ($v2 && $esquemaVal === 'SIN_IMAGEN' && !$hayImagenes) ? ' hidden' : '' ?>>
                <h3 class="intra-form-seccion-titulo">Imágenes</h3>
                <div class="form-text mt-0 mb-2">JPG, PNG o WEBP de hasta 5 MB, hasta <?= IntranetArchivos::MAX_IMAGENES ?>. Se achican y se comprimen solas. La de la estrella es la portada: es la primera que se ve y la que aparece en el hub. Arrastralas para cambiar el orden.</div>
                <div class="intra-soltar" data-soltar>
                    <i class="fa-regular fa-images" aria-hidden="true"></i>
                    <span class="intra-soltar-texto">Arrastrá las imágenes acá o</span>
                    <button type="button" class="intra-btn intra-archivos-elegir"><i class="fa-solid fa-upload"></i> Elegir imágenes</button>
                    <span class="intra-archivos-tope" hidden>Máximo <?= IntranetArchivos::MAX_IMAGENES ?> imágenes</span>
                </div>
                <input type="file" class="d-none" accept="image/jpeg,image/png,image/webp" multiple>
                <ul class="intra-archivos-errores"></ul>
                <ul class="intra-archivos-lista intra-grilla"></ul>
            </section>

            <!-- 5. Adjuntos PDF -->
            <section class="intra-form-seccion intra-archivos" data-archivos data-tipo="PDF" data-max="<?= IntranetArchivos::MAX_PDF ?>"
                     data-peso-max="<?= IntranetArchivos::PESO_MAX_PDF ?>" data-url="<?= $e(Intranet::URL_SUBIR) ?>"
                     data-items="<?= $e($jsonArchivos('PDF')) ?>">
                <h3 class="intra-form-seccion-titulo">Adjuntos PDF</h3>
                <div class="form-text mt-0 mb-2">Hasta <?= IntranetArchivos::MAX_PDF ?> PDF de hasta 10 MB. El nombre es el que va a ver la gente.</div>
                <div class="intra-soltar" data-soltar>
                    <i class="fa-regular fa-file-pdf" aria-hidden="true"></i>
                    <span class="intra-soltar-texto">Arrastrá los PDF acá o</span>
                    <button type="button" class="intra-btn intra-archivos-elegir"><i class="fa-solid fa-upload"></i> Elegir PDF</button>
                    <span class="intra-archivos-tope" hidden>Máximo <?= IntranetArchivos::MAX_PDF ?> PDF</span>
                </div>
                <input type="file" class="d-none" accept="application/pdf,.pdf" multiple>
                <ul class="intra-archivos-errores"></ul>
                <ul class="intra-archivos-lista"></ul>
            </section>
            <?php elseif ($puedeCom || $puedePla): ?>
                <div class="alert alert-light border small py-2 mb-3"><i class="fa-solid fa-circle-info me-1"></i> <?= $e(IntranetArchivos::AVISO_SIN_CARPETA) ?>.</div>
            <?php endif; ?>

            <!-- 6. Link, vigencia y opciones -->
            <section class="intra-form-seccion">
                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-7">
                        <label class="form-label" for="f_link_url">Link <span class="fw-normal text-muted">(opcional)</span></label>
                        <input type="text" class="form-control<?= $inv('link_url') ?>" id="f_link_url" name="link_url" maxlength="<?= IntranetAdmin::MAX['link_url'] ?>" placeholder="https://… o /ruta/interna" value="<?= $e($val['link_url']) ?>">
                        <?= $feedback('link_url') ?>
                    </div>
                    <div class="col-12 col-md-5">
                        <label class="form-label" for="f_link_texto">Texto del botón</label>
                        <input type="text" class="form-control<?= $inv('link_texto') ?>" id="f_link_texto" name="link_texto" maxlength="<?= IntranetAdmin::MAX['link_texto'] ?>" placeholder="Ver más información" value="<?= $e($val['link_texto']) ?>">
                        <?= $feedback('link_texto') ?>
                    </div>
                </div>

                <?php if (!$esPla): // una plantilla no usa vigencia ni se fija: eso se define al usarla ?>
                <div class="row g-3 mb-3">
                    <div class="col-12 col-sm-6 col-md-4">
                        <label class="form-label" for="f_desde">Vigente desde</label>
                        <input type="date" class="form-control<?= $inv('vigente_desde') ?>" id="f_desde" name="vigente_desde" value="<?= $e($val['vigente_desde']) ?>" required>
                        <?= $feedback('vigente_desde') ?>
                        <div class="form-text">Si es una fecha futura, queda programado y aparece solo ese día.</div>
                    </div>
                    <div class="col-12 col-sm-6 col-md-4">
                        <label class="form-label" for="f_hasta">Vigente hasta <span class="fw-normal text-muted">(opcional)</span></label>
                        <input type="date" class="form-control<?= $inv('vigente_hasta') ?>" id="f_hasta" name="vigente_hasta" value="<?= $e($val['vigente_hasta']) ?>">
                        <?= $feedback('vigente_hasta') ?>
                        <div class="form-text">Vacío: no vence.</div>
                    </div>
                </div>

                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="f_fijado" name="fijado" value="1"<?= $val['fijado'] ? ' checked' : '' ?>>
                    <label class="form-check-label ms-1" for="f_fijado">Fijar arriba de todo</label>
                </div>
                <?php endif; ?>

                <?php if ($v2): $conAviso = !empty($val['aviso_emergente']); ?>
                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" id="f_aviso" name="aviso_emergente" value="1"<?= $conAviso ? ' checked' : '' ?> aria-describedby="ayudaAviso">
                    <label class="form-check-label ms-1" for="f_aviso"><i class="fa-solid fa-bell text-warning"></i> Mostrar como aviso al entrar al hub</label>
                    <div class="form-text" id="ayudaAviso">Para comunicados importantes. Aparece en una ventana la primera vez del día que se entra al hub.</div>
                </div>
                <?php if (!$esPla): ?>
                <div class="row g-3 mt-0" id="bloqueAvisoHasta"<?= $conAviso ? '' : ' hidden' ?>>
                    <div class="col-12 col-sm-6 col-md-4">
                        <label class="form-label" for="f_aviso_hasta">Mostrar el aviso hasta <span class="fw-normal text-muted">(opcional)</span></label>
                        <input type="date" class="form-control<?= $inv('aviso_hasta') ?>" id="f_aviso_hasta" name="aviso_hasta" value="<?= $e($val['aviso_hasta'] ?? '') ?>">
                        <?= $feedback('aviso_hasta') ?>
                        <div class="form-text">Vacío: mientras el comunicado esté vigente.</div>
                    </div>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </section>

            <?php if ($conInteraccion): ?>
            <!-- 7. Participación: comentarios y reacciones -->
            <section class="intra-form-seccion">
                <fieldset>
                    <legend class="intra-form-seccion-titulo">Participación</legend>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="f_comentarios" name="permite_comentarios" value="1"<?= !empty($val['permite_comentarios']) ? ' checked' : '' ?>>
                        <label class="form-check-label ms-1" for="f_comentarios"><i class="fa-regular fa-comment text-secondary"></i> Permitir comentarios</label>
                    </div>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="f_reacciones" name="permite_reacciones" value="1"<?= !empty($val['permite_reacciones']) ? ' checked' : '' ?>>
                        <label class="form-check-label ms-1" for="f_reacciones"><i class="fa-regular fa-thumbs-up text-secondary"></i> Permitir reacciones</label>
                    </div>
                    <div class="form-text">Se puede comentar y reaccionar mientras esté publicado y vigente. Si desmarcás una opción, lo que ya haya se oculta, pero no se borra.</div>
                </fieldset>
            </section>
            <?php endif; ?>

            <div class="intra-form-botones">
                <button type="button" class="intra-btn" id="btnVistaPrevia" data-bs-toggle="modal" data-bs-target="#modalPrevia"><i class="fa-regular fa-eye"></i> Vista previa</button>
                <?php $botonesGuardar(); ?>
                <a href="?solapa=<?= $esPla ? 'plantillas' : 'comunicados' ?>" class="intra-btn">Cancelar</a>
            </div>
        </form>

        <!-- Vista previa: se arma en el servidor con las mismas plantillas que la pantalla real, sin guardar -->
        <div class="modal fade" id="modalPrevia" tabindex="-1" aria-labelledby="modalPreviaTitulo" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content" style="border-radius: 14px;">
                    <div class="modal-header">
                        <div>
                            <h2 class="modal-title h6 fw-bold" id="modalPreviaTitulo">Vista previa</h2>
                            <div class="small text-muted">Así se va a ver. Todavía no se guardó nada.</div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body intra-previa-cuerpo">
                        <ul class="nav nav-tabs mb-3" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="tabPreviaIntranet" data-bs-toggle="tab" data-bs-target="#previaIntranet" type="button" role="tab" aria-controls="previaIntranet" aria-selected="true">Intranet</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#previaHub" type="button" role="tab" aria-controls="previaHub" aria-selected="false">Columna del hub</button>
                            </li>
                            <li class="nav-item" role="presentation" id="tabPreviaAvisoItem" hidden>
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#previaAviso" type="button" role="tab" aria-controls="previaAviso" aria-selected="false"><i class="fa-solid fa-bell text-warning"></i> Aviso al entrar</button>
                            </li>
                        </ul>
                        <div class="alert alert-warning small py-2" id="previaFaltan" hidden></div>
                        <div class="text-center text-muted py-5" id="previaCargando"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Armando la vista previa…</div>
                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="previaIntranet" role="tabpanel" aria-labelledby="tabPreviaIntranet">
                                <div class="intra-previa-intranet" id="previaIntranetCuerpo"></div>
                            </div>
                            <div class="tab-pane fade" id="previaHub" role="tabpanel">
                                <div class="intra-previa-hub">
                                    <h3 class="intra-bloque-titulo"><i class="fa-solid fa-bullhorn"></i> Comunicados</h3>
                                    <div id="previaHubCuerpo"></div>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="previaAviso" role="tabpanel">
                                <div class="intra-previa-aviso">
                                    <div class="modal-content intra-aviso-modal" id="previaAvisoCuerpo"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="intra-btn" data-bs-dismiss="modal">Cerrar y seguir editando</button>
                        <?php $botonesGuardar(); ?>
                    </div>
                </div>
            </div>
        </div>


        <?php elseif ($form && $form['tipo'] === 'fecha'): ?>
        <!-- ==================== Formulario de fecha ==================== -->
        <form method="post" class="intra-card" style="max-width: 720px;" novalidate>
            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
            <input type="hidden" name="solapa" value="fechas">
            <input type="hidden" name="accion" value="guardar_fecha">
            <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">
            <h2 class="h6 fw-bold mb-3"><?= $form['id'] ? 'Editar fecha' : 'Nueva fecha' ?></h2>

            <div class="row g-3 mb-3">
                <div class="col-12 col-sm-6">
                    <label class="form-label" for="f_fdesde">Desde</label>
                    <input type="date" class="form-control<?= $inv('fecha_desde') ?>" id="f_fdesde" name="fecha_desde" value="<?= $e($val['fecha_desde']) ?>" required>
                    <?= $feedback('fecha_desde') ?>
                </div>
                <div class="col-12 col-sm-6">
                    <label class="form-label" for="f_fhasta">Hasta <span class="fw-normal text-muted">(opcional)</span></label>
                    <input type="date" class="form-control<?= $inv('fecha_hasta') ?>" id="f_fhasta" name="fecha_hasta" value="<?= $e($val['fecha_hasta']) ?>">
                    <?= $feedback('fecha_hasta') ?>
                    <div class="form-text">Solo para eventos de varios días.</div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="f_ftitulo">Título</label>
                <input type="text" class="form-control<?= $inv('titulo') ?>" id="f_ftitulo" name="titulo" maxlength="<?= IntranetAdmin::MAX['titulo'] ?>" value="<?= $e($val['titulo']) ?>" required data-contador>
                <?= $feedback('titulo') ?>
            </div>

            <div class="mb-3">
                <label class="form-label" for="f_tipo">Tipo</label>
                <select class="form-select<?= $inv('tipo') ?>" id="f_tipo" name="tipo" required>
                    <option value="">Elegí el tipo</option>
                    <?php foreach (IntranetAdmin::TIPOS_CARGA as $cod): ?>
                        <option value="<?= $e($cod) ?>"<?= $val['tipo'] === $cod ? ' selected' : '' ?>><?= $e(Intranet::tipoFecha($cod)) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= $feedback('tipo') ?>
            </div>

            <div class="mb-4">
                <label class="form-label" for="f_detalle">Detalle <span class="fw-normal text-muted">(opcional)</span></label>
                <textarea class="form-control<?= $inv('detalle') ?>" id="f_detalle" name="detalle" rows="3" maxlength="<?= IntranetAdmin::MAX['detalle'] ?>" data-contador><?= $e($val['detalle']) ?></textarea>
                <?= $feedback('detalle') ?>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <button type="submit" class="intra-btn intra-btn-primario"><i class="fa-solid fa-check"></i> <?= $form['id'] ? 'Guardar cambios' : 'Guardar' ?></button>
                <a href="?solapa=fechas" class="intra-btn">Cancelar</a>
            </div>
        </form>

        <?php elseif ($solapa === 'comunicados'): ?>
        <!-- ==================== Listado de comunicados ==================== -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <form method="get" class="d-flex align-items-center gap-2">
                <input type="hidden" name="solapa" value="comunicados">
                <label for="filtroEstado" class="form-label m-0 text-nowrap">Mostrar</label>
                <select id="filtroEstado" name="estado" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($filtrosEstado as $cod => $nom): ?>
                        <option value="<?= $e($cod) ?>"<?= $cod === $filtroEstado ? ' selected' : '' ?>><?= $e($nom) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <a href="?solapa=comunicados&amp;nuevo=1" class="intra-btn intra-btn-primario"><i class="fa-solid fa-plus"></i> Nuevo comunicado</a>
        </div>

        <?php if ($errorListado): ?>
            <div class="alert alert-warning">No pudimos leer los comunicados. Probá de nuevo en unos minutos.</div>
        <?php elseif (!$comunicados): ?>
            <div class="intra-card text-center text-muted py-4">No hay comunicados para mostrar con este filtro.</div>
        <?php else: ?>
            <div class="intra-card p-0">
                <div class="table-responsive">
                    <table class="table intra-tabla">
                        <thead>
                            <tr><th class="ps-3">Título</th><th>Categoría</th><th>Vigencia</th><th>Estado</th><?php if ($conInteraccion): ?><th>Participación</th><?php endif; ?><th>Cargó</th><th class="text-end pe-3">Acciones</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($comunicados as $c): ?>
                            <tr class="<?= $c['estado'] === 'INACTIVO' ? 'fila-inactiva' : '' ?>">
                                <td class="ps-3">
                                    <div class="fw-bold text-body"><?= $e($c['titulo']) ?></div>
                                    <?= Intranet::etiquetaEstilo($c) ?>
                                    <?php if ($c['fijado']): ?><span class="intra-tag intra-tag-fijado mt-1"><i class="fa-solid fa-thumbtack"></i> Fijado</span><?php endif; ?>
                                    <?php if (!empty($c['aviso_emergente'])): ?>
                                        <span class="intra-tag intra-tag-aviso mt-1" title="Se muestra como aviso al entrar al hub<?= $c['aviso_hasta'] ? ' hasta el ' . $e(Intranet::ddmm($c['aviso_hasta'])) : '' ?>">
                                            <i class="fa-solid fa-bell"></i> Aviso<?= $c['aviso_hasta'] ? ' hasta ' . $e(Intranet::ddmm($c['aviso_hasta'])) : '' ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="intra-tag <?= ($c['categoria'] ?? '') !== '' ? 'intra-tag-cat' : '' ?>"><?= $e(Intranet::categoria($c['categoria'])) ?></span></td>
                                <td class="text-nowrap">
                                    <?= $e(Intranet::ddmm($c['vigente_desde'])) ?>
                                    <?= $c['vigente_hasta'] ? ' al ' . $e(Intranet::ddmm($c['vigente_hasta'])) : ' · no vence' ?>
                                </td>
                                <td><span class="badge rounded-pill <?= $estadoClase[$c['estado']] ?>"><?= $e(IntranetAdmin::ESTADOS[$c['estado']]) ?></span></td>
                                <?php if ($conInteraccion):
                                    // Siempre los contadores (aunque estén deshabilitados: se ocultan, no se borran)
                                    $ri = $c['interaccion'] ?? null;
                                    $nReac = (int)($ri['total_reacciones'] ?? 0);
                                    $nCom = (int)($ri['comentarios'] ?? 0);
                                    $nOcu = (int)($ri['ocultos'] ?? 0);
                                    $habil = array_filter([!empty($c['permite_reacciones']) ? 'reacciones' : '', !empty($c['permite_comentarios']) ? 'comentarios' : '']);
                                ?>
                                <td>
                                    <div class="intra-participacion" title="<?= $e($habil ? 'Habilitado: ' . implode(' y ', $habil) : 'Comentarios y reacciones deshabilitados') ?>">
                                        <?php if ($nReac > 0): ?>
                                            <button type="button" class="intra-participacion-ver<?= empty($c['permite_reacciones']) ? ' text-muted' : '' ?>" data-bs-toggle="modal" data-bs-target="#modalQuienes"
                                                    data-id="<?= (int)$c['id'] ?>" data-titulo="<?= $e($c['titulo']) ?>" title="Ver quiénes reaccionaron">
                                                <i class="fa-regular fa-thumbs-up"></i> <?= $nReac ?> <?= $nReac === 1 ? 'reacción' : 'reacciones' ?>
                                            </button>
                                        <?php else: ?>
                                            <span class="<?= empty($c['permite_reacciones']) ? 'text-muted' : '' ?>"><i class="fa-regular fa-thumbs-up"></i> 0 reacciones</span>
                                        <?php endif; ?>
                                        <span class="<?= empty($c['permite_comentarios']) ? 'text-muted' : '' ?>"><i class="fa-regular fa-comment"></i> <?= $nCom ?> <?= $nCom === 1 ? 'comentario' : 'comentarios' ?><?php if ($nOcu > 0): ?> <span class="text-muted">(<?= $nOcu ?> <?= $nOcu === 1 ? 'oculto' : 'ocultos' ?>)</span><?php endif; ?></span>
                                    </div>
                                </td>
                                <?php endif; ?>
                                <td>
                                    <?= $e($c['creado_por_nombre'] ?: $c['creado_por']) ?>
                                    <?php if ($c['modificado_por']): ?>
                                        <div class="text-muted small">Modificó: <?= $e($c['modificado_por_nombre'] ?: $c['modificado_por']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="intra-acciones-fila">
                                    <a href="?solapa=comunicados&amp;editar=<?= (int)$c['id'] ?>" class="intra-btn" title="Editar"><i class="fa-solid fa-pen"></i> Editar</a>
                                    <?php if ($v2 && $c['estado'] === 'BORRADOR'):
                                        // Datos de plantilla sin completar: se avisa antes de publicar (ventana "Quedaron datos sin completar")
                                        $pend = IntranetAdmin::datosPendientes($c); ?>
                                        <form method="post" class="d-inline"<?= $pend ? ' data-pendientes="' . $e(json_encode($pend, JSON_UNESCAPED_UNICODE)) . '"' : '' ?>>
                                            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                                            <input type="hidden" name="solapa" value="comunicados">
                                            <input type="hidden" name="accion" value="publicar_comunicado">
                                            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                            <button type="submit" class="intra-btn intra-btn-primario"><i class="fa-solid fa-paper-plane"></i> Publicar</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($v2): ?>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                                            <input type="hidden" name="solapa" value="comunicados">
                                            <input type="hidden" name="accion" value="duplicar_comunicado">
                                            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                            <button type="submit" class="intra-btn" title="Crear una copia como borrador"><i class="fa-regular fa-copy"></i> Duplicar</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($puedePla): ?>
                                        <a href="?solapa=plantillas&amp;nuevo=1&amp;desde=<?= (int)$c['id'] ?>" class="intra-btn" title="Crear una plantilla nueva con este contenido (el comunicado no cambia)"><i class="fa-solid fa-layer-group"></i> Guardar como plantilla</a>
                                    <?php endif; ?>
                                    <?php if ($v2 && !in_array($c['estado'], ['BORRADOR', 'INACTIVO'], true)): ?>
                                        <button type="button" class="intra-btn" data-bs-toggle="modal" data-bs-target="#modalBorrador"
                                                data-id="<?= (int)$c['id'] ?>" data-titulo="<?= $e($c['titulo']) ?>">
                                            <i class="fa-solid fa-rotate-left"></i> Volver a borrador
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($c['estado'] === 'INACTIVO'): ?>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                                            <input type="hidden" name="solapa" value="comunicados">
                                            <input type="hidden" name="accion" value="estado_comunicado">
                                            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                            <input type="hidden" name="activo" value="1">
                                            <button type="submit" class="intra-btn"><i class="fa-solid fa-rotate-left"></i> Reactivar</button>
                                        </form>
                                    <?php else: ?>
                                        <button type="button" class="intra-btn text-danger" data-bs-toggle="modal" data-bs-target="#modalDesactivar"
                                                data-accion="estado_comunicado" data-id="<?= (int)$c['id'] ?>" data-titulo="<?= $e($c['titulo']) ?>">
                                            <i class="fa-solid fa-ban"></i> Desactivar
                                        </button>
                                    <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php elseif ($solapa === 'plantillas'): ?>
        <!-- ==================== Listado de plantillas ==================== -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <p class="text-muted small m-0">Se ofrecen en este orden al tocar «Nuevo comunicado». Nunca se publican.</p>
            <a href="?solapa=plantillas&amp;nuevo=1" class="intra-btn intra-btn-primario"><i class="fa-solid fa-plus"></i> Nueva plantilla</a>
        </div>

        <?php if ($errorListado): ?>
            <div class="alert alert-warning">No pudimos leer las plantillas. Probá de nuevo en unos minutos.</div>
        <?php elseif (!$plantillas): ?>
            <div class="intra-card text-center text-muted py-4">Todavía no hay plantillas.</div>
        <?php else: ?>
            <div class="intra-card p-0">
                <div class="table-responsive">
                    <table class="table intra-tabla">
                        <thead>
                            <tr><th class="ps-3">Orden</th><th>Plantilla</th><th>Categoría</th><th>Estilo</th><th>Estado</th><th class="text-end pe-3">Acciones</th></tr>
                        </thead>
                        <tbody>
                            <?php $ultima = count($plantillas) - 1; foreach ($plantillas as $i => $p): $activa = !empty($p['activo']); ?>
                            <tr class="<?= $activa ? '' : 'fila-inactiva' ?>">
                                <td class="ps-3 text-nowrap">
                                    <?php foreach ([-1 => ['fa-arrow-up', 'Subir'], 1 => ['fa-arrow-down', 'Bajar']] as $paso => [$icoPaso, $nomPaso]): ?>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                                        <input type="hidden" name="solapa" value="plantillas">
                                        <input type="hidden" name="accion" value="mover_plantilla">
                                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                        <input type="hidden" name="paso" value="<?= $paso ?>">
                                        <button type="submit" class="intra-btn-mini" title="<?= $nomPaso ?>" aria-label="<?= $nomPaso ?> «<?= $e($p['plantilla_nombre']) ?>»"
                                            <?= ($paso < 0 && $i === 0) || ($paso > 0 && $i === $ultima) ? ' disabled' : '' ?>><i class="fa-solid <?= $icoPaso ?>"></i></button>
                                    </form>
                                    <?php endforeach; ?>
                                </td>
                                <td>
                                    <div class="fw-bold text-body"><?= $e($p['plantilla_nombre']) ?></div>
                                    <?php if (($p['plantilla_descripcion'] ?? '') !== ''): ?><div class="text-muted small"><?= $e($p['plantilla_descripcion']) ?></div><?php endif; ?>
                                </td>
                                <td><span class="intra-tag <?= ($p['categoria'] ?? '') !== '' ? 'intra-tag-cat' : '' ?>"><?= $e(Intranet::categoria($p['categoria'])) ?></span></td>
                                <td class="text-nowrap"><?= Intranet::etiquetaEstilo($p) ?: '<span class="small">Clásico</span>' ?></td>
                                <td><?= $activa ? '<span class="badge rounded-pill text-bg-success">Activa</span>' : '<span class="badge rounded-pill text-bg-light border">Inactiva</span>' ?></td>
                                <td class="text-end pe-3">
                                    <div class="intra-acciones-fila">
                                        <a href="?solapa=plantillas&amp;editar=<?= (int)$p['id'] ?>" class="intra-btn"><i class="fa-solid fa-pen"></i> Editar</a>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                                            <input type="hidden" name="solapa" value="plantillas">
                                            <input type="hidden" name="accion" value="duplicar_plantilla">
                                            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                            <button type="submit" class="intra-btn" title="Crear una copia inactiva"><i class="fa-regular fa-copy"></i> Duplicar</button>
                                        </form>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                                            <input type="hidden" name="solapa" value="plantillas">
                                            <input type="hidden" name="accion" value="estado_plantilla">
                                            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                            <input type="hidden" name="activo" value="<?= $activa ? '0' : '1' ?>">
                                            <?php if ($activa): ?>
                                                <button type="submit" class="intra-btn text-danger" title="Deja de ofrecerse al crear un comunicado"><i class="fa-solid fa-ban"></i> Desactivar</button>
                                            <?php else: ?>
                                                <button type="submit" class="intra-btn"><i class="fa-solid fa-rotate-left"></i> Reactivar</button>
                                            <?php endif; ?>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php else: ?>
        <!-- ==================== Listado de fechas ==================== -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <a href="?solapa=fechas<?= $verPasadas ? '' : '&amp;pasadas=1' ?>" class="intra-btn">
                <i class="fa-solid <?= $verPasadas ? 'fa-eye-slash' : 'fa-clock-rotate-left' ?>"></i> <?= $verPasadas ? 'Ocultar las pasadas' : 'Ver también las pasadas' ?>
            </a>
            <a href="?solapa=fechas&amp;nuevo=1" class="intra-btn intra-btn-primario"><i class="fa-solid fa-plus"></i> Nueva fecha</a>
        </div>

        <?php if ($errorListado): ?>
            <div class="alert alert-warning">No pudimos leer las fechas. Probá de nuevo en unos minutos.</div>
        <?php endif; ?>

        <?php foreach (['Próximas' => $proximas, 'Pasadas' => $verPasadas ? $pasadas : null] as $tituloGrupo => $grupo): ?>
            <?php if ($grupo === null) continue; ?>
            <h2 class="h6 fw-bold mt-3 mb-2"><?= $e($tituloGrupo) ?></h2>
            <?php if (!$grupo): ?>
                <div class="intra-card text-center text-muted py-3 mb-3">No hay fechas para mostrar.</div>
            <?php else: ?>
            <div class="intra-card p-0 mb-3">
                <div class="table-responsive">
                    <table class="table intra-tabla">
                        <thead>
                            <tr><th class="ps-3">Fecha</th><th>Título</th><th>Tipo</th><th>Estado</th><th class="text-end pe-3">Acciones</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($grupo as $f): $esFeriado = $f['origen'] === 'FERIADOS'; $activa = !empty($f['activo']); ?>
                            <tr class="<?= $activa ? '' : 'fila-inactiva' ?>">
                                <td class="ps-3 text-nowrap fw-bold"><?= $e(Intranet::rango($f['fecha_desde'], $f['fecha_fin'])) ?></td>
                                <td>
                                    <?= $e($f['titulo']) ?>
                                    <?php if ($f['detalle'] !== '' && $f['detalle'] !== null): ?><div class="text-muted small"><?= $e($f['detalle']) ?></div><?php endif; ?>
                                </td>
                                <td><span class="intra-tag <?= $tagTipo[$f['tipo']] ?? '' ?>"><?= $e(Intranet::tipoFecha($f['tipo'])) ?></span></td>
                                <td><?= $esFeriado ? '—' : ($activa ? '<span class="badge rounded-pill text-bg-success">Activa</span>' : '<span class="badge rounded-pill text-bg-light border">Inactiva</span>') ?></td>
                                <td class="text-end pe-3 text-nowrap">
                                    <?php if ($esFeriado): ?>
                                        <span class="text-muted small">Feriado (se carga por fuera de esta pantalla)</span>
                                    <?php else: ?>
                                        <a href="?solapa=fechas&amp;editar=<?= (int)$f['id'] ?>" class="intra-btn" title="Editar"><i class="fa-solid fa-pen"></i> Editar</a>
                                        <?php if (!$activa): ?>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                                                <input type="hidden" name="solapa" value="fechas">
                                                <input type="hidden" name="accion" value="estado_fecha">
                                                <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                                                <input type="hidden" name="activo" value="1">
                                                <button type="submit" class="intra-btn"><i class="fa-solid fa-rotate-left"></i> Reactivar</button>
                                            </form>
                                        <?php else: ?>
                                            <button type="button" class="intra-btn text-danger" data-bs-toggle="modal" data-bs-target="#modalDesactivar"
                                                    data-accion="estado_fecha" data-id="<?= (int)$f['id'] ?>" data-titulo="<?= $e($f['titulo']) ?>">
                                                <i class="fa-solid fa-ban"></i> Desactivar
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php endif; ?>
    </main>

    <!-- Confirmación de baja -->
    <div class="modal fade" id="modalDesactivar" tabindex="-1" aria-labelledby="modalDesactivarTitulo" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="post" class="modal-content" style="border-radius: 14px;">
                <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                <input type="hidden" name="solapa" value="<?= $e($solapa) ?>">
                <input type="hidden" name="accion" id="mdAccion" value="">
                <input type="hidden" name="id" id="mdId" value="">
                <input type="hidden" name="activo" value="0">
                <div class="modal-header">
                    <h2 class="modal-title h6 fw-bold" id="modalDesactivarTitulo">¿Desactivar?</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-1">Vas a desactivar <strong id="mdTitulo"></strong>.</p>
                    <p class="text-muted small mb-0">Deja de verse en el hub y en la intranet. Lo podés reactivar cuando quieras.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="intra-btn" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="intra-btn intra-btn-primario" style="background:#b91c1c;border-color:#b91c1c;">Desactivar</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($v2 && $puedeCom): ?>
    <!-- Confirmación de volver a borrador -->
    <div class="modal fade" id="modalBorrador" tabindex="-1" aria-labelledby="modalBorradorTitulo" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="post" class="modal-content" style="border-radius: 14px;">
                <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                <input type="hidden" name="solapa" value="comunicados">
                <input type="hidden" name="accion" value="a_borrador_comunicado">
                <input type="hidden" name="id" id="mbId" value="">
                <div class="modal-header">
                    <h2 class="modal-title h6 fw-bold" id="modalBorradorTitulo">¿Volver a borrador?</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-1"><strong id="mbTitulo"></strong> pasa a borrador.</p>
                    <p class="text-muted small mb-0">Deja de verse en el hub y en la intranet hasta que lo vuelvas a publicar.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="intra-btn" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="intra-btn intra-btn-primario">Volver a borrador</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($conInteraccion && !$form && $solapa === 'comunicados'): ?>
    <!-- Quiénes reaccionaron (desde el contador del listado) -->
    <div class="modal fade" id="modalQuienes" tabindex="-1" aria-labelledby="modalQuienesTitulo" aria-hidden="true"
         data-url="<?= $e(Intranet::URL_INTERACCION) ?>" data-csrf="<?= $e($csrf) ?>">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content" style="border-radius: 14px;">
                <div class="modal-header">
                    <div>
                        <h2 class="modal-title h6 fw-bold" id="modalQuienesTitulo">Quiénes reaccionaron</h2>
                        <div class="small text-muted" id="mqTitulo"></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body com-quienes" id="mqCuerpo" aria-live="polite"></div>
                <div class="modal-footer">
                    <button type="button" class="intra-btn" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($solapa === 'comunicados' && $puedeCom): ?>
    <!-- Datos de plantilla sin completar: aparece al publicar (formulario o listado) si quedó algún [...] -->
    <div class="modal fade" id="modalPendientes" tabindex="-1" aria-labelledby="modalPendientesTitulo" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 14px;">
                <div class="modal-header">
                    <h2 class="modal-title h6 fw-bold" id="modalPendientesTitulo"><i class="fa-solid fa-triangle-exclamation text-warning"></i> Quedaron datos sin completar</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-1">Quedaron datos sin completar: <strong id="mpLista"></strong></p>
                    <p class="text-muted small mb-0">Son los textos entre corchetes del título o del texto. Si los publicás así, se van a ver tal cual.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="intra-btn" data-bs-dismiss="modal" id="mpVolver">Volver a editar</button>
                    <button type="button" class="intra-btn intra-btn-primario" id="mpPublicar"><i class="fa-solid fa-paper-plane"></i> Publicar igual</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <footer class="footer-bar">
        <div class="container">
            &copy; <?= date('Y') ?> XL Extra Large · Todos los derechos reservados.
        </div>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <?php if ($form && in_array($form['tipo'], ['comunicado', 'plantilla'], true)): ?>
    <script src="/recursosHumanos/intranet/assets/intranet_comunicados.js?v=<?= @filemtime(__DIR__ . '/assets/intranet_comunicados.js') ?>"></script>
    <script src="/recursosHumanos/intranet/assets/intranet_carga.js?v=<?= @filemtime(__DIR__ . '/assets/intranet_carga.js') ?>"></script>
    <?php endif; ?>
    <?php if ($conInteraccion && !$form && $solapa === 'comunicados'): ?>
    <script src="/recursosHumanos/intranet/assets/intranet_interaccion.js?v=<?= @filemtime(__DIR__ . '/assets/intranet_interaccion.js') ?>"></script>
    <script>
    (function () {
        // Quiénes reaccionaron: la lista se pide al abrir la ventana (siempre actualizada)
        const modal = document.getElementById('modalQuienes');
        const cuerpo = document.getElementById('mqCuerpo');
        let pedido = 0;
        modal.addEventListener('show.bs.modal', function (ev) {
            const b = ev.relatedTarget;
            const este = ++pedido;
            document.getElementById('mqTitulo').textContent = '«' + b.dataset.titulo + '»';
            cuerpo.textContent = 'Cargando…';
            IntraInteraccion.pedirQuienes(modal.dataset.url, modal.dataset.csrf, b.dataset.id).then(function (r) {
                if (este !== pedido) return;
                if (r.ok) cuerpo.innerHTML = r.html;
                else IntraInteraccion.mostrarError(cuerpo, r.error || 'No pudimos cargar la lista. Probá de nuevo.');
            });
        });
        modal.addEventListener('hidden.bs.modal', function () { pedido++; cuerpo.innerHTML = ''; });
    })();
    </script>
    <?php endif; ?>
    <script>
    (function () {
        // Confirmación de baja: completa el formulario del modal con lo elegido
        const modal = document.getElementById('modalDesactivar');
        modal.addEventListener('show.bs.modal', function (ev) {
            const b = ev.relatedTarget;
            document.getElementById('mdAccion').value = b.dataset.accion;
            document.getElementById('mdId').value = b.dataset.id;
            document.getElementById('mdTitulo').textContent = '«' + b.dataset.titulo + '»';
        });

        // Confirmación de volver a borrador
        const modalBorrador = document.getElementById('modalBorrador');
        if (modalBorrador) {
            modalBorrador.addEventListener('show.bs.modal', function (ev) {
                const b = ev.relatedTarget;
                document.getElementById('mbId').value = b.dataset.id;
                document.getElementById('mbTitulo').textContent = '«' + b.dataset.titulo + '»';
            });
        }

        // Contadores de caracteres
        // El tope puede cambiar según el esquema (data-max; por ejemplo, 300 en «Solo imagen»)
        document.querySelectorAll('[data-contador]').forEach(function (campo) {
            const tope = function () { return parseInt(campo.dataset.max || campo.getAttribute('maxlength'), 10); };
            const cont = document.createElement('div');
            cont.className = 'intra-contador';
            const fb = campo.parentNode.querySelector('.invalid-feedback');
            campo.parentNode.insertBefore(cont, fb ? fb.nextSibling : campo.nextSibling);
            function actualizar() {
                const n = campo.value.replace(/\r\n/g, '\n').length;
                const max = tope();
                cont.textContent = n + ' / ' + max;
                cont.classList.toggle('excedido', n > max);
            }
            campo.addEventListener('input', actualizar);
            actualizar();
        });

        // Datos sin completar ([Nombre del local]...): al publicar, avisar con "Volver a editar" o
        // "Publicar igual". Formulario: se revisan título y textos al tocar «Publicar» (no al
        // guardar borrador). Listado: el servidor deja la lista en data-pendientes.
        // Mismo patrón que IntranetAdmin::datosPendientes (los links [texto](url) no cuentan).
        const modalPend = document.getElementById('modalPendientes');
        if (modalPend && window.bootstrap) {
            const PATRON = /\[([^\[\]\n<>]{1,80})\](?!\()/g;
            let envio = null; // {form, boton} que espera la respuesta

            function pendientesDe(form) {
                if (form.dataset.pendientes) {
                    try { return JSON.parse(form.dataset.pendientes); } catch (e) { return []; }
                }
                const lista = [];
                ['titulo', 'texto', 'texto_inferior'].forEach(function (nombre) {
                    const campo = form.querySelector('[name="' + nombre + '"]');
                    if (!campo || campo.closest('[hidden]')) return; // el texto de abajo solo cuenta en «Imagen al centro»
                    (campo.value.match(PATRON) || []).forEach(function (d) {
                        if (lista.indexOf(d) === -1) lista.push(d);
                    });
                });
                return lista;
            }

            document.addEventListener('submit', function (ev) {
                const form = ev.target;
                if (!form.matches || !form.matches('[data-pendientes], [data-revisar-datos]')) return;
                if (form._publicarIgual) {
                    form._publicarIgual = false;
                    return;
                }
                const boton = ev.submitter || null;
                if (form.hasAttribute('data-revisar-datos') && (!boton || boton.value !== 'publicar')) return;
                const lista = pendientesDe(form);
                if (!lista.length) return;
                ev.preventDefault();
                ev.stopImmediatePropagation();
                envio = { form: form, boton: boton };
                document.getElementById('mpLista').textContent = lista.join(', ');
                // Una ventana a la vez: si la vista previa está abierta, se cierra primero
                const previa = document.getElementById('modalPrevia');
                const abierta = previa && bootstrap.Modal.getInstance(previa);
                if (abierta && previa.classList.contains('show')) {
                    previa.addEventListener('hidden.bs.modal', function () {
                        bootstrap.Modal.getOrCreateInstance(modalPend).show();
                    }, { once: true });
                    abierta.hide();
                } else {
                    bootstrap.Modal.getOrCreateInstance(modalPend).show();
                }
            }, true);

            document.getElementById('mpPublicar').addEventListener('click', function () {
                if (!envio) return;
                const e = envio;
                envio = null;
                bootstrap.Modal.getOrCreateInstance(modalPend).hide();
                e.form._publicarIgual = true;
                if (e.form.requestSubmit) {
                    e.form.requestSubmit(e.boton || undefined);
                } else {
                    e.form.submit();
                }
            });
            modalPend.addEventListener('hidden.bs.modal', function () {
                if (envio && envio.form.id === 'formComunicado') {
                    const titulo = envio.form.querySelector('[name="titulo"]');
                    if (titulo) titulo.focus(); // "Volver a editar"
                }
                envio = null;
            });
        }
    })();
    </script>
</body>
</html>
