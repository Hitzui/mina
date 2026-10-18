# Avance de OT y reparaciones durante una asignación

## Qué muestran las dos barras

- **Avance del trabajo:** procesos finalizados / procesos vigentes. Cada proceso tiene el mismo peso; los cancelados se excluyen. Una OT sin procesos vigentes muestra «Sin procesos vigentes», no un 100 % ficticio. Llegar al 100 % no finaliza automáticamente la OT.
- **Tiempo de asignación:** azul para el periodo transcurrido, rojo para intervalos de reparación y gris para el periodo por transcurrir. No representa horas productivas. Se muestra una línea general de la OT y una por cada asignación de equipo, identificando el proceso.

En la línea general, dos equipos dañados durante las mismas horas no duplican tiempo calendario. Las horas de cada equipo se muestran por separado; sumar esas horas-equipo es una medida distinta.

## Ejemplo práctico

Un molino está asignado desde el lunes a las 08:00 hasta el jueves a las 08:00: 72 horas de disponibilidad. El martes se daña a las 08:00 y vuelve a funcionar a las 14:00. Se registran esas seis horas y el motivo. El intervalo aparece en rojo en la asignación y en la línea general. El fin de asignación continúa siendo el jueves a las 08:00.

Las horas efectivas proceden del registro habitual de horas o del horómetro. Registrar seis horas de reparación **no resta otras seis horas al horómetro**, no cambia depreciación/electricidad y no genera un gasto automáticamente.

## Recorrido web

1. Abra una OT. Debajo de su encabezado aparecen el avance por procesos y la línea de tiempo.
2. Entre en **Reparaciones y plazos**. También hay accesos desde el detalle del proceso y la gestión de trabajo del equipo. El buscador del menú encuentra «reparaciones», «avería», «progreso» y «ampliar plazo».
3. En la asignación afectada, elija **Poner equipo en reparación** e indique si es **Interna · personal propio** o **Externa · enviado a taller**, el inicio, el fin opcional y el motivo de la falla. Si deja el fin vacío aparece **En reparación**.
4. Cuando el equipo vuelva a funcionar, abra **Terminar o corregir el intervalo**, indique el fin real, la **descripción de lo que se hizo** y confirme. El registro muestra el tiempo total entre inicio y fin; si sigue abierto, muestra el transcurrido al momento de consultar. Este total puede superar el plazo de asignación; la barra sigue mostrando únicamente la parte coincidente con el uso.
5. Si necesita más disponibilidad, utilice **Ampliar el plazo de esta asignación**. Es una decisión manual y se comprueba que el equipo no esté ocupado en otra asignación. No cambia automáticamente las fechas del proceso ni de otras asignaciones.
6. En la reparación, abra **Registrar costo vinculado a esta reparación**: **costo total y descripción del trabajo**, con categoría, moneda y fecha para su registro contable. No requiere desglose de repuestos o mano de obra: se guarda cantidad 1 e importe global. Es un movimiento normal del proceso y de su OT, con una referencia a la reparación. Se contabiliza una sola vez y no crea una salida de caja. El resumen suma los costos registrados por moneda. Para corregirlo o anularlo, abra **Costos del proceso**.

Desde **Administración → Equipos → Ver equipo → Procesos donde se usó**, el acceso **Poner en reparación / consultar** lleva directamente a la asignación elegida. El listado de equipos muestra **En reparación** cuando tiene un intervalo abierto. El estado activo/inactivo del catálogo se mantiene como una clasificación diferente. Para registrar una reparación en este flujo, el equipo debe tener una asignación a un proceso de OT.

Los registros anteriores no se clasifican automáticamente como internos o externos: muestran «Lugar de reparación sin especificar» hasta que se complete ese dato.

Puede haber varias reparaciones consecutivas de una asignación. No se admiten intervalos solapados del mismo uso, fechas futuras ni un inicio fuera de la asignación. El cierre real puede ser posterior al fin asignado: el rojo y las horas afectadas se limitan a la parte coincidente con la asignación.

Las asignaciones sin fecha de fin se representan hasta el momento del cálculo. Las reparaciones abiertas siguen acumulando tiempo únicamente al renovar la consulta del servidor, y se limitan al plazo asignado.

## Móvil, Online y Offline

Abra una OT: **Avance y tiempo de reparación → Ver reparaciones y plazos**. Allí puede registrar, terminar, corregir o anular un intervalo, proponer una ampliación y registrar su costo. Las fechas y horas se introducen en la zona horaria del taller, indicada en la pantalla.

### Online

Cada cambio se guarda primero en SQLite y se sube automáticamente mediante la misma cola idempotente de sincronización. Una respuesta confirmada permite renovar la consulta del servidor. Si la respuesta es incierta, el cambio se conserva y debe reintentarse con su misma clave, sin registrarlo de nuevo.

Las barras web y móvil consultan el servidor cada 30 segundos con la pantalla abierta en Online. La web pausa la consulta al ocultar la pestaña y la renueva al volver. El móvil evita renovar los formularios mientras están abiertos o guardando. En la web solo se reemplaza el resumen; los formularios mantienen su versión y sus datos escritos hasta que el usuario renueve la pantalla.

### Offline

- La barra conserva **exactamente el resultado y la fecha del cálculo descargado**; no se recalcula como resultado definitivo con los cambios locales.
- Las reparaciones y ampliaciones locales se superponen únicamente en los registros y formularios para poder continuar el trabajo. Por ejemplo, se puede registrar una falla sin fin y después cerrarla antes de subir.
- Los cambios pendientes aparecen en una lista separada. Una asignación creada Offline permite registrar su reparación, pero no inventa una barra confirmada.
- Una OT nueva Offline tampoco tiene porcentaje confirmado hasta sincronizar.
- Para renovar el progreso: **Menú → Sincronización → Subir datos → Descargar datos**. La descarga está protegida mientras existan operaciones pendientes.
- Después de subir se limpia la copia antigua; si sigue en modo Offline, debe descargar de nuevo antes de consultar el progreso.
- Una descarga anterior a esta funcionalidad debe renovarse para disponer de seguimiento.

### Conflictos

Las correcciones y anulaciones de reparaciones llevan una versión esperada. Las ampliaciones llevan el fin de asignación esperado. Si otro dispositivo cambió ese dato, el servidor rechaza la operación y revierte el lote completo; no sobrescribe silenciosamente el cambio central.

Si el servidor confirmó el rechazo y la reversión, puede **quitar los cambios locales de seguimiento de esa OT**, subir otros pendientes, descargar la versión actual y registrar la corrección revisada. Se quitan juntos los intervalos, ampliaciones y costos de seguimiento pendientes de esa OT para conservar sus dependencias. No se eliminan otras operaciones.

Si hubo un envío sin respuesta confirmada, no se permite quitarlo ni registrar otro cambio de seguimiento de esa OT. Reintente la subida de los mismos pendientes: las claves evitan duplicar reparaciones o gastos. Un rechazo posterior no demuestra que un envío anterior incierto nunca se aplicara.

## Permisos e historial

- Consultar avance: `ordenes_trabajo.view`.
- Ver asignaciones e intervalos: además `proceso_equipo.view`.
- Registrar, corregir, anular reparaciones o ampliar plazos: además `proceso_equipo.edit`.
- Registrar costos de reparación: además `movimientos_costo.create`; consultar sus importes requiere `movimientos_costo.view`.
- OT/proceso cerrado: no admite reparaciones nuevas, ampliaciones ni costos nuevos. Los intervalos existentes se pueden terminar o corregir. Reabra el registro para continuar trabajo nuevo.
- Una reparación con costos vigentes no puede anularse hasta revisar esos costos.
- No se elimina ni se cambia el equipo de una asignación con historial de reparaciones; los cambios de fechas deben conservar los inicios registrados.

## API y actualización

Migraciones: `2026_10_17_000001_seguimiento_reparaciones.php` crea `equipo_reparaciones` y añade `movimientos_costos.reparacion_id`; `2026_10_17_000002_modalidad_reparacion.php` añade modalidad interna/externa y trabajo realizado. Se reutilizan los costos y las operaciones idempotentes existentes.

Rutas autenticadas bajo `/api/v1/orders/{id}/tracking`:

| Método/ruta | Contenido |
| --- | --- |
| `GET /` | Avance, líneas de tiempo, asignaciones, reparaciones y opciones según permisos |
| `POST /repairs` | `assignment_id`, `started_at`, `ended_at` opcional, `reason`, `repair_location` (`interna`/`externa`), `work_description` opcional |
| `PUT /repairs/{id}` | Inicio, fin opcional, motivo, modalidad, trabajo realizado y `expected_version` |
| `DELETE /repairs/{id}` | `expected_version`; anulación lógica |
| `PUT /assignments/{id}/deadline` | `ended_at`, `expected_end` presente, nullable |
| `POST /repairs/{id}/costs` | `total_cost`, `descripcion`, `categoria_costo_id`, `fecha`, `moneda_id`. Admite `costo_unitario` de clientes anteriores; se fija cantidad 1. |

El detalle de OT y el snapshot incluyen `tracking`. El snapshot incluye la zona horaria del taller. Los tipos nuevos de cola son `repair.create`, `repair.update`, `repair.delete`, `repair_cost.create` y `equipment.extend`. Se resuelven IDs temporales de OT, proceso, asignación y reparación en el orden de la cola. Las horas de reparación se redondean a cuatro decimales; los tramos de barra son porcentajes del periodo correspondiente.

Cada reparación incluye `repair_location`, `work_description`, `total_hours` (duración completa) y `hours_in_assignment` (coincidencia con el uso). Los clientes anteriores pueden omitir los nuevos campos sin borrar la modalidad o el trabajo existente. El móvil conserva estos campos en SQLite y en operaciones pendientes; la barra mantiene la referencia descargada hasta sincronizar.

Pruebas: `SeguimientoReparacionesTest` en backend y `SeguimientoRepositoryTest` en el módulo compartido móvil.
