<?php
/**
 * Intranet XL - pantalla principal
 * Acceso: cualquier usuario logueado en el hub.
 * Comunicados (cargados + versiones nuevas de DocuGest), agenda, cumpleaños y Equipo XL.
 * Si una fuente falla, solo se oculta su bloque.
 */
require_once __DIR__ . '/Class/bootstrap.php';
require_once __DIR__ . '/Class/Intranet.php';

Auth::exigir();

$e = [Intranet::class, 'e'];
$hoy = Intranet::hoy();
$mesNombre = Intranet::mes((int)$hoy->format('n'));
$tagTipo = ['FERIADO' => 'intra-tag-feriado', 'COMERCIAL' => 'intra-tag-comercial', 'OPERATIVA' => 'intra-tag-operativa'];

$puedeComunicados = Intranet::puedePublicarComunicados();
$puedeFechas = Intranet::puedePublicarFechas();

$personalOk = false;
$cumpleHoy = $cumpleMes = $bienvenidas = $aniversarios = $comunicados = $agenda = [];
$proximoCumple = $feriado = $comercial = $saludo = $saludoAniv = null;
$anivHoy = [];

try {
    $personalOk   = Intranet::personal() !== null;
    $cumpleHoy    = Intranet::cumpleHoy();
    $proximoCumple = $cumpleHoy ? null : Intranet::proximoCumple();
    $saludo       = Intranet::cumpleUsuario();
    $saludoAniv   = Intranet::aniversarioUsuario();
    $anivHoy      = Intranet::aniversariosHoy();
    $cumpleMes    = Intranet::cumpleMes();
    $bienvenidas  = Intranet::bienvenidas();
    $aniversarios = Intranet::aniversarios();
    $feriado      = Intranet::proximoDeTipo('FERIADO');
    $comercial    = Intranet::proximoDeTipo('COMERCIAL');
    $comunicados  = Intranet::comunicadosVigentes();
    $agenda       = Intranet::agenda($hoy->modify('first day of this month'), $hoy->modify('last day of next month'));
} catch (Throwable $ex) {
    error_log('intranet/index.php: ' . $ex->getMessage());
}

// Comentarios de los comunicados que los tienen habilitados (los ocultos, solo para responsables de carga)
$yo = (string)Auth::username();
$comentarios = [];
$idsConComentarios = array_column(array_filter($comunicados, fn($c) => !empty($c['permite_comentarios']) && empty($c['automatico'])), 'id');
if ($idsConComentarios) {
    require_once __DIR__ . '/Class/IntranetInteraccion.php';
    $comentarios = IntranetInteraccion::comentarios($idsConComentarios, $puedeComunicados);
}
$hayInteraccion = (bool)array_filter($comunicados, [Intranet::class, 'conInteraccionHabilitada']);

// Filtros: solo categorías con comunicados vigentes, en el orden de la especificación
$porCategoria = array_count_values(array_column($comunicados, 'categoria'));
$filtros = array_filter(Intranet::CATEGORIAS, fn($cod) => !empty($porCategoria[$cod]), ARRAY_FILTER_USE_KEY);

// Agenda agrupada por mes (un evento que empezó el mes anterior va en el mes actual)
$inicioMes = $hoy->modify('first day of this month');
$agendaPorMes = [];
foreach ($agenda as $f) {
    $ref = $f['desde'] < $inicioMes ? $inicioMes : $f['desde'];
    $agendaPorMes[$ref->format('Y-m')][] = $f;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Intranet XL · XL Gestión</title>
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

        .min-w-0 {
            min-width: 0;
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
            font-size: 1.6rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            color: var(--text-title);
            margin: 0;
        }

        .intra-bajada {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin: 0.15rem 0 0;
        }

        /* Tarjetas de "lo de hoy" */
        .intra-kpi {
            height: 100%;
            display: flex;
            gap: 12px;
            align-items: flex-start;
        }

        .intra-kpi-icono {
            width: 42px;
            min-width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.05rem;
        }

        .intra-kpi-label {
            font-size: 0.7rem;
            font-weight: 800;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            color: var(--text-muted);
        }

        .intra-kpi-valor {
            font-size: 0.98rem;
            font-weight: 800;
            color: var(--text-title);
            line-height: 1.3;
        }

        .intra-kpi-sub {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .intra-kpi.es-destacada {
            background: linear-gradient(135deg, var(--primary-dark) 0%, #4338ca 100%);
            border-color: transparent;
            color: #ffffff;
        }

        .intra-kpi.es-destacada .intra-kpi-label,
        .intra-kpi.es-destacada .intra-kpi-sub {
            color: rgba(255, 255, 255, 0.75);
        }

        .intra-kpi.es-destacada .intra-kpi-valor {
            color: #ffffff;
        }

        .intra-kpi.es-destacada .intra-kpi-icono {
            background: rgba(255, 255, 255, 0.14);
        }

        .intra-seccion-titulo {
            font-size: 0.8rem;
            font-weight: 800;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 7px;
            margin: 0 0 0.75rem;
        }

        .intra-seccion-titulo i {
            color: var(--primary);
        }

        /* Filtros */
        .intra-filtros {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .intra-filtro {
            min-height: 44px;
            padding: 0 1rem;
            border-radius: 22px;
            border: 1px solid var(--border-subtle);
            background: #ffffff;
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--text-body);
            cursor: pointer;
        }

        .intra-filtro:hover {
            border-color: #818cf8;
        }

        .intra-filtro.activo {
            background: var(--primary);
            border-color: var(--primary);
            color: #ffffff;
        }

        /* Comunicado: la tarjeta (y "Leer más") está en intranet.css, compartida con el aviso y la vista previa */
        .com-card {
            margin-bottom: 0.85rem;
            scroll-margin-top: 80px;
        }

        .com-card.es-elegido {
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.35);
        }

        /* Agenda */
        .agenda-mes {
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--primary);
            margin: 0.9rem 0 0.5rem;
        }

        .agenda-mes:first-of-type {
            margin-top: 0;
        }

        .es-pasado {
            opacity: 0.5;
        }

        /* Cumpleaños en dos columnas */
        .cumple-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.5rem;
        }

        .cumple-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px;
            border-radius: 10px;
            min-width: 0;
        }

        .cumple-item.es-hoy {
            background: var(--primary-light);
            box-shadow: inset 0 0 0 1px #c7d2fe;
        }

        .cumple-item.es-hoy .intra-avatar {
            background: var(--primary);
            color: #ffffff;
        }

        .cumple-item.es-pasado .intra-avatar {
            background: #f1f5f9;
            color: #94a3b8;
        }

        /* Bienvenidas destacadas */
        .intra-bienvenida {
            background: linear-gradient(135deg, #ecfdf5 0%, #eef2ff 100%);
            border: 1px solid #a7f3d0;
            border-radius: 14px;
            padding: 1.1rem 1.2rem;
            box-shadow: 0 6px 18px -10px rgba(5, 150, 105, 0.35);
        }

        .intra-bienvenida-icono {
            width: 48px;
            min-width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, #059669 0%, #4f46e5 100%);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .intra-bienvenida-titulo {
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--text-title);
            margin: 0 0 0.15rem;
        }

        .intra-bienvenida-bajada {
            font-size: 0.92rem;
            color: var(--text-body);
            margin: 0;
        }

        .intra-bienvenida-persona {
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid #d1fae5;
            border-radius: 12px;
            padding: 0.75rem 0.9rem;
            min-height: 72px;
            min-width: 0;
        }

        .intra-bienvenida-persona .intra-avatar {
            width: 44px;
            min-width: 44px;
            height: 44px;
            background: #d1fae5;
            color: #047857;
            font-size: 0.85rem;
        }

        /* Manos que aplauden al cargar */
        .intra-bienvenida-icono i {
            display: inline-block;
            animation: intra-aplauso 0.32s ease-in-out 0.7s 6 alternate;
        }

        @keyframes intra-aplauso {
            from { transform: scale(1) rotate(0); }
            to   { transform: scale(1.18) rotate(-8deg); }
        }

        @media (prefers-reduced-motion: reduce) {
            .intra-bienvenida-icono i {
                animation: none;
            }
        }

        /* Carrusel de bienvenidas (más de un ingreso en el mes) */
        .intra-carrusel-nav {
            display: flex;
            align-items: center;
            gap: 4px;
            flex-shrink: 0;
        }

        .intra-carrusel-btn {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            border: 1px solid #a7f3d0;
            background: #ffffff;
            color: #047857;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .intra-carrusel-btn:hover {
            background: #059669;
            border-color: #059669;
            color: #ffffff;
        }

        .intra-carrusel-contador {
            font-size: 0.78rem;
            font-weight: 700;
            color: #047857;
            min-width: 44px;
            text-align: center;
            white-space: nowrap;
        }

        /* Aniversarios redondos (5, 10, 15... años) */
        .cumple-item.es-hito {
            background: #fffbeb;
            box-shadow: inset 0 0 0 1px #fde68a;
        }

        .cumple-item.es-hito .intra-avatar {
            background: #fef3c7;
            color: #b45309;
        }

        .text-hito {
            color: #92400e;
        }

        @media (max-width: 400px) {
            .cumple-grid {
                grid-template-columns: minmax(0, 1fr);
            }
        }

        @media (max-width: 767.98px) {
            .intra-page {
                padding: 1rem 0.85rem 2rem;
            }

            .intra-titulo {
                font-size: 1.35rem;
            }
        }
    </style>
</head>
<body>

    <?php $topbarEtiqueta = 'Intranet'; require XL_HUB_DIR . '/partials/topbar.php'; ?>

    <main class="intra-page">

        <!-- Encabezado -->
        <a href="/sistemas/hub/index.php" class="intra-volver"><i class="fa-solid fa-arrow-left"></i> Volver al hub</a>
        <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-3">
            <div>
                <h1 class="intra-titulo">Intranet XL</h1>
                <p class="intra-bajada">Comunicados, fechas importantes y la gente que hace XL</p>
            </div>
            <?php if ($puedeComunicados && $puedeFechas): ?>
                <div class="dropdown">
                    <button class="intra-btn intra-btn-primario dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fa-solid fa-plus"></i> Cargar novedad
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item py-2" href="<?= $e(Intranet::URL_ADMIN) ?>?solapa=comunicados&amp;nuevo=1"><i class="fa-solid fa-bullhorn me-2 text-secondary"></i>Comunicado</a></li>
                        <li><a class="dropdown-item py-2" href="<?= $e(Intranet::URL_ADMIN) ?>?solapa=fechas&amp;nuevo=1"><i class="fa-solid fa-calendar-plus me-2 text-secondary"></i>Fecha</a></li>
                    </ul>
                </div>
            <?php elseif ($puedeComunicados || $puedeFechas): ?>
                <a class="intra-btn intra-btn-primario" href="<?= $e(Intranet::URL_ADMIN) ?>?solapa=<?= $puedeComunicados ? 'comunicados' : 'fechas' ?>&amp;nuevo=1">
                    <i class="fa-solid fa-plus"></i> Cargar novedad
                </a>
            <?php elseif (Intranet::puedeAdministrarPlantillas()): ?>
                <a class="intra-btn" href="<?= $e(Intranet::URL_ADMIN) ?>?solapa=plantillas">
                    <i class="fa-solid fa-layer-group"></i> Plantillas
                </a>
            <?php endif; ?>
        </div>

        <?php require __DIR__ . '/partials/saludo_cumple.php'; ?>

        <!-- Lo de hoy -->
        <div class="row g-3 mb-4">
            <?php if ($personalOk && ($cumpleHoy || $proximoCumple)): ?>
            <div class="col-12 col-md intra-aparece" style="--i: 0">
                <div class="intra-card intra-kpi es-destacada intra-elevable">
                    <div class="intra-kpi-icono"><i class="fa-solid fa-cake-candles"></i></div>
                    <div class="min-w-0">
                        <?php if ($cumpleHoy): ?>
                            <div class="intra-kpi-label"><?= count($cumpleHoy) === 1 ? 'Hoy cumple' : 'Hoy cumplen' ?></div>
                            <?php foreach ($cumpleHoy as $p): ?>
                                <div class="intra-kpi-valor"><?= $e($p['nombre']) ?></div>
                                <?php if ($p['sector'] !== ''): ?><div class="intra-kpi-sub"><?= $e($p['sector']) ?></div><?php endif; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="intra-kpi-label">Próximo cumpleaños</div>
                            <div class="intra-kpi-valor"><?= $e(implode(', ', array_column($proximoCumple['personas'], 'nombre'))) ?></div>
                            <div class="intra-kpi-sub"><?= $e(Intranet::diaYFecha($proximoCumple['fecha'])) ?> · <?= $e(Intranet::enDias($proximoCumple['fecha'])) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($anivHoy): ?>
            <div class="col-12 col-md intra-aparece" style="--i: 1">
                <div class="intra-card intra-kpi intra-elevable">
                    <div class="intra-kpi-icono" style="background:#fef3c7;color:#b45309;"><i class="fa-solid fa-champagne-glasses"></i></div>
                    <div class="min-w-0">
                        <div class="intra-kpi-label"><?= count($anivHoy) === 1 ? 'Aniversario de hoy' : 'Aniversarios de hoy' ?></div>
                        <?php foreach ($anivHoy as $a): ?>
                            <div class="intra-kpi-valor"><?= $e($a['nombre']) ?></div>
                            <div class="intra-kpi-sub">
                                <?php if ($a['es_hito']): ?>
                                    <span class="intra-tag intra-tag-hito"><i class="fa-solid fa-medal"></i> <?= Intranet::anios($a['anios']) ?> en XL</span>
                                <?php else: ?>
                                    <?= Intranet::anios($a['anios']) ?> en XL
                                <?php endif; ?>
                                <?= $a['sector'] !== '' ? ' · ' . $e($a['sector']) : '' ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($feriado): ?>
            <div class="col-12 col-md intra-aparece" style="--i: 2">
                <div class="intra-card intra-kpi intra-elevable">
                    <div class="intra-kpi-icono" style="background:#fef2f2;color:#b91c1c;"><i class="fa-solid fa-umbrella-beach"></i></div>
                    <div class="min-w-0">
                        <div class="intra-kpi-label"><?= Intranet::diasHasta($feriado['desde']) <= 0 ? 'Hoy es feriado' : 'Próximo feriado' ?></div>
                        <div class="intra-kpi-valor"><?= $e($feriado['titulo']) ?></div>
                        <div class="intra-kpi-sub"><?= $e(Intranet::diaYFecha($feriado['desde'])) ?> · <?= $e(Intranet::enDias($feriado['desde'])) ?></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($comercial): ?>
            <div class="col-12 col-md intra-aparece" style="--i: 3">
                <div class="intra-card intra-kpi intra-elevable">
                    <div class="intra-kpi-icono" style="background:#fff7ed;color:#9a3412;"><i class="fa-solid fa-tags"></i></div>
                    <div class="min-w-0">
                        <div class="intra-kpi-label">Próxima fecha comercial</div>
                        <div class="intra-kpi-valor"><?= $e($comercial['titulo']) ?></div>
                        <div class="intra-kpi-sub"><?= $e(Intranet::rango($comercial['desde'], $comercial['hasta'])) ?> · <?= $e(Intranet::enDias($comercial['desde'])) ?></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($bienvenidas): ?>
        <!-- Bienvenidas: ingresos del mes, destacados a todo el ancho -->
        <?php $variasBienvenidas = count($bienvenidas) > 1; ?>
        <section class="intra-bienvenida mb-4 intra-aparece" id="bienvenidas" aria-label="Bienvenidas" style="scroll-margin-top: 80px; --i: 3;">
            <div class="d-flex align-items-center gap-3 mb-2">
                <div class="intra-bienvenida-icono"><i class="fa-solid fa-hands-clapping"></i></div>
                <h2 class="intra-bienvenida-titulo flex-grow-1">¡Bienvenida al equipo!</h2>
                <?php if ($variasBienvenidas): ?>
                <div class="intra-carrusel-nav">
                    <button type="button" class="intra-carrusel-btn" data-bs-target="#carruselBienvenidas" data-bs-slide="prev" aria-label="Anterior">
                        <i class="fa-solid fa-chevron-left"></i>
                    </button>
                    <span class="intra-carrusel-contador" id="contadorBienvenidas" aria-live="polite">1 de <?= count($bienvenidas) ?></span>
                    <button type="button" class="intra-carrusel-btn" data-bs-target="#carruselBienvenidas" data-bs-slide="next" aria-label="Siguiente">
                        <i class="fa-solid fa-chevron-right"></i>
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <div<?php if ($variasBienvenidas): ?> id="carruselBienvenidas" class="carousel slide" data-total="<?= count($bienvenidas) ?>"<?php endif; ?>>
                <div class="<?= $variasBienvenidas ? 'carousel-inner' : '' ?>">
                    <?php foreach ($bienvenidas as $i => $b): ?>
                    <div class="<?= $variasBienvenidas ? 'carousel-item' . ($i === 0 ? ' active' : '') : '' ?>">
                        <div class="intra-bienvenida-persona">
                            <div class="intra-avatar"><?= $e(Intranet::iniciales($b['nombre'])) ?></div>
                            <p class="intra-bienvenida-bajada">
                                Le damos la bienvenida a <strong><?= $e($b['nombre']) ?></strong>,
                                que se <?= $b['futuro'] ? 'suma' : 'sumó' ?> a <?= $e($b['sector'] !== '' ? $b['sector'] : 'XL') ?> el <?= $e(Intranet::ddmm($b['fecha'])) ?>.
                            </p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Comunicados -->
            <section class="col-12 col-lg-8" aria-label="Comunicados">
                <h2 class="intra-seccion-titulo"><i class="fa-solid fa-bullhorn"></i> Comunicados</h2>

                <?php if (!$comunicados): ?>
                    <div class="intra-card text-center text-muted py-4">
                        <i class="fa-regular fa-newspaper fa-2x mb-2 opacity-50"></i>
                        <div class="fw-bold">Todavía no hay comunicados vigentes</div>
                    </div>
                <?php else: ?>
                    <?php if (count($filtros) > 1): ?>
                    <div class="intra-filtros" role="group" aria-label="Filtrar por categoría">
                        <button type="button" class="intra-filtro activo" data-filtro="TODOS">Todos</button>
                        <?php foreach ($filtros as $cod => $nom): ?>
                            <button type="button" class="intra-filtro" data-filtro="<?= $e($cod) ?>"><?= $e($nom) ?></button>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <div id="listaComunicados">
                        <?php foreach ($comunicados as $ic => $c): ?>
                            <?= Intranet::plantilla('comunicado_tarjeta', ['c' => $c, 't' => [
                                'id' => 'com-' . $c['clave'],
                                'clases' => 'intra-aparece intra-elevable',
                                'estilo' => '--i: ' . (4 + min($ic % 10, 6)),
                                'interaccion' => 'completo',
                                'comentarios' => $comentarios[$c['id']] ?? [],
                                'moderar' => $puedeComunicados,
                                'yo' => $yo,
                            ]]) ?>
                        <?php endforeach; ?>
                    </div>

                    <div class="text-center">
                        <button type="button" class="intra-btn" id="btnVerMas" hidden><i class="fa-solid fa-chevron-down"></i> Ver más</button>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Columna derecha (en celular pasa abajo) -->
            <aside class="col-12 col-lg-4 d-flex flex-column gap-4" aria-label="Agenda y equipo">

                <!-- Agenda -->
                <section class="intra-card intra-aparece" style="--i: 4" aria-label="Agenda">
                    <h2 class="intra-seccion-titulo"><i class="fa-solid fa-calendar-days"></i> Agenda</h2>
                    <?php if (!$agendaPorMes): ?>
                        <div class="text-muted small">No hay fechas para este mes ni el próximo.</div>
                    <?php endif; ?>
                    <?php foreach ($agendaPorMes as $ym => $fechas): ?>
                        <div class="agenda-mes"><?= $e(Intranet::mes((int)substr($ym, 5, 2))) ?></div>
                        <ul class="intra-lista">
                            <?php foreach ($fechas as $f): ?>
                            <li class="intra-item<?= $f['hasta'] < $hoy ? ' es-pasado' : '' ?>">
                                <div class="intra-dia"><?= $e($f['desde']->format('j')) ?><small><?= $e(Intranet::diaSemanaCorto($f['desde'])) ?></small></div>
                                <div class="intra-item-texto">
                                    <div class="intra-item-titulo" title="<?= $e($f['titulo']) ?>"><?= $e($f['titulo']) ?></div>
                                    <div class="intra-item-sub">
                                        <span class="intra-tag <?= $tagTipo[$f['tipo']] ?? '' ?>"><?= $e(Intranet::tipoFecha($f['tipo'])) ?></span>
                                        <?= $e(Intranet::rango($f['desde'], $f['hasta'])) ?>
                                        <?php if ($f['detalle'] !== ''): ?> · <?= $e($f['detalle']) ?><?php endif; ?>
                                    </div>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endforeach; ?>
                </section>

                <?php if ($cumpleMes): ?>
                <!-- Cumpleaños del mes -->
                <section class="intra-card intra-aparece" id="cumpleanos" aria-label="Cumpleaños del mes" style="scroll-margin-top: 80px; --i: 5;">
                    <h2 class="intra-seccion-titulo"><i class="fa-solid fa-cake-candles"></i> Cumpleaños de <?= $e($mesNombre) ?></h2>
                    <div class="cumple-grid">
                        <?php foreach ($cumpleMes as $p): ?>
                        <div class="cumple-item<?= $p['es_hoy'] ? ' es-hoy' : ($p['paso'] ? ' es-pasado' : '') ?>">
                            <div class="intra-avatar"><?= $e(Intranet::iniciales($p['nombre'])) ?></div>
                            <div class="intra-item-texto">
                                <div class="intra-item-titulo" title="<?= $e($p['nombre']) ?>"><?= $e($p['nombre']) ?></div>
                                <div class="intra-item-sub"><?= $p['es_hoy'] ? 'Hoy' : $e(Intranet::ddmm($p['fecha'])) ?><?= $p['sector'] !== '' ? ' · ' . $e($p['sector']) : '' ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>

                <?php if ($aniversarios): ?>
                <!-- Equipo XL -->
                <section class="intra-card intra-aparece" id="equipo" aria-label="Equipo XL" style="scroll-margin-top: 80px; --i: 6;">
                    <h2 class="intra-seccion-titulo"><i class="fa-solid fa-people-group"></i> Equipo XL</h2>
                    <?php if ($aniversarios):
                        // Se ven primero los de hoy en adelante (hasta TOPE_EQUIPO_PAGINA); el resto, con "Ver todos"
                        $proximos = array_keys(array_filter($aniversarios, fn($p) => !$p['paso']));
                        $mostrar = array_flip(array_slice($proximos ?: array_keys($aniversarios), 0, Intranet::TOPE_EQUIPO_PAGINA));
                        $ocultos = count($aniversarios) - count($mostrar);
                    ?>
                        <div class="intra-sub-titulo">Aniversarios del mes</div>
                        <div class="cumple-grid" id="gridAniversarios">
                            <?php foreach ($aniversarios as $i => $p): ?>
                            <div class="cumple-item<?= $p['es_hito'] ? ' es-hito' : '' ?><?= $p['es_hoy'] ? ' es-hoy' : ($p['paso'] ? ' es-pasado' : '') ?><?= isset($mostrar[$i]) ? '' : ' d-none' ?>"<?= isset($mostrar[$i]) ? '' : ' data-extra' ?>>
                                <div class="intra-avatar"><?= $p['es_hito'] ? '<i class="fa-solid fa-medal"></i>' : $e(Intranet::iniciales($p['nombre'])) ?></div>
                                <div class="intra-item-texto">
                                    <div class="intra-item-titulo" title="<?= $e($p['nombre']) ?>"><?= $e($p['nombre']) ?></div>
                                    <div class="intra-item-sub"><span class="<?= $p['es_hito'] ? 'fw-bold text-hito' : '' ?>"><?= $p['anios'] === 1 ? '1 año' : $p['anios'] . ' años' ?></span> · <?= $p['es_hoy'] ? 'Hoy' : $e(Intranet::ddmm($p['fecha'])) ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($ocultos > 0): ?>
                            <button type="button" class="intra-btn w-100 mt-2" id="btnVerAniversarios" data-total="<?= count($aniversarios) ?>">
                                Ver todos los aniversarios (<?= count($aniversarios) ?>)
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>
                </section>
                <?php endif; ?>
            </aside>
        </div>
    </main>

    <footer class="footer-bar">
        <div class="container">
            &copy; <?= date('Y') ?> XL Extra Large · Todos los derechos reservados.
        </div>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script src="/recursosHumanos/intranet/assets/intranet_comunicados.js?v=<?= @filemtime(__DIR__ . '/assets/intranet_comunicados.js') ?>"></script>
    <?php if ($hayInteraccion): ?>
    <script src="/recursosHumanos/intranet/assets/intranet_interaccion.js?v=<?= @filemtime(__DIR__ . '/assets/intranet_interaccion.js') ?>"></script>
    <?php endif; ?>
    <script>
    (function () {
        const POR_PAGINA = 10;
        const tarjetas = Array.from(document.querySelectorAll('.com-card'));
        const btnVerMas = document.getElementById('btnVerMas');
        let filtro = 'TODOS';
        let visibles = POR_PAGINA;

        // "Leer más" solo donde el texto quedó cortado (y su clic) viven en intranet_comunicados.js
        function revisarCortes() {
            IntraComunicados.revisarCortes(document.getElementById('listaComunicados') || document);
        }

        function aplicar() {
            let n = 0;
            tarjetas.forEach(function (t) {
                const coincide = filtro === 'TODOS' || t.dataset.cat === filtro;
                if (coincide) n++;
                t.hidden = !coincide || n > visibles;
            });
            if (btnVerMas) btnVerMas.hidden = n <= visibles;
            revisarCortes();
        }

        document.querySelectorAll('.intra-filtro').forEach(function (b) {
            b.addEventListener('click', function () {
                document.querySelectorAll('.intra-filtro').forEach(function (x) { x.classList.remove('activo'); });
                b.classList.add('activo');
                filtro = b.dataset.filtro;
                visibles = POR_PAGINA;
                aplicar();
            });
        });

        if (btnVerMas) {
            btnVerMas.addEventListener('click', function () {
                visibles += POR_PAGINA;
                aplicar();
            });
        }

        aplicar();
        window.addEventListener('resize', revisarCortes);

        // Bienvenidas: carrusel que pasa solo cada 6 s (se frena con el mouse encima y no avanza
        // solo si el usuario pidió reducir animaciones). Se puede deslizar con el dedo.
        const carrusel = document.getElementById('carruselBienvenidas');
        if (carrusel && window.bootstrap) {
            const reducir = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            bootstrap.Carousel.getOrCreateInstance(carrusel, {
                interval: reducir ? false : 6000,
                ride: reducir ? false : 'carousel',
                pause: 'hover',
                touch: true,
                wrap: true
            });
            const contador = document.getElementById('contadorBienvenidas');
            carrusel.addEventListener('slid.bs.carousel', function (ev) {
                contador.textContent = (ev.to + 1) + ' de ' + carrusel.dataset.total;
            });
        }

        // Aniversarios: mostrar u ocultar los que quedaron plegados
        const btnAniv = document.getElementById('btnVerAniversarios');
        if (btnAniv) {
            btnAniv.addEventListener('click', function () {
                const abrir = btnAniv.dataset.abierto !== '1';
                document.querySelectorAll('#gridAniversarios [data-extra]').forEach(function (el) {
                    el.classList.toggle('d-none', !abrir);
                });
                btnAniv.dataset.abierto = abrir ? '1' : '0';
                btnAniv.textContent = abrir ? 'Ver menos' : 'Ver todos los aniversarios (' + btnAniv.dataset.total + ')';
            });
        }

        // Llegada desde el hub con un comunicado elegido: mostrarlo, desplegarlo e ir hasta él
        const elegido = new URLSearchParams(location.search).get('c');
        const destino = elegido ? document.getElementById('com-' + elegido) : null;
        if (destino) {
            const indice = tarjetas.indexOf(destino);
            visibles = Math.ceil((indice + 1) / POR_PAGINA) * POR_PAGINA;
            aplicar();
            destino.classList.add('abierto', 'es-elegido');
            const leer = destino.querySelector('.com-leer');
            if (leer && !leer.hidden) leer.textContent = 'Leer menos';
            destino.scrollIntoView({ block: 'start' });
        }
    })();
    </script>
</body>
</html>
