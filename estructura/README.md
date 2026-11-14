# Estructura XL

Quién es quién en la empresa y a quién consultar cada tema. Es la etapa 1 de un proyecto que después va a sumar un chatbot: el objetivo es que menos consultas entren por un solo canal (Recepción). Por eso las búsquedas se registran, porque son la base para entrenarlo.

Vive en `recursosHumanos/estructura/`. Usa la sesión, `Auth` y `ConexionApps` del hub (`sistemas/hub`), igual que la Intranet. Los datos están en la base central (`conectar('central')`), junto a `RO_T_INTERNOS_PERSONAL`.

## Pantallas

| Pantalla | URL | Login |
|---|---|---|
| Guía de Consultas | `/recursosHumanos/estructura/` y `/recursosHumanos/estructura/guia/` | No |
| Contactos por área | `/recursosHumanos/estructura/contactos/` | No |
| Internos | `/recursosHumanos/estructura/internos/` | No |
| Administrar áreas | `/recursosHumanos/estructura/admin/` | Hub + permiso `estructura.administrar` |
| Ficha de un área (Editar y Nueva área) | `/recursosHumanos/estructura/admin/area.php?id=N` | Hub + permiso `estructura.administrar` |
| Administrar internos (ABM) | `/recursosHumanos/estructura/internos/listado.php` | Hub (la regla de siempre, ver Permisos) |

Todas comparten el mismo menú lateral (`partials/menu.php`) y la estética de XL Gestión. En el celular, el menú queda arriba y las fichas pasan a una columna. Los ítems de administración del menú se dibujan sólo si ya hay una sesión del hub con permiso: el menú nunca obliga a loguearse.

- **Guía de Consultas.** Muestra un buscador ("¿Qué necesitás consultar?"), chips de ejemplo, chips por área y una ficha por área con la tabla Tema | Referente.
  - La búsqueda corre en el navegador sobre las áreas y temas activos, que vienen con la página. Primero normaliza el texto: lo pasa a minúsculas, le saca las tildes y las palabras vacías.
  - Un tema aparece si **todas** las palabras que quedan están contenidas en el tema, en sus palabras clave o en el nombre del área. Basta una coincidencia parcial: "imprim" encuentra Impresoras.
  - Si quedan menos de 2 caracteres útiles, en lugar de resultados muestra "Escribí un poco más…".
  - Acepta dos parámetros en la URL:
    - `?q=` abre con una búsqueda hecha (para la tarjeta del inicio de XL Gestión);
    - `?area=<ID>` abre con un área elegida.
- **Contactos por área.** Muestra, para cada sector:
  - el mail (`mailto:`);
  - el celular, con un botón de WhatsApp Web (`https://web.whatsapp.com/send?phone=549` más los 10 dígitos);
  - el horario de atención;
  - el gerente y el jefe del área.

  Lo que falta se muestra "a dar de alta" o "a definir", en gris itálica. Cada ficha tiene `id="area-<ID>"`, y también acepta `?area=<ID>`.
- **Internos.** Es el Directorio de Personal, migrado de `comercial/recepcion` (ver más abajo).
  - El filtro de sectores pasó arriba de los resultados.
  - Acepta `?q=<nombre>`: lo usa el link "Ver en Internos" de la Guía.
- **Administrar áreas.** Tiene tres partes:
  - el listado de áreas, con su orden, temas, ficha completa, estado, última actualización y las acciones Editar, Subir, Bajar, Desactivar y Reactivar;
  - las búsquedas sin resultado de los últimos 30 días;
  - la importación de Excel.
- **Ficha de un área.**
  - Datos del área: nombre, color, qué resuelve, mail, celular y horario de sector, gerente y jefe.
  - Temas, en una tabla editable: tema, referente y palabras clave, con Subir, Bajar y Quitar.
  - "Guardar ficha" valida todo en el servidor y guarda en una transacción: si algo no pasa, no se guarda nada y cada campo muestra su motivo.

### Ficha completa

Una ficha está **completa** cuando cumple todo esto:
- tiene qué resuelve, mail, celular y horario;
- tiene temas;
- cada tema tiene un referente activo en Internos o el equipo del área.

Gerente y jefe no cuentan, porque hay áreas que no los tienen. Si falta algo, el listado lo dice en naranja, por ejemplo "Falta celular y horario", "6 temas sin referente" o "1 referente inactivo en Internos".

### Personas

Las fichas guardan el **ID** de la persona en `RO_T_INTERNOS_PERSONAL`, nunca el nombre. Así, corregir un nombre en Internos no rompe nada. Si una persona está dada de baja en Internos (`INACTIVO = 1`):
- en la Guía y en Contactos se muestra "A definir";
- en la administración aparece con el aviso "<Nombre> ya no está activa en Internos". Si ya estaba guardada, la ficha se puede guardar igual sin cambiarla. Para elegir una persona nueva, tiene que estar activa.

## URLs viejas → nuevas

Las URLs viejas **no redirigen**: los links se actualizan en las tareas de los repos `comercial`, `sistemas` y `centralizadores`.

| Vieja | Nueva |
|---|---|
| `/comercial/recepcion/index.php` (y `/comercial/recepcion/`) | `/recursosHumanos/estructura/internos/` |
| `/comercial/recepcion/listado.php` | `/recursosHumanos/estructura/internos/listado.php` |
| `/comercial/recepcion/ajax/interno_ajax.php` | `/recursosHumanos/estructura/internos/ajax.php` |
| `/comercial/recepcion/fotos/` (carpeta del servidor) | `/recursosHumanos/estructura/internos/fotos/` |
| — | `/recursosHumanos/estructura/` (Guía de Consultas) |
| — | `/recursosHumanos/estructura/contactos/` |
| — | `/recursosHumanos/estructura/admin/` |

El Direccionario (`/comercial/recepcion/direccionario/`) **no** se migró: es otra tarea.

Links a las URLs viejas que ya se encontraron, para esas tareas:
- `sistemas/xlgestion/config/menu.php`: Internos y Direccionario;
- `sistemas/hub/index.php`: el acceso "Directorio de internos";
- `sistemas/control/index.php`;
- `centralizadores/comer/Class/Menu.php`: "Administrar Internos" y "Directorio de Internos".

## Internos: qué cambió en la migración

1. **Migración tal cual.** Se movieron la vista, el ABM, la clase, el ajax, el JS y el CSS. Los cambios necesarios fueron:
   - la conexión pasó a ser la de este repo;
   - el ABM pasó del bootstrap de Nodo al de estructura. Nodo ya usaba el `Auth` del hub, así que la regla de permisos es la misma;
   - las rutas, incluida la carpeta de fotos, salen de `Class/rutas.php`.
2. **Fotos.** El servidor revisa si existe la foto. Si no existe, la tarjeta muestra las iniciales sin pedir la imagen, sin 404 en la consola ni líneas en el log.
3. **Permisos del ABM en el servidor.** Hasta ahora `interno_ajax.php` no validaba nada: cualquiera, sin login, podía guardar o desactivar.
   - Ahora cada acción pide la regla de siempre (`$puede_crear`, `$puede_editar` y `$puede_eliminar`, en `Permisos::reglaAbmInternos()`).
   - Las acciones que escriben piden CSRF.
   - Los botones del ABM se esconden según esa misma regla.
4. **Reactivar.** El ABM suma el filtro "Ver también las personas desactivadas", para poder reactivarlas.
5. **Menú y estética.** Internos y el ABM toman el menú lateral y la estética nueva.

Sin ningún script corrido, Internos funciona igual que antes: no usa nada del Directorio.

## Permisos

- **`estructura.administrar`**, en `FP_PERMISOS`, módulo `ESTRUCTURA`, que queda inactivo a propósito para no aparecer como app en el hub.
  - Da acceso a Administrar áreas, la ficha, la importación y el panel de búsquedas.
  - Se asigna desde el hub (Gestionusuarios) a los roles de Innovación. El script no lo asigna a ningún rol.
  - El administrador total lo tiene siempre.
  - Toda acción de administración se valida en el servidor (`ajax/admin.php` → `Permisos::autorizarAdmin()`), aunque se la llame directo.
  - Ojo: existe otro permiso, `estructura.ver`, del módulo NODO (el organigrama de Nodo). No tiene relación con este.
- **Administrar internos.** El ítem se ve si hay sesión del hub y alguno de los tres permisos del ABM es verdadero. La regla es la de `comercial/recepcion/listado.php`:
  - crear: sólo admin o Sistemas;
  - editar y desactivar: admin, Sistemas o Recepcionista.
- **Etapa siguiente: un permiso por área**, para los responsables de cada ficha. Se suma **sin rehacer nada**:
  - todo lo que toca una ficha ya pasa por `Permisos::puedeEditarArea($idArea)`, que hoy sólo mira `estructura.administrar`;
  - para sumar responsables:
    1. agregar en `sql/04` un permiso `estructura.editar_ficha`;
    2. crear una tabla `RO_T_DIR_AREAS_RESPONSABLES` (ID_AREA, USUARIO, más la auditoría);
    3. en `puedeEditarArea()`, devolver verdadero también si el usuario tiene ese permiso y es responsable del área.

  Las pantallas y `ajax/admin.php` ya preguntan por área. Lo único que queda por sumar es el filtro del listado, para mostrar sólo las áreas propias.

## Modelo de datos (base central)

Todas las tablas nuevas llevan `USUARIO_ALTA`, `FECHA_ALTA`, `USUARIO_MODIF`, `FECHA_MODIF`, `USUARIO_BAJA` y `FECHA_BAJA`. La excepción es `RO_T_DIR_BUSQUEDAS`, que es un registro y no se edita. El usuario sale de `Auth::username()`. No hay bajas físicas: `ACTIVA` / `ACTIVO = 0` es la baja.

| Tabla | Para qué | Columnas principales |
|---|---|---|
| `RO_T_DIR_AREAS` | Áreas | `ID`, `NOMBRE`, `NOMBRE_CLAVE` (calculada), `COLOR` (#RRGGBB), `ORDEN`, `RESUELVE` (200), `MAIL_SECTOR`, `CELULAR_SECTOR` (10 dígitos), `HORARIO` (100), `ID_GERENTE`, `ID_JEFE`, `ACTIVA` |
| `RO_T_DIR_TEMAS` | Temas de cada área | `ID`, `ID_AREA`, `TEMA`, `TEMA_CLAVE` (calculada), `ORDEN`, `ID_REFERENTE`, `REFERENTE_EQUIPO` (bit), `PALABRAS_CLAVE` (separadas por coma), `ACTIVO` |
| `RO_T_DIR_BUSQUEDAS` | Lo que se busca en la Guía | `TEXTO`, `TEXTO_NORMALIZADO`, `CANT_RESULTADOS`, `ID_AREA_FILTRO`, `USUARIO`, `COD_CLIENT`, `FECHA` |
| `RO_T_DIR_BUSQUEDAS_DESCARTADAS` | Búsquedas descartadas del panel | `TEXTO_NORMALIZADO` (único), `FECHA_DESCARTE` |
| `RO_T_DIR_IMPORTACIONES` | Cada importación de Excel | `ARCHIVO`, áreas y temas creados y actualizados, `FILAS_OMITIDAS`, `AVISOS`, `DETALLE` (JSON), `USUARIO_ALTA`, `FECHA_ALTA` |

Reglas que se cumplen en la base, además de en la app:
- **Nombres únicos.** El nombre de un área es único entre las activas, y un tema es único dentro de su área entre los activos, sin distinguir mayúsculas ni tildes. Se resuelve con `NOMBRE_CLAVE` / `TEMA_CLAVE` = `dbo.RO_FN_DIR_CLAVE(...)` y un índice único filtrado. `RO_FN_DIR_CLAVE` es el mismo algoritmo que `Reglas::clave()`: si se cambia una, hay que cambiar la otra.
- **Referente.** El referente es una persona **o** "el equipo del área", nunca las dos cosas (CHECK). Sin ninguna de las dos, se muestra "A definir".
- **Celular.** El celular se guarda sólo con dígitos: código de área más número, sin 0 ni 15, exactamente 10 (CHECK).
- **Sin FK a Internos.** Es a propósito, para no trabar a otra app que borre en Internos. Un ID que no existe se trata como "A definir".
- **Descartar una búsqueda no la borra.** Guarda el texto normalizado con su fecha, y el panel esconde las búsquedas de ese texto anteriores al descarte. Si alguien lo vuelve a buscar después, reaparece, porque es una señal nueva.

`RO_T_INTERNOS_PERSONAL` ya tenía `ID INT IDENTITY` como clave primaria (relevado el 14/11/2026), así que no se modificó.

## Scripts SQL

Los corre Ramiro, en este orden. Todos son idempotentes: se pueden correr más de una vez.

| Orden | Script | Base | Qué hace |
|---|---|---|---|
| 1 | `sql/01_internos_id.sql` | Central | Sólo verifica que `RO_T_INTERNOS_PERSONAL.ID` sea IDENTITY y único. No modifica nada. |
| 2 | `sql/02_directorio_tablas.sql` | Central | Crea `dbo.RO_FN_DIR_CLAVE` y las tablas `RO_T_DIR_*`. |
| 3 | `sql/03_directorio_carga_inicial.sql` | Central | Carga las 6 áreas y sus temas. Resuelve las personas por nombre contra Internos y avisa con `PRINT` las que no encuentra. Sólo agrega lo que falta: no pisa ediciones. |
| 4 | `sql/04_estructura_permiso.sql` | **XL-APPS** | Crea el módulo `ESTRUCTURA` y el permiso `estructura.administrar`, sin asignarlo a ningún rol. |

Después: asignar el permiso a los roles de Innovación desde el hub, y mover la carpeta de fotos (ver abajo).

Si falta un script, las pantallas nuevas no se caen: dicen cuál falta correr. Internos funciona igual sin ninguno.

## Importar listado (Excel)

**Para qué.** Sirve para la carga inicial y para los cambios masivos.

**La plantilla.** "Descargar plantilla" baja un Excel con los datos actuales. Tiene **una fila por tema**, con los datos del área repetidos en cada fila. Las columnas se reconocen por su nombre, en cualquier orden:

| Columna | Qué va |
|---|---|
| Área | Nombre del área (**obligatoria**) |
| Orden | Número de orden del área |
| Color | Hex, por ejemplo `#2F5FB3` |
| Qué resuelve | Una línea (hasta 200 caracteres) |
| Mail de sector | `sector@xl.com.ar` |
| Celular de sector | Con código de área, sin 0 ni 15: `11 2345 6789` |
| Horario | Texto (hasta 100 caracteres) |
| Gerente | Nombre como figura en Internos |
| Jefe | Nombre como figura en Internos |
| Tema | Nombre del tema (**obligatoria**) |
| Referente | Nombre como figura en Internos, o la palabra **Equipo** |
| Palabras clave | Separadas por coma |

**Reglas.**
- **Agrega y actualiza; nunca borra ni desactiva.**
- **Una celda vacía no borra un dato.** Para borrarlo, se edita la ficha.
- El área se reconoce por su nombre, y el tema por su nombre dentro del área, sin mayúsculas ni tildes: "Logistica" = "Logística".

**Antes de guardar.** Primero se ve una **vista previa**: qué se crea, qué se actualiza (con el campo, el valor anterior y el nuevo), los avisos y los errores. Nada se guarda hasta "Confirmar importación". Al confirmar, se vuelve a calcular todo contra lo último y se guarda en **una transacción**: todo o nada. Queda registrado en `RO_T_DIR_IMPORTACIONES`.

**Avisos (no bloquean):**
- una persona que no está en Internos, está inactiva o aparece repetida: se importa vacía. En un tema o un área que ya existía, se deja la persona que tenía;
- un dato del área que viene distinto en dos filas: se toma el de la primera fila;
- un área y tema repetidos en el archivo: se omite la segunda fila;
- áreas y temas activos que no vienen en el archivo: "no se tocan".

**Errores (bloquean todo, con fila y motivo):**
- una fila sin área o sin tema;
- un mail, celular, color u orden inválidos;
- un texto demasiado largo;
- un área o un tema que existe **desactivado**: se reactiva desde la pantalla, no importando.

**Filas omitidas:** las vacías y las repetidas.

## Registro de búsquedas

La Guía registra una búsqueda **cuando el usuario deja de escribir**: 1,5 segundos sin teclear, Enter, tocar un chip de ejemplo o abrir la página con `?q=`. Nunca registra una por tecla.

**Cuándo no registra:**
- si quedan menos de 3 caracteres útiles;
- si es igual a la última registrada.

**Qué guarda:**
- el texto tal como se escribió;
- el texto normalizado;
- la cantidad de resultados, que **calcula el servidor**;
- el área elegida;
- el usuario y el `COD_CLIENT` de la sesión de XL Gestión (`$_SESSION['username']` y `$_SESSION['codClient']`).

**Si falla, la pantalla no avisa:** el error queda en el log de PHP.

**Límite.** Como no pide login, `ajax/consulta.php` acepta hasta 30 registros cada 5 minutos por sesión.

## Fotos de Internos

La carpeta pasa a ser `estructura/internos/fotos/` (constante `ESTRUCTURA_FOTOS_DIR`). **Ramiro la mueve en el servidor** desde `comercial/recepcion/fotos/`. Está en el `.gitignore`: las fotos no se suben al repo.

Mientras la carpeta no esté, las tarjetas muestran las iniciales, como cuando alguien no tiene foto.

Formatos de nombre que se aceptan:
- `Apellido Nombre.jpg`;
- `Nombre_Apellido.jpg`;
- el que armaba el JS viejo: sólo el primer espacio pasado a `_`.

## Pruebas

- `php estructura/tests/run.php` corre las pruebas de la lógica pura, sin base: normalización, búsqueda, celular, mail, color, referente, ficha completa, registro, importación, validación de la ficha y permisos. Se corre antes de cada commit.
- `/recursosHumanos/estructura/tests/paridad.html`, abierto en el navegador, corre **los mismos casos** con el normalizador JS (`tests/casos_compartidos.json` y `tests/fixture_directorio.json`). El título queda "Paridad OK" o "Paridad FALLA". La lista de palabras vacías y el mapa de tildes existen sólo en PHP (`Reglas`), y el JS los recibe al cargar la página.

## Notas técnicas

- **Rutas.** Todas las URLs y carpetas están en `Class/rutas.php`. El JS las recibe en `window.ESTRUCTURA` (`partials/config_js.php`).
- **Orden de carga del `.env`.** `Class/bootstrap.php` carga el `.env` con el `DotEnv` del repo **antes** que el bootstrap del hub. La razón:
  - `ConexionApps` deja las variables sólo en `$_ENV`;
  - `DotEnv` saltea las que ya están ahí y no las pasa a `putenv()`;
  - `Conexion` las lee con `getenv()`.

  En el otro orden, `conectar('central')` quedaba sin credenciales.
- **Lógica pura.** La lógica sin base está en `Class/Reglas.php`, `Class/Ficha.php`, `Class/Importacion.php` y `Class/Permisos.php` (la parte pura). La base está sólo en `Class/Directorio.php` y `Class/Interno.php`.
- **PHP.** El código es compatible con PHP 7.3 (`composer.json`): sin funciones flecha, sin propiedades tipadas, sin `match`. El servidor corre PHP 8.2.
- **Librerías.** Son las que ya usaba Internos: Bootstrap 5.1.3, Bootstrap Icons 1.8.1, jQuery y SweetAlert2 (sólo Internos), y SheetJS 0.17.0 (exportar Internos e importar áreas).
- **Maquetas.** Están en `docs/maquetas/*.dc.html`. Son la referencia de diseño y de contenido, no código: usan el runtime del canvas de diseño (`support.js`, que no está acá). Donde la especificación dice otra cosa, manda la especificación; por ejemplo, sin la columna "Responsables" en Áreas y con el panel de búsquedas sin resultado en el listado.

## Pendientes a definir

- **Filtro de "Direccion" en Internos.** Se migró tal cual:
  - `getSectores()` esconde el sector "Direccion" cuando `$_SESSION['entorno'] == 'sistemas'` (cualquiera que entra por XL Gestión);
  - `buscarInternos()` esconde a las personas de Dirección sólo si `entorno === 'sucursal'`, y nada pone ese valor. O sea que el chip no se ve, pero las personas sí.

  Falta definir la regla.
- **Permisos por área para los responsables de cada ficha.** Ver "Permisos".
