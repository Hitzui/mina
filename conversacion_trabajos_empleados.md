# Conversación --- Trabajos de empleados

> Exportación del contenido de la conversación disponible en el contexto
> actual. Las partes que no están disponibles literalmente no pueden
> reconstruirse palabra por palabra.

## Estado del proyecto

Se está desarrollando un sistema en Laravel para gestionar procesos,
órdenes de trabajo y trabajos realizados por empleados.

Módulos indicados como terminados o en progreso:

1.  Etapas --- listo
2.  Órdenes de trabajo --- ingreso, edición y show listos
3.  Procesos de la orden --- CRUD, pendientes algunas validaciones
4.  Categorías de costos --- CRUD listo
5.  Tipos de pago de empleado --- CRUD listo
6.  Pagos/tarifas de empleados --- CRUD listo
7.  Trabajos de empleados --- módulo actual
8.  Productos
9.  Proveedores
10. Compras
11. Inventario
12. Tipos de producción
13. Producciones
14. Recuperaciones
15. Monedas
16. Tipos de cambio
17. Precios de oro
18. Valoraciones de oro
19. Tipos de participación
20. Acuerdos
21. Liquidaciones
22. Tipos de ingreso
23. Ingresos
24. Cajas
25. Métodos de pago
26. Cobros
27. Detalle de cobros
28. Cierres

------------------------------------------------------------------------

## Trabajos de empleados

La tabla `mina.trabajos_empleados` contiene:

``` sql
CREATE TABLE mina.trabajos_empleados (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  empleado_id bigint(20) UNSIGNED NOT NULL,
  orden_trabajo_id bigint(20) UNSIGNED DEFAULT NULL,
  proceso_orden_id bigint(20) UNSIGNED DEFAULT NULL,
  tipo_pago_id bigint(20) UNSIGNED NOT NULL,
  fecha date NOT NULL,
  hora_inicio time DEFAULT NULL,
  hora_fin time DEFAULT NULL,
  descripcion varchar(255) DEFAULT NULL,
  cantidad decimal(10, 2) NOT NULL DEFAULT 0.00,
  tarifa decimal(14, 2) NOT NULL DEFAULT 0.00,
  total decimal(14, 2) NOT NULL DEFAULT 0.00,
  tarifa_nio decimal(14, 4) DEFAULT NULL,
  total_nio decimal(14, 2) DEFAULT NULL,
  moneda_id bigint(20) UNSIGNED NOT NULL,
  observaciones text DEFAULT NULL,
  created_at timestamp NULL DEFAULT NULL,
  updated_at timestamp NULL DEFAULT NULL,
  deleted_at timestamp NULL DEFAULT NULL,
  unidad varchar(20) NOT NULL DEFAULT 'hora',
  PRIMARY KEY (id)
);
```

Relaciones:

-   `empleado_id` → `empleados.id`
-   `moneda_id` → `monedas.id`
-   `orden_trabajo_id` → `ordenes_trabajo.id`, con `ON DELETE SET NULL`
-   `proceso_orden_id` → `procesos_orden.id`, con `ON DELETE SET NULL`
-   `tipo_pago_id` → `tipos_pago_empleado.id`

Las vistas están ubicadas en:

``` text
resources/views/procesos/ordenes_trabajo/trabajos_empleados/
├── _action.blade.php
├── _form.blade.php
├── _modal_form.blade.php
├── create.blade.php
├── edit.blade.php
├── index.blade.php
└── show.blade.php
```

El JavaScript está en:

``` text
public/js/ordenes_trabajo/trabajo_empleados.js
```

------------------------------------------------------------------------

## Rutas actuales

``` text
GET|HEAD        procesos/ordenes-trabajo/{ordenTrabajo}/trabajos-empleados
POST            procesos/ordenes-trabajo/{ordenTrabajo}/trabajos-empleados
GET|HEAD        procesos/ordenes-trabajo/{ordenTrabajo}/trabajos-empleados/create
GET|HEAD        procesos/ordenes-trabajo/{ordenTrabajo}/trabajos-empleados/tarifa
GET|HEAD        procesos/ordenes-trabajo/{ordenTrabajo}/trabajos-empleados/{trabajos_empleado}
PUT|PATCH       procesos/ordenes-trabajo/{ordenTrabajo}/trabajos-empleados/{trabajos_empleado}
DELETE          procesos/ordenes-trabajo/{ordenTrabajo}/trabajos-empleados/{trabajos_empleado}
GET|HEAD        procesos/ordenes-trabajo/{ordenTrabajo}/trabajos-empleados/{trabajos_empleado}/edit
```

------------------------------------------------------------------------

## Modal de trabajo

El formulario se abre como modal.

Campos principales:

-   Empleado
-   Fecha
-   Tipo de pago
-   Proceso
-   Hora inicio
-   Hora fin
-   Cantidad
-   Unidad
-   Tarifa
-   Total
-   Tarifa NIO
-   Descripción
-   Observaciones

El empleado no se selecciona mediante un simple `<select>`. Se abre otro
modal con DataTable y checkbox para seleccionar un empleado.

Ese modal está en:

``` text
admin/empleados/_modal_empleado.blade.php
```

porque se considera reutilizable para otros módulos.

Se utiliza Flatpickr para fecha y hora.

------------------------------------------------------------------------

## Error de POST solucionado

Inicialmente el formulario enviaba el POST a:

``` text
procesos/ordenes-trabajo/1
```

en lugar de:

``` text
procesos/ordenes-trabajo/1/trabajos-empleados
```

Se solucionó configurando el `action` del formulario mediante
`data-store-url`:

``` javascript
let $formulario = $('#formTrabajoEmpleado');

$('#btnNuevoTrabajoEmpleado').on('click', function () {
    limpiarFormulario();
    $('#modalTrabajoEmpleadoLabel').text('Nuevo trabajo de empleado');
    $formulario.attr('action', $formulario.data('store-url'));
    $formulario.find('input[name="_method"]').remove();
    modal?.show();
});
```

Después de esto el registro guardó correctamente.

------------------------------------------------------------------------

## Tarifa vigente

El método `tarifaVigente()` busca la tarifa vigente de un empleado
según:

-   empleado
-   tipo de pago
-   fecha

La consulta utiliza `EmpleadosPago` y la moneda relacionada.

Conceptualmente:

``` text
Empleado + Tipo de pago + Fecha
        ↓
Buscar tarifa vigente
        ↓
Tarifa + moneda
```

Si no existe tarifa vigente, devuelve HTTP 404 con un mensaje indicando
que no existe una tarifa vigente.

------------------------------------------------------------------------

## Conversión a córdobas

Se acordó que:

-   `tarifa` es la tarifa en la moneda original.
-   `tarifa_nio` es la tarifa convertida a córdobas.
-   `tipo_cambio` es el tipo de cambio utilizado.
-   `total` es el total en la moneda original.
-   `total_nio` es el total convertido a córdobas.

Si la tarifa ya está en córdobas:

``` text
tipo_cambio = 1
```

Si la tarifa está en otra moneda, se busca el tipo de cambio
correspondiente.

También se acordó que si no existe tipo de cambio, se puede utilizar
temporalmente `1` para evitar que el sistema falle, pero se debe mostrar
una alerta indicando que no existe tipo de cambio.

------------------------------------------------------------------------

## Edición

La edición abre el mismo modal del formulario.

Al hacer clic en editar:

1.  Se consulta el registro.
2.  Se cargan sus datos.
3.  Se cambia el título a "Editar trabajo de empleado".
4.  Se cambia el `action` del formulario.
5.  Se agrega `_method=PUT`.

La edición ya funciona y actualiza correctamente.

------------------------------------------------------------------------

## Show

El show también se convirtió en modal.

El botón de ver abre un modal con los datos del trabajo sin navegar a
una página independiente.

------------------------------------------------------------------------

## Delete

El usuario decidió utilizar Laravel SweetAlert para confirmar la
eliminación.

La idea es utilizar atributos como:

``` html
<a href="{{ route('posts.destroy', $post) }}"
   data-confirm-delete
   data-confirm-title="Delete..."
   data-confirm-text="This cannot be undone."
   data-confirm-button="Yes, delete it">
    Delete Post
</a>
```

El delete todavía está pendiente.

------------------------------------------------------------------------

# Problema de lógica detectado

Se detectó que el cálculo actual no sirve para todos los tipos de pago.

Ejemplo:

``` text
Tipo de pago: Por trabajo
Cantidad: 8 horas
Tarifa: C$700
```

El cálculo actual producía:

``` text
8 × 700 = C$5,600
```

pero lo correcto es:

``` text
Total = C$700
```

Las 8 horas se registran para saber cuánto tiempo tomó realizar el
trabajo, pero no multiplican el pago.

En cambio, para un empleado que cobra por hora:

``` text
Cantidad: 8 horas
Tarifa: C$100/hora
Total: C$800
```

Por producción:

``` text
Cantidad: 25 unidades
Tarifa: C$20/unidad
Total: C$500
```

------------------------------------------------------------------------

# Tipos de pago de empleado

La tabla actual es:

``` sql
CREATE TABLE mina.tipos_pago_empleado (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre varchar(50) NOT NULL,
  codigo varchar(20) DEFAULT NULL,
  descripcion varchar(255) DEFAULT NULL,
  estado tinyint(1) NOT NULL DEFAULT 1,
  created_at timestamp NULL DEFAULT NULL,
  updated_at timestamp NULL DEFAULT NULL,
  deleted_at timestamp NULL DEFAULT NULL,
  PRIMARY KEY (id)
)
ENGINE = INNODB,
AUTO_INCREMENT = 8,
AVG_ROW_LENGTH = 2730,
CHARACTER SET utf8mb4,
COLLATE utf8mb4_general_ci;
```

Los tipos existentes incluyen:

``` text
1  Fijo
2  Por día
3  Por jornada
4  Por trabajo
5  Por hora
6  Por producción
7  test
```

Se agregó el campo `codigo`.

------------------------------------------------------------------------

# Nueva propuesta: metodo_calculo

Se decidió no usar un `switch` basado en el nombre o código del tipo de
pago.

La razón es que si mañana el usuario crea:

``` text
Por tarea
```

no debería ser necesario modificar el código PHP.

La propuesta es agregar:

``` text
metodo_calculo
```

a `tipos_pago_empleado`.

La estructura conceptual quedaría:

``` text
id
nombre
codigo
metodo_calculo
descripcion
estado
created_at
updated_at
deleted_at
```

SQL propuesto:

``` sql
ALTER TABLE mina.tipos_pago_empleado
ADD COLUMN metodo_calculo VARCHAR(30) NOT NULL DEFAULT 'CANTIDAD_X_TARIFA'
AFTER codigo;
```

Para los registros existentes:

``` sql
UPDATE mina.tipos_pago_empleado
SET metodo_calculo = 'TARIFA'
WHERE codigo IN ('FIJO', 'TRABAJO');

UPDATE mina.tipos_pago_empleado
SET metodo_calculo = 'CANTIDAD_X_TARIFA'
WHERE codigo IN ('DIA', 'JORNADA', 'HORA', 'PRODUCCION');
```

------------------------------------------------------------------------

## Métodos de cálculo iniciales

Se decidió comenzar únicamente con dos reglas:

### TARIFA

La tarifa representa el pago completo:

``` text
total = tarifa
```

Ejemplo:

``` text
8 horas
tarifa = C$700
total = C$700
```

### CANTIDAD_X_TARIFA

La cantidad multiplica la tarifa:

``` text
total = cantidad × tarifa
```

Ejemplo:

``` text
8 horas
tarifa = C$100
total = C$800
```

La misma regla debe aplicarse a `total_nio`.

------------------------------------------------------------------------

# Por qué no crear una tabla de fórmulas todavía

Para las necesidades actuales solamente existen dos reglas claras:

``` text
TARIFA
CANTIDAD_X_TARIFA
```

Crear un motor de fórmulas o una tabla adicional sería innecesario por
ahora.

Si en el futuro aparecen reglas más complejas, por ejemplo:

``` text
PORCENTAJE
ESCALA
BONIFICACION
```

se puede ampliar el mecanismo.

Para nuevos tipos de pago sencillos, el usuario podrá seleccionar la
regla desde el CRUD sin modificar PHP.

Ejemplo:

``` text
Nombre: Por tarea
Código: TAREA
Método de cálculo: TARIFA
```

o:

``` text
Nombre: Por tonelada
Código: TONELADA
Método de cálculo: CANTIDAD_X_TARIFA
```

------------------------------------------------------------------------

# Proceso de la orden

No se considera necesario agregar otro campo.

`trabajos_empleados` ya tiene:

``` text
proceso_orden_id
```

Este campo indica en qué proceso de la orden se realizó el trabajo.

Puede contener un proceso concreto o ser `NULL` para trabajos generales
de la orden.

Conceptualmente:

``` text
ORDEN DE TRABAJO
│
├── Proceso 1
│   ├── Trabajo empleado
│   └── Trabajo empleado
│
├── Proceso 2
│   └── Trabajo empleado
│
└── Trabajo general
    └── Trabajo empleado
```

------------------------------------------------------------------------

# Próximos pasos

1.  Agregar `metodo_calculo` a `tipos_pago_empleado`.
2.  Actualizar los registros existentes.
3.  Agregar `metodo_calculo` al modelo.
4.  Modificar el CRUD de tipos de pago.
5.  Agregar selector de método de cálculo al formulario.
6.  Validar `metodo_calculo` en `store()` y `update()`.
7.  Modificar `trabajos_empleados.store()` para usar `metodo_calculo`.
8.  Modificar `trabajos_empleados.update()` para usar `metodo_calculo`.
9.  Modificar el JavaScript para mostrar el total correcto.
10. Implementar Delete con SweetAlert.

## Estado al momento de la exportación

-   CRUD de tipos de pago: listo.
-   CRUD de pagos/tarifas de empleados: listo.
-   DataTable de trabajos de empleados: funcionando.
-   Crear trabajo mediante modal: funcionando.
-   Selección de empleado mediante modal/DataTable: funcionando.
-   Tarifa vigente: funcionando.
-   Conversión a NIO: implementada en la lógica actual.
-   Editar trabajo mediante modal: funcionando.
-   Show mediante modal: funcionando.
-   Delete: pendiente.
-   Corrección del cálculo según `metodo_calculo`: pendiente.
-   `metodo_calculo` en `tipos_pago_empleado`: propuesta acordada para
    implementar.
