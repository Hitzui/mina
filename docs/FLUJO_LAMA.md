# Lama: servicio original y recuperación posterior

## Regla de trabajo

La OT original registra el servicio prestado al cliente y su precio. El acuerdo indica, de forma independiente:

- Quién conserva el material sobrante: empresa o cliente.
- Qué porcentaje del oro obtenido **de esa lama** corresponde a la empresa; el resto es del cliente.

Conservar el material no significa necesariamente quedarse con todo su oro. Por ejemplo, la empresa puede conservarlo y acordar 70% del oro para la empresa y 30% para el cliente. Este reparto no modifica el del oro del trabajo original.

Las cantidades de lama se registran como **estimadas**. Se trabaja únicamente con **lotes completos**: un lote alimenta una OT de tratamiento; no se admiten mezclas ni retiros parciales. Una OT original puede producir varios lotes físicos identificados por separado.

## Preparación

Se necesitan una OT original no cancelada, un acuerdo activo con las condiciones de la lama, una unidad de peso y la ubicación física del material. Para valorar después el oro, deben existir precios de oro y, cuando corresponda, tipos de cambio para la fecha.

Se usan los permisos existentes: ver OT para consultar, editar OT para registrar/corregir/anular lotes, crear y editar OT para iniciar tratamientos, y permisos de recuperaciones y valoraciones para registrar el resultado. La consulta financiera completa exige acceso a ingresos, recuperaciones, valoraciones y los componentes de costos.

## Pasos en la web

1. Abra la OT de servicio y entre a **Acuerdos de participación y lama**. Puede mantener la tarifa de molienda y definir además quién conserva la lama y su reparto de oro. Porcentaje empresa 100 significa todo para la empresa; 0 significa todo para el cliente.
2. Abra **Lama y recuperación posterior**. Registre cantidad estimada, unidad, fecha, ubicación y acuerdo. Puede registrar los sobrantes de un servicio finalizado.
3. Revise el lote antes de tratarlo. Puede corregir cantidad y ubicación, o anular un registro equivocado. Para cambiar unidad, procedencia o acuerdo, anule el lote sin tratamiento y regístrelo correctamente.
4. Si la empresa conserva el material, pulse **Iniciar tratamiento del lote completo** e indique la fecha. Se crea la OT `OT-LAMA-…`, vinculada al lote, con cantidad estimada y reparto heredados. Si el oro es 100% de la empresa, no se crea un cliente ficticio; si hay participación del cliente, se conserva el cliente original.
5. Abra esa nueva OT y registre sus procesos, personal, equipos, horas, insumos y demás costos utilizando las pantallas habituales.
6. Registre el oro recuperado desde la pantalla de lama de la **OT de tratamiento**, antes de finalizarla. Admite varias recuperaciones del mismo lote. Cero gramos es un resultado válido. La pureza es opcional y se ingresa como fracción: `0.80` representa 80%.
7. Valore cada recuperación indicando fecha y moneda del precio. El precio se selecciona con las reglas históricas habituales; se guarda la valoración en moneda base. No se genera un ingreso ni un cobro.
8. Revise el resultado del tratamiento y, desde el origen, la consulta conjunta. El acuerdo heredado también está disponible para las liquidaciones habituales del oro.

El menú **Órdenes de Trabajo → Lama y tratamientos** reúne los lotes y sus procedencias. La lama conservada por el cliente se identifica, pero no figura como saldo disponible en la empresa.

## Pasos en móvil

Desde el detalle de una OT, pulse **Lama y recuperación posterior**. La pantalla explica los pasos y permite abrir acuerdos, registrar lotes, corregirlos o anularlos antes del tratamiento, crear la OT vinculada, abrir sus procesos, registrar recuperaciones y valorar el oro, según permisos.

- **Online:** gestión de lotes, tratamientos, recuperaciones y valoraciones.
- **Offline:** consulta de la información incluida en la última descarga, identificada como copia. Los botones de modificación de lama se deshabilitan. Una descarga anterior a esta funcionalidad solicita actualizarla.
- Los acuerdos y el registro operativo compatible de una OT de tratamiento ya descargada mantienen el mecanismo habitual de pendientes. Esos cambios no recalculan la referencia económica de la copia de lama; suba los pendientes y actualice la descarga para consultarla al día.

## Cómo leer los resultados

**Tratamiento:** valor del oro que corresponde a la empresa menos costos de ese tratamiento.

**Consulta conjunta:** ingresos registrados del servicio original + valor del oro de la empresa de los tratamientos vinculados − costos originales − costos de esos tratamientos.

Los gastos generales se suman una sola vez por OT; su distribución entre procesos continúa siendo informativa. No se traslada el costo del servicio a la OT de tratamiento ni se cuenta otra vez la venta del mismo oro además de su valoración. Los costos del tratamiento se descuentan completos; el reparto configurado corresponde al oro, no a un reparto de gastos.

La cifra es una **referencia económica**, no saldo de caja, cobro ni utilidad neta contable. Sigue siendo provisional mientras se registra trabajo. Si faltan equivalentes NIO, horas/confirmaciones de insumos de equipos, recuperaciones o valoraciones, el resultado permanece pendiente y se muestran los importes conocidos. Los lotes propios disponibles pendientes de tratar también dejan pendiente el resultado conjunto.

Los gramos se reparten según los porcentajes sin aplicar nuevamente la pureza. La pureza interviene al valorar el oro. Si se corrigen gramos o pureza de una recuperación, su equivalente de valoración se marca pendiente para evitar mezclar un valor anterior con gramos nuevos; puede recalcularla desde la misma pantalla con permiso para editar valoraciones.

## Conservación de la procedencia

El lote guarda las condiciones vigentes al registrarlo. Los cambios posteriores en el acuerdo original aplican a nuevos lotes. Una vez iniciado el tratamiento, se conservan cantidad, procedencia y reparto histórico, y el lote no puede eliminarse ni reutilizarse. Una OT de tratamiento cancelada también conserva el vínculo completo; no libera automáticamente ese material para otra OT.

Los lotes y sus tratamientos no representan químicos ni productos ordinarios del almacén. Los químicos y combustibles usados para tratar la lama sí se gestionan por los mecanismos habituales de materiales y equipos.

## API

Base: `/api/v1/orders/{ordenTrabajo}/lama`, con token Sanctum y permisos existentes.

| Método | Sufijo | Operación |
|---|---|---|
| GET | `/` | Pasos, capacidades, lotes, recuperaciones y referencia económica autorizada |
| POST | `/lotes` | Registrar lote: `acuerdo_id`, `fecha`, `cantidad_estimada`, `unidad_id`, `ubicacion`, `observaciones` opcional |
| PUT | `/lotes/{lote}` | Corregir cantidad estimada, ubicación y observaciones antes del tratamiento |
| DELETE | `/lotes/{lote}` | Anular lote sin tratamiento, conservando borrado lógico |
| POST | `/lotes/{lote}/tratamiento` | Iniciar lote completo con `fecha`; devuelve datos de la nueva OT |
| POST | `/recuperaciones` | Registrar `fecha`, `gramos`, `pureza` opcional en la OT de tratamiento |
| POST | `/valoraciones` | Valorar/recalcular `recuperacion_id`, `fecha`, `precio_moneda`; exige crear o editar según exista valoración |

Los acuerdos web y API añaden `lama_destino` (`sin_definir`, `empresa`, `cliente`) y `lama_porcentaje_empresa` (0 a 100). El detalle y snapshot añaden `lama`; las órdenes identifican `work_type`. Las operaciones específicas de lama no se encolan Offline.
