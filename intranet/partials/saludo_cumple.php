<?php
/**
 * Saludos personales del usuario logueado (hub e intranet):
 *   $saludo     = Intranet::cumpleUsuario();      cumpleaños de hoy
 *   $saludoAniv = Intranet::aniversarioUsuario(); aniversario en XL de hoy
 * Cualquiera de los dos puede ser null; si cumple ambos, ve los dos.
 * Cada uno se puede cerrar y queda cerrado por el resto del día en ese navegador.
 * No muestra edad ni año de nacimiento.
 */
// Vista previa (?vista_previa=saludo): muestra los dos saludos con el nombre del usuario
$previa = Intranet::vistaPreviaSaludos();
if ($previa) {
    [$saludo, $saludoAniv] = $previa;
}
$marcaPrevia = $previa ? ' (vista previa)' : '';

$saludos = [];
if (!empty($saludo)) {
    $saludos[] = [
        'clave'  => $previa ? '' : 'intra_saludo_' . date('Ymd'),
        'clase'  => '',
        'icono'  => 'fa-cake-candles',
        'titulo' => '¡Feliz cumple, ' . $saludo['pila'] . '! 🎂',
        'sub'    => 'Todo el equipo de XL te desea un gran día.' . $marcaPrevia,
    ];
}
if (!empty($saludoAniv)) {
    $saludos[] = [
        'clave'  => $previa ? '' : 'intra_saludo_aniv_' . date('Ymd'),
        'clase'  => ' es-aniversario',
        'icono'  => !empty($saludoAniv['es_hito']) ? 'fa-medal' : 'fa-champagne-glasses',
        'titulo' => '¡Feliz aniversario, ' . $saludoAniv['pila'] . '! 🎉',
        'sub'    => 'Hoy cumplís ' . Intranet::anios((int)$saludoAniv['anios']) . ' en XL. ¡Gracias por ser parte del equipo!' . $marcaPrevia,
    ];
}
if (!$saludos) {
    return;
}
?>
<?php foreach ($saludos as $s): ?>
<div class="intra-saludo<?= $s['clase'] ?>" role="status" data-clave="<?= Intranet::e($s['clave']) ?>">
    <div class="intra-saludo-icono"><i class="fa-solid <?= $s['icono'] ?>"></i></div>
    <div class="intra-saludo-texto">
        <div class="intra-saludo-titulo"><?= Intranet::e($s['titulo']) ?></div>
        <div class="intra-saludo-sub"><?= Intranet::e($s['sub']) ?></div>
    </div>
    <button type="button" class="intra-saludo-cerrar" aria-label="Cerrar saludo" title="Cerrar">
        <i class="fa-solid fa-xmark"></i>
    </button>
</div>
<?php endforeach; ?>
<script>
(function () {
    document.querySelectorAll('.intra-saludo[data-clave]').forEach(function (caja) {
        const clave = caja.dataset.clave; // vacía en la vista previa: no se recuerda el cierre
        try {
            if (clave && localStorage.getItem(clave) === '1') { caja.remove(); return; }
        } catch (e) { /* sin almacenamiento: se muestra igual */ }
        caja.querySelector('.intra-saludo-cerrar').addEventListener('click', function () {
            try { if (clave) localStorage.setItem(clave, '1'); } catch (e) {}
            caja.remove();
        });
        lanzarConfeti(caja);
    });

    // Papel picado una sola vez al aparecer (no si el usuario pidió reducir animaciones)
    function lanzarConfeti(caja) {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        const colores = ['#4f46e5', '#db2777', '#f59e0b', '#10b981', '#0ea5e9', '#a855f7'];
        for (let i = 0; i < 26; i++) {
            const p = document.createElement('span');
            p.className = 'intra-confeti';
            p.style.left = (Math.random() * 100) + '%';
            p.style.background = colores[i % colores.length];
            p.style.animationDelay = (0.3 + Math.random() * 0.6) + 's';
            p.style.setProperty('--dx', (Math.random() * 60 - 30) + 'px');
            p.style.setProperty('--rot', (Math.random() * 720 - 360) + 'deg');
            caja.appendChild(p);
            setTimeout(function () { p.remove(); }, 3500);
        }
    }
})();
</script>
