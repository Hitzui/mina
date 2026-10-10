{{--
    La ficha de un cobro.

    ===========================================================================
    POR QUÉ ESTÁ ESTRUCTURADA EN ESTE ORDEN
    ===========================================================================

    Primero de quien es el cobro y cuanto es, y despues el desglose. Al revés —el
    desglose primero y el total al final— obliga a recorrer veinte lineas para
    llegar a la cifra que se entro a mirar.

    ===========================================================================
    Y EL AVISO DE LO QUE YA ESTA COBRADO, QUE ES LO QUE HACE FALTA
    ===========================================================================

    Cada linea dice cuanto lleva cobrado su ingreso, contando todos los cobros y
    sin contar este. Y cuando ese ingreso esta pagado del todo, la linea avisa.

    Ese aviso no es un adorno: es la unica forma de que quien mira una ficha se
    de cuenta de que hay un cobro anterior que el taller todavia no ha revocado.
    Sin el, un ingreso aparece con dos lineas de cobro y las dos parecen
    correctas, y el saldo del cliente sale mal sin que nada lo diga.
--}}
<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Cobro' }}
    </x-slot:pageTitle>

    <x-breadcrumb :items="$breadcrumbs"/>

    <x-slot:headerFiles>
    </x-slot>

    <div class="row layout-top-spacing">

        <div class="col-xl-12 col-lg-12 col-sm-12">

            {{-- ============================================================
                 LA CABECERA
                 ============================================================ --}}

            <div class="widget-content widget-content-area br-8 mb-3">

                <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">

                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                            <h4 class="mb-0">
                                <i class="bi bi-cash-coin me-1"></i>
                                {{ $cobro->codigo }}
                            </h4>
                            <span class="badge {{ $cobro->estadoClase() }}">
                                {{ $cobro->estadoTexto() }}
                            </span>
                        </div>

                        <p class="text-muted mb-0">
                            Cobro de <strong>{{ $cobro->cliente?->nombre ?? 'Cliente eliminado' }}</strong>
                            del {{ $cobro->fecha->format('d/m/Y') }}.
                        </p>
                    </div>

                    <div class="d-flex gap-2 flex-wrap">
                        @can('cobros.edit')
                            @if (! $cobro->esFinalizado() && ! $cobro->estaCancelado())
                                <form
                                    method="POST"
                                    action="{{ route('procesos.cobros.finalizar', $cobro) }}"
                                    class="d-inline"
                                    data-confirm
                                    data-confirm-title="¿Confirmar que se recibió el pago?"
                                    data-confirm-text="El cobro quedará Finalizado y sus líneas empezarán a contar como dinero recibido."
                                    data-confirm-button="Sí, confirmar pago"
                                >
                                    @csrf
                                    <button type="submit" class="btn btn-success">
                                        <i class="bi bi-check-circle me-1"></i>
                                        Confirmar pago
                                    </button>
                                </form>
                            @endif
                        @endcan

                        <a href="{{ route('procesos.cobros.edit', $cobro) }}" class="btn btn-light">
                            <i class="bi bi-pencil me-1"></i>
                            Editar
                        </a>

                        <form
                            method="POST"
                            action="{{ route('procesos.cobros.destroy', $cobro) }}"
                            class="d-inline"
                            data-confirm-delete
                            data-confirm-title="Eliminar el cobro {{ $cobro->codigo }}?"
                            @if ($cobro->esFinalizado())
                                data-confirm-text="Este cobro está finalizado: sus {{ $cobro->cantidad_lineas }} {{ $cobro->cantidad_lineas === 1 ? 'línea' : 'líneas' }} ya cuentan como cobrado. Si lo elimina, esos ingresos volverán a salir como pendientes y el papel que respalda ese dinero desaparecerá. El dinero no sale de la caja, pero el saldo del cliente queda descuadrado. Esto no se puede recuperar."
                            @elseif ($cobro->estaCancelado())
                                data-confirm-text="Este cobro ya está cancelado, así que sus líneas no cuentan como cobrado. Eliminarlo quita el documento. Esto no se puede recuperar."
                            @else
                                data-confirm-text="Este cobro está pendiente, así que todavía no cuenta como dinero recibido. Sus líneas quedarán dadas de baja y el cobro desaparecerá del listado. Esto no se puede recuperar."
                            @endif
                            data-confirm-button="Sí, eliminar"
                        >
                            @csrf
                            @method('DELETE')

                            <button type="submit" class="btn btn-light">
                                <i class="bi bi-trash me-1"></i>
                                Eliminar
                            </button>
                        </form>

                        <a href="{{ route('procesos.cobros.index') }}" class="btn btn-light">
                            <i class="bi bi-arrow-left me-1"></i>
                            Volver
                        </a>
                    </div>

                </div>

                {{--
                    Y el total, en grande y solo.

                    Es el numero que se entro a mirar y no esta en la cabecera de la
                    tabla de cobros con la misma prominence: aqui es la respuesta a la
                    pregunta que se hace al abrir la ficha.
                --}}
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <div class="p-3 rounded-3" style="background: var(--bs-light);">
                            <div class="text-secondary small mb-1">Total del cobro</div>
                            <div class="fs-3 fw-bold">
                                {{ number_format($cobro->total(), 2) }}
                            </div>
                            <div class="text-secondary small">
                                {{ $cobro->moneda?->nombre ?? 'Moneda eliminada' }}
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-md-2">
                        <div class="text-secondary small mb-1">Caja</div>
                        <div class="fw-semibold">
                            {{ $cobro->caja?->nombre ?? 'Caja eliminada' }}
                        </div>
                        @if ($cobro->caja && ! $cobro->caja->estado)
                            <span class="badge bg-secondary"
                                  title="Esta caja está desactivada ahora. El cobro se hizo con ella activa.">
                                inactiva
                            </span>
                        @endif
                    </div>

                    <div class="col-6 col-md-2">
                        <div class="text-secondary small mb-1">Método</div>
                        <div class="fw-semibold">
                            {{ $cobro->metodo_pago?->nombre ?? 'Método eliminado' }}
                        </div>
                        @if ($cobro->metodo_pago && ! $cobro->metodo_pago->estado)
                            <span class="badge bg-secondary"
                                  title="Este método está desactivado ahora.">
                                inactivo
                            </span>
                        @endif
                    </div>

                    <div class="col-6 col-md-2">
                        <div class="text-secondary small mb-1">Referencia</div>
                        <div class="fw-semibold">
                            {{ $cobro->referencia ?: '—' }}
                        </div>
                    </div>

                    <div class="col-6 col-md-2">
                        <div class="text-secondary small mb-1">Líneas</div>
                        <div class="fw-semibold">
                            {{ $cobro->cantidad_lineas }}
                        </div>
                    </div>
                </div>

                @if ($cobro->observaciones)
                    <div class="mt-3">
                        <div class="text-secondary small mb-1">Observaciones</div>
                        <p class="mb-0 text-break">{{ $cobro->observaciones }}</p>
                    </div>
                @endif

            </div>

            {{-- ============================================================
                 EL DESGLOSE
                 ============================================================ --}}

            <div class="widget-content widget-content-area br-8">

                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div>
                        <h5 class="mb-1">¿A qué se imputó este dinero?</h5>
                        <p class="text-muted mb-0 small">
                            Cada línea dice cuánto de este cobro va a un ingreso, y
                            cuánto lleva cobrado ese ingreso contando todos los
                            cobros.
                        </p>
                    </div>
                </div>

                {{--
                    Y el aviso del estado, que es lo que un estado significa y una
                    etiqueta de color no dice.

                    Cancelado es el unico de los cuatro que cambia lo que cuenta: sus
                    lineas dejan de ser dinero cobrado. Los otros tres no lo hacen,
                    porque Pendiente y En proceso son estados de lo que esta por
                    pasar, no de lo que ya paso.
                --}}
                @if ($cobro->estaCancelado())
                    <div class="alert alert-danger" role="alert" style="font-size: .85rem;">
                        <i class="bi bi-x-octagon-fill me-1"></i>
                        Este cobro está <strong>cancelado</strong>. Sus líneas
                        <strong>no cuentan como dinero cobrado</strong>, y por eso no
                        salen en el total de cobrado del cliente. El importe de abajo
                        es lo que se anotó, no lo que se recibió.
                    </div>
                @elseif ($cobro->esFinalizado())
                    <div class="alert alert-success" role="alert" style="font-size: .85rem;">
                        <i class="bi bi-check-circle-fill me-1"></i>
                        Cobro <strong>finalizado</strong>: el dinero está confirmado
                        en la caja y estas líneas cuentan como cobrado.
                    </div>
                @else
                    <div class="alert alert-warning" role="alert" style="font-size: .85rem;">
                        <i class="bi bi-clock-fill me-1"></i>
                        Este cobro está <strong>{{ strtolower($cobro->estadoTexto()) }}</strong>:
                        el dinero todavía <strong>no está confirmado en la caja</strong>.
                        Póngalo en Finalizada cuando lo haya recibido; hasta entonces
                        estas líneas no cuentan como cobrado.
                    </div>
                @endif

                <div class="table-responsive">

                    <table class="table align-middle" id="tablaDetalle">

                        <thead class="table-light">
                            <tr>
                                <th style="width: 32%">Ingreso</th>
                                <th style="width: 14%">Orden</th>
                                <th style="width: 14%" class="text-end">Total del ingreso</th>
                                <th style="width: 16%" class="text-end">Importe de esta línea</th>
                                <th style="width: 24%" class="text-end">Estado de ese ingreso</th>
                            </tr>
                        </thead>

                        <tbody>

                            @foreach ($cobro->detalles as $linea)
                                @php
                                    $ingreso = $linea->ingreso;

                                    /*
                                     * Lo que lleva cobrado este ingreso, por todo:
                                     * los OTROS cobros mas esta misma linea.
                                     *
                                     * Y el "mas esta misma linea" no es un descuido de
                                     * suma: `cobradoPorIngreso` recibe el id de este
                                     * cobro para excluirlo, y la exclusion es
                                     * correcta —sin ella, la linea se veria como
                                     * duplicada—. Lo que hay que hacer es volver a
                                     * sumar la linea despues de excluida. Sin ese
                                     * "mas", ningun ingreso saldria nunca como pagado
                                     * cuando lo paga este cobro, que es justo el caso
                                     * normal.
                                     */
                                    $deOtros = $cobradoPorIngreso[(int) $linea->ingreso_id] ?? 0.0;
                                    $totalCobrado = $deOtros + (float) $linea->monto;

                                    /*
                                     * Y la comparacion solo existe si las monedas
                                     * son la misma. Con monedas distintas, el importe
                                     * de la linea va en la del cobro y el total del
                                     * ingreso en la suya, y no hay conversion
                                     * guardada en ninguna parte: comparar los dos
                                     * daria un numero, pero no diria nada.
                                     */
                                    $mismaMoneda = $ingreso
                                        && (int) $ingreso->moneda_id === (int) $cobro->moneda_id;

                                    $pasado = $mismaMoneda
                                        && $totalCobrado > (float) $ingreso->total + 0.005;

                                    $pagado = $mismaMoneda
                                        && ! $pasado
                                        && $totalCobrado >= (float) $ingreso->total - 0.005;
                                @endphp

                                <tr>
                                    <td>
                                        <div class="fw-semibold">
                                            {{ $ingreso?->tipo_ingreso?->nombre ?? 'Ingreso eliminado' }}
                                        </div>
                                        <div class="text-secondary" style="font-size: .8rem;">
                                            {{ $ingreso?->descripcion ?: 'Sin descripción' }}
                                        </div>
                                        @if ($linea->observaciones)
                                            <div class="text-secondary fst-italic" style="font-size: .78rem;">
                                                Nota de la línea: {{ $linea->observaciones }}
                                            </div>
                                        @endif
                                    </td>

                                    <td>
                                        @if ($ingreso?->orden_trabajo)
                                            <a href="{{ route('procesos.ordenes_trabajo.show', $ingreso->orden_trabajo->id) }}">
                                                {{ $ingreso->orden_trabajo->codigo }}
                                            </a>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>

                                    <td class="text-end">
                                        {{ $ingreso ? number_format((float) $ingreso->total, 2) : '—' }}
                                        @if ($ingreso)
                                            <div class="text-secondary" style="font-size: .75rem;">
                                                {{ $ingreso->moneda?->codigo }}
                                            </div>
                                        @endif
                                    </td>

                                    <td class="text-end fw-semibold">
                                        {{ number_format((float) $linea->monto, 2) }}
                                    </td>

                                    <td class="text-end">
                                        @if (! $ingreso)
                                            <span class="badge bg-secondary">El ingreso ya no existe</span>

                                        @elseif ($pasado)
                                            {{--
                                                Pasado del total. Solo se puede decir cuando
                                                las monedas son la misma, porque con
                                                monedas distintas el importe de la linea no
                                                se puede comparar con el total del ingreso:
                                                no hay conversion guardada. Ahi se dice
                                                que no se puede comprobar, y no se dice que
                                                esta bien.
                                            --}}
                                            <span class="badge bg-danger"
                                                  title="La suma de todos los cobros de este ingreso pasa de su total.">
                                                sobrecobrado
                                            </span>
                                            <div class="text-danger" style="font-size: .75rem;">
                                                {{ number_format($totalCobrado - (float) $ingreso->total, 2) }}
                                                de más
                                            </div>

                                        @elseif ($pagado)
                                            {{-- Y el subtítulo dice de quien es lo que paga. --}}
                                            <span class="badge bg-success"
                                                  title="Con este cobro, este ingreso queda pagado del todo.">
                                                ya pagado
                                            </span>
                                            <div class="text-secondary" style="font-size: .75rem;">
                                                @if ($deOtros > 0)
                                                    {{ number_format($deOtros, 2) }} en otros cobros
                                                    + {{ number_format((float) $linea->monto, 2) }} aquí
                                                @else
                                                    {{ number_format($totalCobrado, 2) }} de
                                                    {{ number_format((float) $ingreso->total, 2) }}
                                                @endif
                                            </div>

                                        @elseif ($mismaMoneda)
                                            <span class="badge bg-primary">quedan por cobrar</span>
                                            <div class="text-secondary" style="font-size: .75rem;">
                                                {{ number_format(max(0, (float) $ingreso->total - $totalCobrado), 2) }}
                                            </div>

                                        @else
                                            {{-- Moneda distinta: no se sabe si esta pagado. --}}
                                            <span class="badge bg-secondary"
                                                  title="Este ingreso está facturado en otra moneda, así que no se puede comparar su importe con el de esta línea.">
                                                no se puede comprobar
                                            </span>
                                            <div class="text-secondary" style="font-size: .75rem;">
                                                {{ number_format($deOtros, 2) }} cobrados en
                                                {{ $cobro->moneda?->codigo }}
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach

                        </tbody>

                        <tfoot class="table-light">
                            <tr>
                                <th colspan="3" class="text-end">Total</th>
                                <th class="text-end fs-6">
                                    {{ number_format($cobro->total(), 2) }}
                                </th>
                                <th></th>
                            </tr>
                        </tfoot>

                    </table>

                </div>

            </div>

        </div>

    </div>

    <x-slot:footerFiles>
    </x-slot>

</x-base-layout>
