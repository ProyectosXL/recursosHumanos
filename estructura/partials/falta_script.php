<?php
/**
 * Aviso de las pantallas nuevas cuando falta correr un script de sql/ (o no hay base).
 * Por qué un aviso y no un error: si falta un script, la pantalla tiene que decir cuál, no caerse.
 * Espera $estado = Directorio::estado(). Con 'ok' y sin nada que falte no dibuja nada.
 */
if (!empty($estado['sin_conexion'])): ?>
        <div class="est-aviso est-aviso--error" role="alert">
            No se pudo leer la base de datos. Probá de nuevo en unos minutos; si sigue, avisá a Innovación.
        </div>
<?php elseif (!empty($estado['faltan'])): ?>
        <div class="est-aviso" role="status">
            <?php if (!$estado['ok']): ?>
                Esta pantalla todavía no está lista: falta correr
            <?php else: ?>
                Todavía no hay áreas cargadas: falta correr
            <?php endif; ?>
            <?php foreach ($estado['faltan'] as $i => $script): ?><?= $i ? ' y ' : '' ?><code>estructura/sql/<?= htmlspecialchars($script) ?></code><?php endforeach; ?>
            (ver el README de estructura).
        </div>
<?php endif;
