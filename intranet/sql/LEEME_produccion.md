# Intranet XL · Comunicados v2: pasos para producción

Para Sistemas. Servidor app.xl.com.ar (Windows, Apache 2.4, PHP 8.2). El sitio se publica desde `V:\`.

## 1. Carpeta de archivos

Las imágenes y los PDF de los comunicados se guardan en disco, **fuera de la carpeta pública del sitio**, y solo se entregan a través de `recursosHumanos/intranet/archivo.php`, que controla la sesión y los permisos.

1. Crear una carpeta **fuera de `V:\`**, por ejemplo `W:\datos_apps\intranet_archivos`.
2. Dar permiso de **Modificar** (leer, escribir, crear y borrar) sobre esa carpeta y sus subcarpetas a la cuenta con la que corre el servicio de Apache: es la que figura en *Servicios → Apache → Propiedades → Iniciar sesión*.
3. Cargar la ruta absoluta en el `.env`:

   ```
   INTRANET_ARCHIVOS=W:\datos_apps\intranet_archivos
   ```

La app crea sola las subcarpetas `comunicados\` y `temp\`.

**La ruta se interpreta en el servidor, no en la PC de quien edita el `.env`.**
- Lo más simple es una **carpeta en un disco local del servidor**, por ejemplo `C:\xampp\datos_intranet` o `D:\datos_intranet`, siempre fuera de `V:\`.
- **No usar letras de unidades de red mapeadas** (`V:`, `W:`, `T:`…). Son conexiones de la sesión de un usuario y el servicio de Apache no las ve: la carpeta "no existe" para la app.
- Si la carpeta tiene que estar en otro equipo, usar la ruta de red completa (`\\servidor\recurso\intranet_archivos`) y darle permiso a la cuenta del servicio de Apache tanto en el recurso compartido como en la carpeta. Si el servicio corre como *Sistema local*, el permiso va para la cuenta del equipo (`DOMINIO\NOMBRE-SERVIDOR$`).
- Cada entorno tiene su propio `.env`: el del servidor lleva la ruta del servidor, y el de una PC de desarrollo, la de esa PC.

Si la variable falta, la carpeta no existe, no tiene permiso de escritura o queda **dentro de la carpeta pública** (`V:\` o la raíz del sitio), la app no la usa. En ese caso, la pantalla de carga muestra *"No se pueden subir archivos: falta configurar la carpeta"* y los comunicados se guardan sin archivos. El motivo exacto queda en el log de errores de PHP, con el prefijo `IntranetArchivos:`.

Respaldo: la carpeta tiene que entrar en el backup junto con la base. Los registros de `FP_INTRA_COMUNICADOS_ARCHIVOS` apuntan a esos archivos.

## 2. PHP

Extensiones activas en `php.ini`:

- `gd`, con soporte **WEBP**: en `gd_info()` tiene que figurar `WebP Support => true`.
- `exif`, para girar las fotos de celular.
- `fileinfo`, para validar el tipo real de cada archivo.

Valores mínimos:

| Directiva | Mínimo |
|---|---|
| `upload_max_filesize` | `12M` |
| `post_max_size` | `16M` |
| `max_file_uploads` | `5` |
| `memory_limit` | `256M` |
| `max_execution_time` | `60` |

Los archivos se suben de a uno: el mayor es un PDF de 10 MB.

Para verificar, se puede correr desde la consola del servidor:

```
php -r "var_export(gd_info()); foreach (['upload_max_filesize','post_max_size','max_file_uploads','memory_limit','max_execution_time'] as $k) echo PHP_EOL, $k, ' = ', ini_get($k);"
php -m
```

Atención: la consola puede usar otro `php.ini` que Apache. Hay que confirmar con `phpinfo()` desde el navegador, o revisando `PHPIniDir` en la configuración de Apache. Después de cambiar `php.ini`, reiniciar Apache.

## 3. Scripts SQL (base XL-APPS)

Correr en este orden. Todos se pueden volver a correr sin problema.

1. `07_intra_comunicados_estilo.sql`, si todavía no se corrió.
2. `08_intra_comunicados_v2.sql`: esquemas, texto con formato, borradores y aviso emergente.
3. `09_intra_comunicados_archivos.sql`: tabla de imágenes y PDF.
4. `10_intra_comentarios_reacciones.sql`: casillas "Permitir comentarios" y "Permitir reacciones" en cada comunicado, y tablas de comentarios y reacciones. Si se corrió una versión anterior, conviene volver a correrlo: agrega el nombre en las reacciones (sin eso todo funciona, pero «Ver quiénes reaccionaron» muestra el nombre actual de cada usuario).
5. `11_intra_plantillas.sql`: plantillas de comunicado, permiso `intranet.plantillas.administrar` (sin asignar: se asigna por rol desde Gestionusuarios) y las 9 plantillas iniciales. Necesita el 06, 07, 08 y 10: si falta alguno, avisa y no cambia nada.
6. `12_intra_acceso_rapido.sql`: Intranet XL pasa a ser un Acceso Directo del hub con el permiso `hub.acceso.intranet`. **No se asigna a ningún rol**: queda oculto para todos, salvo el administrador total. Para mostrarlo, se asigna por rol desde Gestionusuarios. Necesita el 06.
7. `13_intra_panel_hub.sql`: permiso `hub.panel.intranet` para el panel derecho de Intranet XL del hub y el aviso emergente de comunicados importantes. Mismo criterio que el 12: sin asignar, solo lo ve el administrador total. Necesita el 06. **Publicar el `hub/index.php` nuevo recién después de correrlo**: sin el permiso, el panel desaparece para todos los que no son administradores.

8. `14_intra_ruta_recursos_humanos.sql`: la Intranet pasa de `sistemas/hub/intranet` a `recursosHumanos/intranet`. Actualiza la URL del Acceso Directo (`FP_MODULOS.ruta_url` del módulo `INTRANET`). Necesita el 06. **Correrlo al publicar** `recursosHumanos/intranet` y el `hub/index.php` nuevo: la carpeta vieja deja de existir.

La app funciona aunque falte alguno: lo que depende de ese script no se ofrece.

## 4. Control rápido después de publicar

1. Entrar a *Intranet XL → Cargar novedades → Nuevo comunicado*: tienen que aparecer las zonas **Imágenes** y **Adjuntos PDF**, sin el aviso de la carpeta.
2. Subir una foto: tiene que mostrar el peso antes y después (por ejemplo, *2,4 MB → 310 KB*).
3. Verificar que en la carpeta configurada aparecieron archivos dentro de `temp\` y que, al guardar el comunicado, pasaron a `comunicados\<id>\`.
4. Con el script 10 corrido: en el formulario del comunicado tiene que aparecer la sección **Participación** con las dos casillas, y en el listado la columna **Participación**.
5. Con el script 11 corrido: *Nuevo comunicado* tiene que mostrar primero las tarjetas de las plantillas, y con el permiso nuevo asignado aparece la solapa **Plantillas** con las 9 iniciales.
