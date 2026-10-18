# MINA

Sistema web para la gestión operativa y financiera del taller MINA. Centraliza órdenes de trabajo (OT), procesos, recursos, almacén, costos, ingresos y caja, con una API para MINA Móvil.

## Funcionalidades

- **Órdenes de trabajo:** registro, estados, filtros, seguimiento y resumen operativo y financiero. La finalización exige que sus procesos estén finalizados.
- **Procesos:** etapas, fechas, pesos de entrada y salida, trabajos de empleados, equipos, materiales y otros costos.
- **Equipos:** componentes independientes de depreciación, electricidad comprada e insumos de almacén; horas efectivas o lecturas de horómetro; parámetros históricos por uso.
- **Almacén:** productos, existencias, costo promedio y movimientos. Los equipos distinguen entregas, consumos, devoluciones y sobrantes con valor histórico.
- **Finanzas:** costos, ingresos, acuerdos, liquidaciones, cobros y movimientos de caja, con equivalentes en córdobas.
- **Lama:** acuerdos de conservación y reparto del oro posterior, lotes con cantidades estimadas, tratamiento por lotes completos y resultado conjunto enlazado al servicio original. [Guía de uso web/móvil](docs/FLUJO_LAMA.md).
- **Seguimiento:** panel del taller, reportes y distribución informativa de gastos generales entre procesos, sin duplicar movimientos.
- **Avance y reparaciones:** porcentaje por procesos finalizados, líneas de tiempo con reparaciones en rojo, ampliación manual de asignaciones y costos de reparación vinculados a la OT y al proceso. El horómetro conserva las horas reales. [Guía web/móvil y sincronización](docs/SEGUIMIENTO_REPARACIONES.md).
- **Navegación guiada:** buscador de pantallas y tareas en la barra superior (`Ctrl + /`), con sinónimos, orientación para acciones dentro de una OT y accesos rápidos en la barra y el inicio. Solo ofrece destinos autorizados para el usuario.
- **Acceso:** usuarios, roles y permisos por módulo.
- **API móvil:** autenticación con tokens, consulta y registro operativo, descarga de datos y sincronización de operaciones compatibles con Offline.

## Tecnologías y requisitos

- PHP **8.4 o superior**, Composer y Laravel **13**.
- MySQL/MariaDB para los datos operativos.
- Node.js compatible con Vite 8 (por ejemplo, **22.12 o superior**) y npm.
- Blade, Bootstrap, DataTables, Select2 y SweetAlert2.
- Laravel Sanctum, Spatie Permission y Dompdf.

## Instalación local

```bash
git clone https://github.com/Hitzui/mina.git
cd mina
composer install
cp .env.example .env
php artisan key:generate
npm ci
```

En PowerShell, puede copiar el archivo de entorno con `Copy-Item .env.example .env`.

Configure `.env` con el nombre del sistema, URL y conexión de base de datos:

```dotenv
APP_NAME=MINA
APP_URL=http://localhost:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mina
DB_USERNAME=usuario_local
DB_PASSWORD=clave_local
```

Cree la base de datos y ejecute:

```bash
php artisan migrate
php artisan db:seed
npm run build
php artisan serve
```

El seeder principal configura roles y permisos; **no crea usuarios ni carga todos los catálogos operativos**. El responsable de la instalación debe crear el primer administrador y preparar los catálogos necesarios. No hay credenciales predeterminadas documentadas en este repositorio.

Con Apache/XAMPP, configure el sitio para servir el directorio `public/` y ajuste `APP_URL` a la dirección utilizada. Para desarrollo del frontend puede ejecutar `npm run dev`.

## Costeo de equipos

Las fechas de asignación controlan disponibilidad. El costo por tiempo utiliza las **horas efectivas**, ingresadas directamente o mediante la diferencia del horómetro.

Una planta generadora puede usar combustible y depreciación; un molino o una bomba eléctrica, potencia y tarifa de electricidad, con depreciación opcional. Los insumos entregados permanecen en el saldo del equipo hasta consumirse o devolverse. Solo el consumo carga costo al proceso y a la OT.

Los usos conservan sus parámetros históricos. Las estimaciones de combustible son informativas y no se suman al consumo registrado.

Consulte la guía completa en [docs/COSTEO_EQUIPOS.md](docs/COSTEO_EQUIPOS.md).

## MINA Móvil y API

El cliente Android, iOS y escritorio se desarrolla en [Hitzui/mina-mobil](https://github.com/Hitzui/mina-mobil).

- Prefijo de API: `/api/v1`.
- Autenticación: token Bearer de Sanctum y permisos del usuario.
- Contrato base: [docs/api-openapi-v1.yaml](docs/api-openapi-v1.yaml); las rutas y validaciones actuales están en `routes/api.php` y los controladores API.
- Offline: descarga de datos y cola de operaciones compatibles; el servidor valida al sincronizar.
- Las entregas, consumos y devoluciones de equipos requieren **Online**.
- Los lotes, tratamientos, recuperaciones y valoraciones de lama se gestionan **Online**; Offline permite consultar la copia descargada.
- El móvil registra **cobros y abonos por OT**, incluso después de finalizarla: Online confirma el cobro y su entrada de caja; Offline lo conserva pendiente con reintentos idempotentes hasta subir. Usa la misma moneda del ingreso y la caja activa asignada. [Guía funcional y contrato API](https://github.com/Hitzui/mina-mobil/blob/main/docs/COBROS_MOVIL.md).

Configure en el cliente la URL del servidor accesible desde el dispositivo. En un teléfono físico, `localhost` se refiere al propio teléfono.

## Pruebas

```bash
php artisan test --filter=CosteoIntegralEquiposTest
php artisan test --filter=DepreciacionEquiposTest
php artisan test --filter=EquiposEnProcesosTest
php artisan test --filter=OrdenCerradaTest
php artisan test --filter=FlujoLamasTest
php artisan test --filter=MobileCollectionTest
```

Las pruebas de integración usan MySQL/MariaDB y algunas requieren catálogos y registros de referencia. Revise `phpunit.xml` y la conexión de pruebas antes de ejecutarlas: la configuración local utiliza transacciones y **no debe reiniciarse con `migrate:fresh`** una base que contenga información del taller.

## Estructura

```text
app/Models/              Modelos y reglas de datos
app/Services/            Inventario, costeo, resúmenes y operaciones de negocio
app/Http/Controllers/    Pantallas web y API móvil
app/DataTables/          Listados y filtros
resources/views/        Interfaz Blade
public/js/              Comportamiento de pantallas
database/migrations/    Evolución del esquema
routes/                 Rutas web y API
tests/                  Pruebas automatizadas
docs/                   Guías y contrato base de API
```

## Actualización de una instalación

Después de obtener una versión nueva, instale dependencias, aplique las migraciones pendientes y reconstruya los recursos cuando corresponda:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
npm ci
npm run build
php artisan optimize:clear
```

La publicación en Git y la actualización del servidor son operaciones independientes. El servidor y los clientes instalados deben actualizarse para utilizar funcionalidades nuevas.
