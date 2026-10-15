<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Órdenes de Trabajo' }}
    </x-slot>

    <x-breadcrumb :items="$breadcrumbs"/>

    <x-slot:headerFiles>
        <style>
            .ordenes-workspace {
                --ot-ink: #1d2b3a;
                --ot-muted: #6b7787;
                --ot-line: #e3e8ef;
                --ot-surface: #fff;
                --ot-soft: #f6f8fb;
                --ot-primary: #4057b2;
                --ot-primary-hover: #34499a;
                color: var(--ot-ink);
                padding-bottom: 1rem;
            }

            .ordenes-hero {
                position: relative;
                display: flex;
                min-height: 180px;
                align-items: center;
                justify-content: space-between;
                gap: 2rem;
                overflow: hidden;
                margin-bottom: 1.25rem;
                padding: clamp(1.5rem, 3vw, 2.3rem);
                border-radius: 18px;
                background: linear-gradient(112deg, #172944 0%, #1b4265 58%, #176d76 100%);
                box-shadow: 0 14px 28px rgba(26, 50, 75, .16);
            }

            .ordenes-hero::before,
            .ordenes-hero::after {
                position: absolute;
                top: -330px;
                right: 11%;
                width: 500px;
                height: 500px;
                border: 1px solid rgba(255, 255, 255, .09);
                border-radius: 50%;
                content: "";
                pointer-events: none;
            }

            .ordenes-hero::before {
                box-shadow: 0 0 0 32px rgba(255, 255, 255, .035), 0 0 0 72px rgba(255, 255, 255, .025);
            }

            .ordenes-hero::after {
                top: -250px;
                right: 7%;
                width: 340px;
                height: 340px;
                background: rgba(21, 157, 165, .08);
            }

            .ordenes-hero__content,
            .ordenes-hero__actions {
                position: relative;
                z-index: 1;
            }

            .ordenes-hero__content {
                max-width: 650px;
            }

            .ordenes-workspace__eyebrow {
                display: inline-flex;
                align-items: center;
                gap: .45rem;
                margin-bottom: .65rem;
                color: #69e1e2;
                font-size: .68rem;
                font-weight: 800;
                letter-spacing: .13em;
                text-transform: uppercase;
            }

            .ordenes-workspace__title {
                margin: 0;
                color: #fff;
                font-size: clamp(1.65rem, 2.7vw, 2.2rem);
                font-weight: 700;
                letter-spacing: -.035em;
                line-height: 1.2;
            }

            .ordenes-workspace__description {
                max-width: 560px;
                margin: .55rem 0 0;
                color: rgba(255, 255, 255, .83);
                font-size: .9rem;
                line-height: 1.55;
            }

            .ordenes-workspace__actions {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                justify-content: flex-end;
                gap: .7rem;
            }

            .ot-action-button {
                display: inline-flex;
                min-height: 44px;
                align-items: center;
                justify-content: center;
                gap: .55rem;
                padding: .7rem 1rem;
                border: 1px solid transparent;
                border-radius: 8px;
                font-size: .84rem;
                font-weight: 700;
                line-height: 1;
                text-decoration: none;
                transition: background-color .15s ease, border-color .15s ease, box-shadow .15s ease, transform .15s ease;
            }

            .ot-action-button:hover {
                transform: translateY(-1px);
            }

            .ot-action-button--secondary {
                border-color: rgba(255, 255, 255, .38);
                background: rgba(255, 255, 255, .08);
                color: #fff;
            }

            .ot-action-button--secondary:hover {
                border-color: rgba(255, 255, 255, .7);
                background: rgba(255, 255, 255, .15);
                color: #fff;
            }

            .ot-action-button--primary {
                border-color: #fff;
                background: #fff;
                box-shadow: 0 5px 14px rgba(8, 25, 44, .2);
                color: #18344f;
            }

            .ot-action-button--primary:hover {
                border-color: #eef4f8;
                background: #eef4f8;
                color: #18344f;
            }

            .ordenes-workspace__metrics {
                display: grid;
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: .9rem;
                margin-bottom: 1.15rem;
            }

            .ordenes-metric {
                display: flex;
                min-width: 0;
                min-height: 94px;
                align-items: center;
                gap: .85rem;
                padding: 1rem 1.1rem;
                border: 1px solid var(--ot-line);
                border-radius: 13px;
                background: var(--ot-surface);
                box-shadow: 0 4px 14px rgba(27, 42, 61, .035);
            }

            .ordenes-metric__icon {
                display: grid;
                width: 44px;
                height: 44px;
                flex: 0 0 44px;
                place-items: center;
                border-radius: 12px;
                font-size: 1rem;
            }

            .ordenes-metric--total .ordenes-metric__icon {
                background: #eaf2ff;
                color: #2864b5;
            }

            .ordenes-metric--pending .ordenes-metric__icon {
                background: #fff4df;
                color: #b87912;
            }

            .ordenes-metric--progress .ordenes-metric__icon {
                background: #e4f5f1;
                color: #12806c;
            }

            .ordenes-metric--done .ordenes-metric__icon {
                background: #f0eaff;
                color: #6948b8;
            }

            .ordenes-metric__label {
                margin: 0 0 .15rem;
                color: var(--ot-muted);
                font-size: .76rem;
                line-height: 1.35;
            }

            .ordenes-metric__value {
                margin: 0;
                color: var(--ot-ink);
                font-size: 1.45rem;
                font-weight: 800;
                letter-spacing: -.035em;
                line-height: 1.15;
            }

            .ordenes-workspace__filter {
                display: grid;
                grid-template-columns: minmax(230px, 1fr) auto minmax(220px, 1fr);
                align-items: center;
                gap: 1.25rem;
                margin-bottom: 1.15rem;
                padding: 1.1rem 1.25rem;
                border: 1px solid var(--ot-line);
                border-radius: 12px;
                background: var(--ot-surface);
                box-shadow: 0 3px 12px rgba(27, 42, 61, .035);
            }

            .ordenes-workspace__filter-intro {
                display: flex;
                align-items: center;
                gap: .8rem;
                min-width: 0;
            }

            .ordenes-workspace__filter-icon {
                display: grid;
                width: 40px;
                height: 40px;
                flex: 0 0 40px;
                place-items: center;
                border-radius: 10px;
                background: #eef1fb;
                color: var(--ot-primary);
                font-size: 1rem;
            }

            .ordenes-workspace__filter-title {
                margin: 0;
                color: var(--ot-ink);
                font-size: .9rem;
                font-weight: 700;
            }

            .ordenes-workspace__filter-caption {
                margin: .2rem 0 0;
                color: var(--ot-muted);
                font-size: .76rem;
            }

            .ordenes-workspace__filter-form {
                display: flex;
                align-items: end;
                gap: .55rem;
            }

            .ordenes-workspace__field {
                min-width: 205px;
            }

            .ordenes-workspace__field label {
                display: block;
                margin-bottom: .35rem;
                color: #4d5a6b;
                font-size: .75rem;
                font-weight: 700;
            }

            .ordenes-workspace__field .form-select {
                min-height: 40px;
                border-color: #d9e0e9;
                border-radius: 7px;
                color: var(--ot-ink);
                font-size: .83rem;
                box-shadow: none;
            }

            .ordenes-workspace__field .form-select:focus {
                border-color: #8292cf;
                box-shadow: 0 0 0 3px rgba(64, 87, 178, .1);
            }

            .ordenes-workspace__filter-submit,
            .ordenes-workspace__filter-clear {
                display: inline-flex;
                min-height: 40px;
                align-items: center;
                justify-content: center;
                gap: .4rem;
                padding: .55rem .8rem;
                border-radius: 7px;
                font-size: .8rem;
                font-weight: 700;
                white-space: nowrap;
                text-decoration: none;
            }

            .ordenes-workspace__filter-submit {
                border: 1px solid var(--ot-primary);
                background: var(--ot-primary);
                color: #fff;
            }

            .ordenes-workspace__filter-submit:hover {
                border-color: var(--ot-primary-hover);
                background: var(--ot-primary-hover);
                color: #fff;
            }

            .ordenes-workspace__filter-clear {
                border: 1px solid var(--ot-line);
                background: #fff;
                color: #596779;
            }

            .ordenes-workspace__filter-clear:hover {
                border-color: #b9c5d5;
                background: var(--ot-soft);
                color: var(--ot-ink);
            }

            .ordenes-workspace__search-note {
                display: flex;
                align-items: flex-start;
                justify-content: flex-end;
                gap: .5rem;
                color: var(--ot-muted);
                font-size: .75rem;
                line-height: 1.5;
                text-align: right;
            }

            .ordenes-workspace__search-note i {
                margin-top: .1rem;
                color: #8390a1;
            }

            .ordenes-workspace__table-card {
                overflow: hidden;
                border: 1px solid var(--ot-line);
                border-radius: 12px;
                background: var(--ot-surface);
                box-shadow: 0 5px 18px rgba(27, 42, 61, .045);
            }

            .ordenes-workspace__table-head {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 1rem;
                padding: 1rem 1.25rem;
                border-bottom: 1px solid var(--ot-line);
            }

            .ordenes-workspace__table-title {
                margin: 0;
                color: var(--ot-ink);
                font-size: .95rem;
                font-weight: 700;
            }

            .ordenes-workspace__table-caption {
                margin: .2rem 0 0;
                color: var(--ot-muted);
                font-size: .76rem;
            }

            .ordenes-workspace__active-filter {
                display: inline-flex;
                align-items: center;
                gap: .4rem;
                padding: .4rem .65rem;
                border: 1px solid #d9e1f7;
                border-radius: 6px;
                background: #f3f5fc;
                color: #394e9c;
                font-size: .74rem;
                font-weight: 700;
                white-space: nowrap;
            }

            .ordenes-workspace__table-body {
                padding: 1rem 1.25rem 1.1rem;
            }

            .ordenes-workspace__table-body .table-responsive {
                border: 0;
            }

            .ordenes-workspace__table-card .table-responsive {
                min-width: 0;
            }

            .ordenes-workspace__table-card table.dataTable {
                width: 100% !important;
                min-width: 0 !important;
                table-layout: fixed;
                margin: .25rem 0 .6rem !important;
                border-collapse: separate !important;
                border-spacing: 0;
            }

            .ordenes-workspace__table-card table.dataTable thead th {
                padding: .8rem .85rem;
                border-bottom: 1px solid #dfe5ed !important;
                background: var(--ot-soft);
                color: #4c596b;
                font-size: .72rem;
                font-weight: 800;
                letter-spacing: .045em;
                text-transform: uppercase;
                white-space: nowrap;
            }

            .ordenes-workspace__table-card table.dataTable tbody td {
                padding: .8rem .85rem;
                border-bottom: 1px solid #edf0f4;
                color: #465365;
                font-size: .82rem;
                vertical-align: middle;
                white-space: normal;
                overflow-wrap: anywhere;
            }

            .ordenes-workspace__table-card table.dataTable th:last-child,
            .ordenes-workspace__table-card table.dataTable td:last-child {
                width: 140px;
                min-width: 140px;
                white-space: nowrap;
                overflow-wrap: normal;
            }

            .ordenes-workspace__table-card table.dataTable tbody tr:last-child td {
                border-bottom: 0;
            }

            .ordenes-workspace__table-card table.dataTable tbody tr:hover td {
                background: #f8f9fc;
            }

            .ordenes-workspace__table-card .dataTables_wrapper,
            .ordenes-workspace__table-card .dt-container {
                color: #586577;
                font-size: .78rem;
            }

            .ordenes-workspace__table-card .dataTables_length label,
            .ordenes-workspace__table-card .dataTables_filter label,
            .ordenes-workspace__table-card .dt-length,
            .ordenes-workspace__table-card .dt-search {
                color: #566273;
                font-size: .78rem;
                font-weight: 600;
            }

            .ordenes-workspace__table-card .dataTables_length select,
            .ordenes-workspace__table-card .dt-length select {
                min-height: 36px;
                margin: 0 .35rem;
                border: 1px solid #d9e0e9;
                border-radius: 7px;
                color: var(--ot-ink);
            }

            .ordenes-workspace__table-card .dataTables_filter input,
            .ordenes-workspace__table-card .dt-search input {
                min-height: 38px;
                margin-left: .45rem;
                border: 1px solid #d9e0e9;
                border-radius: 7px;
                color: var(--ot-ink);
                outline: 0;
                box-shadow: none;
            }

            .ordenes-workspace__table-card .dataTables_filter input:focus,
            .ordenes-workspace__table-card .dt-search input:focus {
                border-color: #8292cf;
                box-shadow: 0 0 0 3px rgba(64, 87, 178, .1);
            }

            .ordenes-workspace__table-card .dataTables_info,
            .ordenes-workspace__table-card .dt-info {
                padding-top: .75rem;
                color: #718094;
                font-size: .76rem;
            }

            .ordenes-workspace__table-card .pagination,
            .ordenes-workspace__table-card .dt-paging .pagination {
                margin: .35rem 0 0;
            }

            @media (max-width: 991.98px) {
                .ordenes-workspace__metrics {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }

                .ordenes-workspace__filter {
                    grid-template-columns: minmax(0, 1fr) minmax(180px, 1fr);
                }

                .ordenes-workspace__search-note {
                    grid-column: 1 / -1;
                    justify-content: flex-start;
                    text-align: left;
                }
            }

            @media (max-width: 767.98px) {
                .ordenes-hero {
                    align-items: flex-start;
                    flex-direction: column;
                    gap: 1.35rem;
                    min-height: 0;
                }

                .ordenes-workspace__actions {
                    width: 100%;
                    justify-content: flex-start;
                }

                .ordenes-workspace__actions .ot-action-button {
                    flex: 1 1 auto;
                }

                .ordenes-workspace__filter {
                    grid-template-columns: 1fr;
                    gap: .9rem;
                    padding: 1rem;
                }

                .ordenes-workspace__filter-form {
                    align-items: stretch;
                    flex-wrap: wrap;
                }

                .ordenes-workspace__field {
                    flex: 1 1 100%;
                    min-width: 0;
                }

                .ordenes-workspace__search-note {
                    grid-column: auto;
                }

                .ordenes-workspace__table-head {
                    align-items: flex-start;
                    flex-direction: column;
                    padding: .9rem 1rem;
                }

                .ordenes-workspace__table-body {
                    padding: .85rem .75rem 1rem;
                }
            }

            @media (max-width: 575.98px) {
                .ordenes-workspace__actions .ot-action-button {
                    min-height: 40px;
                    padding: .6rem .65rem;
                    font-size: .78rem;
                }

                .ordenes-workspace__metrics {
                    gap: .6rem;
                }

                .ordenes-metric {
                    min-height: 82px;
                    gap: .6rem;
                    padding: .75rem;
                }

                .ordenes-metric__icon {
                    width: 38px;
                    height: 38px;
                    flex-basis: 38px;
                }

                .ordenes-metric__label {
                    font-size: .7rem;
                }

                .ordenes-metric__value {
                    font-size: 1.25rem;
                }

                .ordenes-workspace__filter-submit,
                .ordenes-workspace__filter-clear {
                    flex: 1 1 0;
                }

                .ordenes-workspace__table-card .dt-layout-row,
                .ordenes-workspace__table-card .dt-layout-cell {
                    width: 100%;
                }

                .ordenes-workspace__table-card .dataTables_filter input,
                .ordenes-workspace__table-card .dt-search input {
                    width: min(100%, 230px);
                }
            }
        </style>
    </x-slot>

    @php
        $estadoFiltro = request()->query('estado');
        $datosEstadoFiltro = is_scalar($estadoFiltro) && filter_var((string) $estadoFiltro, FILTER_VALIDATE_INT) !== false
            ? (\App\Models\OrdenesTrabajo::ESTADOS[(int) $estadoFiltro] ?? null)
            : null;
    @endphp

    <div class="row layout-top-spacing">
        <div class="col-12">
            <main class="ordenes-workspace">
                <header class="ordenes-hero">
                    <div class="ordenes-hero__content">
                        <div class="ordenes-workspace__eyebrow">
                            <i class="bi bi-graph-up-arrow" aria-hidden="true"></i>
                            Operaciones · Control del taller
                        </div>
                        <h1 class="ordenes-workspace__title">Órdenes de trabajo</h1>
                        <p class="ordenes-workspace__description">
                            Registre y dé seguimiento al trabajo de su taller desde un solo lugar.
                        </p>
                    </div>

                    <nav class="ordenes-workspace__actions ordenes-hero__actions" aria-label="Acciones de órdenes">
                        <a href="{{ route('procesos.ordenes_trabajo.calendario') }}"
                           class="ot-action-button ot-action-button--secondary">
                            <i class="bi bi-calendar3" aria-hidden="true"></i>
                            Calendario
                        </a>
                        <a href="{{ route('procesos.ordenes_trabajo.create') }}"
                           class="ot-action-button ot-action-button--primary">
                            <i class="bi bi-plus-lg" aria-hidden="true"></i>
                            Nueva orden
                        </a>
                    </nav>
                </header>

                <section class="ordenes-workspace__metrics" aria-label="Resumen de órdenes de trabajo">
                    <article class="ordenes-metric ordenes-metric--total">
                        <span class="ordenes-metric__icon" aria-hidden="true">
                            <i class="bi bi-clipboard-data"></i>
                        </span>
                        <div>
                            <p class="ordenes-metric__label">Órdenes registradas</p>
                            <p class="ordenes-metric__value">{{ number_format($ordenesResumen['total'] ?? 0) }}</p>
                        </div>
                    </article>
                    <article class="ordenes-metric ordenes-metric--pending">
                        <span class="ordenes-metric__icon" aria-hidden="true">
                            <i class="bi bi-hourglass-split"></i>
                        </span>
                        <div>
                            <p class="ordenes-metric__label">Pendientes</p>
                            <p class="ordenes-metric__value">{{ number_format($ordenesResumen['pendientes'] ?? 0) }}</p>
                        </div>
                    </article>
                    <article class="ordenes-metric ordenes-metric--progress">
                        <span class="ordenes-metric__icon" aria-hidden="true">
                            <i class="bi bi-gear-wide-connected"></i>
                        </span>
                        <div>
                            <p class="ordenes-metric__label">En proceso</p>
                            <p class="ordenes-metric__value">{{ number_format($ordenesResumen['en_proceso'] ?? 0) }}</p>
                        </div>
                    </article>
                    <article class="ordenes-metric ordenes-metric--done">
                        <span class="ordenes-metric__icon" aria-hidden="true">
                            <i class="bi bi-check2-circle"></i>
                        </span>
                        <div>
                            <p class="ordenes-metric__label">Finalizadas</p>
                            <p class="ordenes-metric__value">{{ number_format($ordenesResumen['finalizadas'] ?? 0) }}</p>
                        </div>
                    </article>
                </section>

                <section class="ordenes-workspace__filter" aria-labelledby="filtro-ordenes-titulo">
                    <div class="ordenes-workspace__filter-intro">
                        <span class="ordenes-workspace__filter-icon" aria-hidden="true">
                            <i class="bi bi-funnel"></i>
                        </span>
                        <div>
                            <h2 id="filtro-ordenes-titulo" class="ordenes-workspace__filter-title">Filtrar órdenes</h2>
                            <p class="ordenes-workspace__filter-caption">Acote el listado por estado operativo.</p>
                        </div>
                    </div>

                    <form method="GET" action="{{ route('procesos.ordenes_trabajo.index') }}"
                          class="ordenes-workspace__filter-form">
                        <div class="ordenes-workspace__field">
                            <label for="filtro-estado-orden">Estado</label>
                            <select id="filtro-estado-orden" name="estado" class="form-select">
                                <option value="">Todos los estados</option>
                                @foreach (\App\Models\OrdenesTrabajo::ESTADOS as $codigoEstado => $datosEstado)
                                    <option value="{{ $codigoEstado }}" @selected((string) request()->query('estado', '') === (string) $codigoEstado)>
                                        {{ $datosEstado['texto'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="ordenes-workspace__filter-submit">
                            <i class="bi bi-check2" aria-hidden="true"></i>
                            Aplicar
                        </button>
                        <a href="{{ route('procesos.ordenes_trabajo.index') }}"
                           class="ordenes-workspace__filter-clear">
                            <i class="bi bi-x-lg" aria-hidden="true"></i>
                            Limpiar
                        </a>
                    </form>

                    <div class="ordenes-workspace__search-note">
                        <i class="bi bi-info-circle" aria-hidden="true"></i>
                        <span>La búsqueda de la tabla se aplica dentro del estado seleccionado.</span>
                    </div>
                </section>

                <section class="ordenes-workspace__table-card" aria-labelledby="listado-ordenes-titulo">
                    <div class="ordenes-workspace__table-head">
                        <div>
                            <h2 id="listado-ordenes-titulo" class="ordenes-workspace__table-title">Listado de órdenes</h2>
                            <p class="ordenes-workspace__table-caption">Consulte, busque y gestione las órdenes registradas.</p>
                        </div>
                        @if ($datosEstadoFiltro)
                            <span class="ordenes-workspace__active-filter">
                                <i class="bi bi-funnel-fill" aria-hidden="true"></i>
                                Estado: {{ $datosEstadoFiltro['texto'] }}
                            </span>
                        @endif
                    </div>

                    <div class="ordenes-workspace__table-body">
                        <div class="table-responsive">
                            {{ $dataTable->table() }}
                        </div>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <x-slot:footerFiles>
        {{ $dataTable->scripts() }}
    </x-slot>

</x-base-layout>
