# Gestión de Clientes Web

## Descripción General

**Gestión de Clientes Web** es una aplicación interna robusta y moderna, diseñada para interactuar con el ecosistema de bases de datos de la empresa. Permite a los usuarios realizar un ciclo completo de operaciones sobre la base de datos de clientes: **Crear**, **Enviar (replicar)** y **Consultar**.

La aplicación ha sido refactorizada para modernizar su arquitectura, fortalecer la seguridad, profesionalizar su estética visual y optimizar drásticamente la experiencia de usuario con funcionalidades interactivas y en tiempo real.

## Características Principales

-   **Creación de Clientes:** Permite dar de alta nuevos clientes de tipo "Directores" y "Empleados" en la base de datos central, sugiriendo automáticamente el próximo código correlativo disponible.
-   **Envío Masivo de Clientes a Sucursales:** Permite seleccionar múltiples clientes y múltiples sucursales a través de una interfaz intuitiva con checkboxes y filtros, para luego replicar los datos de los clientes seleccionados en las bases de datos de las sucursales elegidas en una sola operación.
-   **Consulta de Clientes Avanzada:**
    -   Muestra una tabla de datos interactiva para visualizar el listado completo de clientes.
    -   **Filtros en backend:** Permite filtrar por Tipo de Cliente y por Estado.
    -   **Funcionalidad en frontend:** Incluye búsqueda rápida en tiempo real, ordenación por columnas y paginación, gracias a la librería **DataTables.js**.
    -   **Gestión de Estado en Tiempo Real:** Permite habilitar o inhabilitar un cliente directamente desde la tabla con un "switch" interactivo que actualiza la base de datos vía **AJAX**, sin recargar la página y proveyendo notificaciones instantáneas.
-   **Diseño Profesional y Responsivo:** Interfaz limpia y moderna construida con **Bootstrap 5**, adaptable a diferentes tamaños de pantalla.
-   **Seguridad y Portabilidad:**
    -   Utiliza **consultas preparadas** en todas las interacciones con la base de datos para prevenir inyecciones SQL.
    -   Gestiona las credenciales de forma segura a través de un archivo de entorno (`.env`).
    -   Construye cadenas de conexión **ODBC DSN-less**, haciendo la aplicación portable sin necesidad de configurar DSNs en el sistema operativo.

## Tecnologías Utilizadas

-   **Backend:** PHP 8.1+
-   **Base de Datos:** Microsoft SQL Server
-   **Dependencias PHP:** `vlucas/phpdotenv` (gestionado con Composer)
-   **Frontend:** HTML5, Bootstrap 5, JavaScript (ES6)
-   **Librerías JavaScript:** jQuery, DataTables.js, Select2.js

## Estructura del Proyecto

recursosHumanos/
├── .env                  <-- (NO SUBIR A GIT) Credenciales y configuración del entorno.
├── composer.json         <-- Define las dependencias de PHP.
├── vendor/               <-- (NO SUBIR A GIT) Librerías de Composer.
└── clientes/
    ├── config.php        <-- Carga el .env y define constantes globales de configuración.
    ├── index.php         <-- Menú principal y punto de entrada de la aplicación.
    ├── elige_crear.php   <-- Menú para elegir qué tipo de cliente crear.
    ├── crear.php         <-- Formulario para la creación de un nuevo cliente.
    ├── elige.php         <-- Menú para elegir qué tipo de cliente enviar.
    ├── clientes.php      <-- Formulario para seleccionar clientes y sucursales a enviar.
    ├── consulta.php      <-- Página de consulta con filtros y tabla de datos interactiva.
    ├── ajax_toggle_estado.php <-- Endpoint AJAX para cambiar el estado de un cliente.
    │
    ├── Class/            <-- LÓGICA DE NEGOCIO Y ACCESO A DATOS (MODELO)
    │   ├── Conexion.php  <-- Gestiona todas las conexiones a las bases de datos.
    │   ├── Cliente.php   <-- Métodos para crear, consultar y obtener datos de clientes.
    │   └── Local.php     <-- Métodos para listar sucursales y sus datos de conexión.
    │
    ├── Controlador/      <-- ARCHIVOS QUE PROCESAN ENVÍOS DE FORMULARIOS (CONTROLADOR)
    │   ├── procesar_crear.php <-- Procesa la creación de un nuevo cliente.
    │   └── procesar.php       <-- Procesa el envío masivo de clientes a sucursales.
    │
    ├── js/               <-- Archivos de JavaScript.
    │   └── main.js       <-- Lógica del cliente (filtros, validaciones, etc.).
    │
    └── templates/        <-- PLANTILLAS REUTILIZABLES DE HTML (VISTA)
        ├── header.php    <-- Encabezado, menú de navegación, CSS y scripts globales.
        └── footer.php    <-- Pie de página y scripts JS globales.

## Flujos de Funcionamiento y Lógica Clave

### 1. Configuración y Conexión (`.env`, `config.php`, `Class/Conexion.php`)

-   Toda la configuración sensible (credenciales de BBDD) se almacena en el archivo `.env`.
-   `config.php` es el primer archivo que se incluye en cualquier script principal. Utiliza `phpdotenv` para cargar las variables del `.env` y las define como constantes de PHP (ej: `DB_HOST_CENTRAL`).
-   `Class/Conexion.php` es la única clase responsable de crear conexiones.
    -   El método `conectar()` construye cadenas de conexión **ODBC DSN-less**, que incluyen el driver, servidor, y base de datos, eliminando la necesidad de configurar DSNs en el servidor.
    -   Ofrece métodos públicos (`conexionLakerbis`, `conexionSucursalPorHost`) que simplifican el acceso a las diferentes bases de datos del sistema.

### 2. Flujo "Crear Cliente" (`elige_crear.php` -> `crear.php` -> `Controlador/procesar_crear.php`)

1.  El usuario elige el tipo de cliente ("Director" o "Empleado").
2.  `crear.php` se conecta a la base de datos central (`LAKERBIS`) para **sugerir el siguiente código numérico disponible**, mejorando la experiencia de usuario.
3.  Al enviar el formulario, `Controlador/procesar_crear.php` recibe los datos.
4.  Valida si el DNI ya existe. Si no, inserta los datos de forma segura usando **consultas preparadas** y **transacciones** para garantizar la integridad de los datos en `GVA14` y `GVA14ITC`.

### 3. Flujo "Enviar Cliente a Sucursales" (Lógica Principal)

Este es el flujo más complejo y el núcleo de la aplicación.

1.  **Selección de Tipo (`elige.php`):** El usuario elige el tipo de cliente a enviar (ej: "Empleados"), lo que lo dirige a `clientes.php?cod=E`.

2.  **Carga de Datos (`clientes.php`):**
    -   Se instancia la clase `Cliente` para llamar a `listarEmpleados('E')`, que trae la lista de clientes desde la BD central (`LAKERBIS`).
    -   Se instancia la clase `Local` para llamar a `listarLocales()`. **Esta es una lógica crucial:**
        -   El método se conecta a `LAKERBIS` (donde está la información de las sucursales).
        -   Ejecuta una consulta que une `SUCURSAL` y `SUCURSALES_LAKERS`.
        -   De esta consulta extrae dos datos vitales para cada sucursal:
            -   `CONEXION_DNS` (alias `Host`): La dirección del servidor de la sucursal (ej: `xlunicenter.dnsalias.com,6089`).
            -   `BASE_NOMBRE` (alias `DbName`): El nombre de la base de datos en ese servidor (ej: `XL_UNICENTER`).

3.  **Construcción del Formulario (`clientes.php`):**
    -   Se crea una lista de checkboxes para los clientes.
    -   Se crea una lista de checkboxes para las sucursales. El `value` de cada checkbox se construye uniendo el Host y el DbName con un separador `|`. Ejemplo: `value="xlunicenter.dnsalias.com,6089|XL_UNICENTER"`.

4.  **Procesamiento (`Controlador/procesar.php`):**
    -   El script recibe un array de códigos de cliente y un array con los valores combinados de las sucursales.
    -   Inicia un **bucle principal** por cada cliente seleccionado.
    -   Dentro de ese bucle, llama a `traerDatosEmpleados()` para obtener todos los datos del cliente actual desde la BD central.
    -   Inicia un **bucle anidado** por cada sucursal seleccionada.
    -   **Lógica de Conexión Dinámica:**
        -   Toma el valor combinado (ej: `"xlunicenter.dnsalias.com,6089|XL_UNICENTER"`).
        -   Lo divide por el `|` para obtener el `hostInfo` y el `dbNameSucursal`.
        -   A su vez, divide el `hostInfo` por la `,` para separar el host del puerto.
        -   Llama al método `conexionSucursalPorHost($hostCompleto, $dbNameSucursal)` de la clase `Conexion` para establecer una conexión directa con la base de datos de esa sucursal específica.
    -   **Lógica de Inserción Segura (Anti-Driver ODBC):**
        -   Para evitar errores de "Número de parámetros no válido" con el driver ODBC, se utiliza una **estrategia de dos pasos** para cada inserción:
            1.  **Verificar:** Se ejecuta un `SELECT COUNT(*)` para comprobar si el registro ya existe.
            2.  **Insertar:** Si el conteo es `0`, se ejecuta un `INSERT` simple.
        -   Esta lógica se aplica para `GVA14` y `GVA14ITC`.
    -   Toda la operación para una sucursal se envuelve en una **transacción** (`odbc_autocommit`, `odbc_commit`, `odbc_rollback`) para asegurar que o se insertan todos los datos del cliente, o no se inserta ninguno.

### 4. Flujo "Consultar Cliente" (`consulta.php`)

-   **Backend:** La página `consulta.php` obtiene los datos de la base de datos central aplicando los filtros de "Tipo" y "Estado" directamente en la consulta SQL.
-   **Frontend:** La librería **DataTables.js** convierte la tabla HTML en una grilla interactiva con paginación, búsqueda en tiempo real y ordenación por columnas, mejorando la usabilidad.
-   **Actualización de Estado (AJAX):**
    -   Un "switch" en cada fila permite al usuario cambiar el estado del cliente.
    -   Al hacer clic, JavaScript envía una petición **AJAX** (`fetch`) al endpoint `ajax_toggle_estado.php` con el código del cliente y el nuevo estado.
    -   El script PHP ejecuta la actualización (`UPDATE GVA14 SET FECHA_INHA = ...`) en la base de datos central de forma segura.
    -   El frontend recibe la confirmación y muestra una notificación de éxito o error, sin necesidad de recargar toda la página.