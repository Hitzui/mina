# Costeo de equipos

## Configuración

En Administración → Equipos se configuran componentes independientes:

- **Depreciación:** valor de adquisición, residual y vida útil en meses.
- **Electricidad comprada:** potencia en kW y tarifa general del taller (NIO/kWh).
- **Recursos de almacén:** productos y consumo de referencia por hora, expresado en la unidad del producto.

Ejemplos: planta generadora con depreciación y diésel; molino o bomba eléctrica con electricidad y depreciación opcional. La electricidad generada por la planta no se cobra además con la tarifa de la distribuidora.

## Uso en un proceso

Las fechas reservan el equipo y evitan asignaciones simultáneas. Las **horas efectivas** se ingresan como total o mediante dos lecturas de horómetro. Las horas no se deducen del periodo asignado.

- Depreciación = `(adquisición − residual) / (vida útil en meses × 30 × 24) × horas efectivas`, limitada al valor pendiente de depreciar. Conserva el prorrateo de vida útil existente.
- Electricidad estimada = `potencia kW × horas efectivas × tarifa NIO/kWh`.
- Insumos = cantidad consumida de cada entrega × costo unitario histórico de esa entrega.
- Costo de uso = depreciación + electricidad estimada + insumos consumidos.

Sin horas, los componentes por tiempo quedan pendientes. Una estimación de combustible es informativa y nunca se añade al consumo registrado.

Desde las acciones del equipo en el proceso, abra **Trabajo efectivo y consumos**:

1. **Entregar:** retira existencias del almacén al costo promedio y las mantiene en custodia del equipo. Conserva la referencia al uso que originó la entrega.
2. **Consumir:** descuenta ese saldo y atribuye su costo al uso y al proceso seleccionados. No produce otra salida de almacén.
3. **Devolver:** reintegra el sobrante al almacén con el valor de la entrega original.
4. **Anular consumo:** corrige el costo y devuelve la cantidad al saldo del equipo, conservando el historial.

El sobrante puede utilizarse en otro proceso del mismo equipo. La ficha del equipo muestra sus saldos. Las entregas y devoluciones no se editan desde el kardex general.

## Historial y móvil

Cada uso conserva su potencia, tarifa, tasa de depreciación y referencias de recursos. Corregir observaciones o disponibilidad no cambia los costos registrados. Los usos anteriores a esta integración conservan sus importes y se identifican como históricos hasta registrar explícitamente sus horas efectivas.

En móvil se pueden asignar equipos y horas en Offline con estimaciones provisionales. La sincronización valida disponibilidad y calcula con la configuración del servidor. Entregas, consumos, devoluciones y correcciones de usos existentes requieren Online.

Los totales de proceso, OT, distribución informativa de gastos generales, panel y reporte de órdenes incluyen los insumos una sola vez dentro del uso de equipos.
