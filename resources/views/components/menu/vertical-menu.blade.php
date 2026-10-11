{{-- Menú principal del sistema de minería --}}

<div class="sidebar-wrapper sidebar-theme">

    <nav id="sidebar">

        {{-- Logo --}}
        <div class="navbar-nav theme-brand flex-row text-center">

            <div class="nav-logo">

                <div class="nav-item theme-logo">
                    <a href="{{ url('/') }}">
                        <img
                            src="{{ asset('resources/images/logo.svg') }}"
                            class="navbar-logo logo-dark"
                            alt="Logo"
                        >
                        <img
                            src="{{ asset('resources/images/logo2.svg') }}"
                            class="navbar-logo logo-light"
                            alt="Logo"
                        >
                    </a>
                </div>

                <div class="nav-item theme-text">
                    <a href="{{ url('/') }}" class="nav-link">
                        MINA
                    </a>
                </div>

            </div>

            <div class="nav-item sidebar-toggle">
                <div class="btn-toggle sidebarCollapse">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        width="24"
                        height="24"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        class="feather feather-chevrons-left"
                    >
                        <polyline points="11 17 6 12 11 7"></polyline>
                        <polyline points="18 17 13 12 18 7"></polyline>
                    </svg>
                </div>
            </div>

        </div>

        {{-- Perfil --}}
        @if (!Request::is('collapsible-menu/*'))
            <div class="profile-info">
                <div class="user-info">

                    <div class="profile-img">
                        <img
                            src="{{ asset('resources/images/profile-30.png') }}"
                            alt="avatar"
                        >
                    </div>

                    <div class="profile-content">
                        <h6>Sistema</h6>
                        <p>Administración</p>
                    </div>

                </div>
            </div>
        @endif

        <div class="shadow-bottom"></div>

        {{-- Menú --}}
        <ul class="list-unstyled menu-categories" id="accordionExample">

            {{-- =====================================================
                INICIO
            ====================================================== --}}
            <li class="menu menu-heading">
                <div class="heading">
                    <i class="bi bi-house"></i>
                    <span>INICIO</span>
                </div>
            </li>

            {{--
                Se marca con routeIs('home') y no con Request::is('/') porque el
                panel tiene un filtro de periodo en la url: "/?periodo=anio" no es
                la raiz y Request::is('/') no la reconoce, de modo que al cambiar el
                periodo se apagaba la Pestana de Inicio. Parecia un fallo del panel y
                era del menu.
            --}}
            <li class="menu {{ Request::routeIs('home') ? 'active' : '' }}">
                <a href="{{ route('home') }}" aria-expanded="false" class="dropdown-toggle">
                    <div>
                        <i class="bi bi-speedometer2"></i>
                        <span>Dashboard</span>
                    </div>
                </a>
            </li>

            {{-- =====================================================
                ADMINISTRACIÓN
            ====================================================== --}}
            <li class="menu menu-heading">
                <div class="heading">
                    <i class="bi bi-people"></i>
                    <span>ADMINISTRACIÓN</span>
                </div>
            </li>

            @php
                $administracionActiva =
                    Request::is('admin/clientes*') ||
                    Request::is('admin/empleados*') ||
                    Request::is('admin/etapas*');
            @endphp

            <li class="menu {{ $administracionActiva ? 'active' : '' }}">

                <a
                    href="#administracion"
                    data-bs-toggle="collapse"
                    aria-expanded="{{ $administracionActiva ? 'true' : 'false' }}"
                    class="dropdown-toggle"
                >
                    <div>
                        <i class="bi bi-person-gear"></i>
                        <span>Administración</span>
                    </div>

                    <div>
                        <i class="bi bi-chevron-right"></i>
                    </div>
                </a>

                <ul
                    class="collapse submenu list-unstyled {{ $administracionActiva ? 'show' : '' }}"
                    id="administracion"
                    data-bs-parent="#accordionExample"
                >

                    <li class="{{ Request::routeIs('admin.clientes.*') ? 'active' : '' }}">
                        <a href="{{ route('admin.clientes.index') }}">
                            <div>
                                <i class="bi bi-person-vcard"></i>
                                <span>Clientes</span>
                            </div>
                        </a>
                    </li>

                    <li class="{{ Request::routeIs('admin.empleados.*') ? 'active' : '' }}">
                        <a href="{{ route('admin.empleados.index') }}">
                            <div>
                                <i class="bi bi-person-badge"></i>
                                <span>Empleados</span>
                            </div>
                        </a>
                    </li>

                    <li class="{{ Request::routeIs('admin.etapas.*') ? 'active' : '' }}">
                        <a href="{{ route('admin.etapas.index') }}">
                            <div>
                                <i class="bi bi-diagram-3"></i>
                                <span>Etapas</span>
                            </div>
                        </a>
                    </li>

                    <li class="{{ Request::routeIs('admin.equipos.*') ? 'active' : '' }}">
                        <a href="{{ route('admin.equipos.index') }}">
                            <div>
                                <i class="bi bi-gear-wide-connected"></i>
                                <span>Equipos</span>
                            </div>
                        </a>
                    </li>

                </ul>
            </li>

            {{-- =====================================================
                OPERACIONES
            ====================================================== --}}
            <li class="menu menu-heading">
                <div class="heading">
                    <i class="bi bi-gear-wide-connected"></i>
                    <span>OPERACIONES</span>
                </div>
            </li>

            @php
                $operacionesActiva = Request::is('procesos/*');
            @endphp

            <li class="menu {{ $operacionesActiva ? 'active' : '' }}">

                <a
                    href="#operaciones"
                    data-bs-toggle="collapse"
                    aria-expanded="{{ $operacionesActiva ? 'true' : 'false' }}"
                    class="dropdown-toggle"
                >
                    <div>
                        <i class="bi bi-clipboard2-check"></i>
                        <span>Órdenes de Trabajo</span>
                    </div>

                    <div>
                        <i class="bi bi-chevron-right"></i>
                    </div>
                </a>

                <ul
                    class="collapse submenu list-unstyled {{ $operacionesActiva ? 'show' : '' }}"
                    id="operaciones"
                    data-bs-parent="#accordionExample"
                >

                    <li class="{{ Request::routeIs('procesos.ordenes_trabajo.calendario') ? 'active' : '' }}">
                        <a href="{{ route('procesos.ordenes_trabajo.calendario') }}">
                            <div>
                                <i class="bi bi-calendar3"></i>
                                <span>Calendario</span>
                            </div>
                        </a>
                    </li>

                    <li class="{{ Request::routeIs('procesos.ordenes_trabajo.index') ? 'active' : '' }}">
                        <a href="{{ route('procesos.ordenes_trabajo.index') }}">
                            <div>
                                <i class="bi bi-list-ul"></i>
                                <span>Órdenes de Trabajo</span>
                            </div>
                        </a>
                    </li>

                </ul>
            </li>

            {{-- =====================================================
                COSTOS
            ====================================================== --}}
            <li class="menu menu-heading">
                <div class="heading">
                    <i class="bi bi-cash-coin"></i>
                    <span>COSTOS</span>
                </div>
            </li>

            @php
                $costosActiva = Request::is('configuracion/*');
            @endphp

            <li class="menu {{ $costosActiva ? 'active' : '' }}">

                <a
                    href="#costos"
                    data-bs-toggle="collapse"
                    aria-expanded="{{ $costosActiva ? 'true' : 'false' }}"
                    class="dropdown-toggle"
                >
                    <div>
                        <i class="bi bi-wallet2"></i>
                        <span>Configuración</span>
                    </div>

                    <div>
                        <i class="bi bi-chevron-right"></i>
                    </div>
                </a>

                <ul
                    class="collapse submenu list-unstyled {{ $costosActiva ? 'show' : '' }}"
                    id="costos"
                    data-bs-parent="#accordionExample"
                >

                    <li class="{{ Request::routeIs('configuracion.categorias_costos.*') ? 'active' : '' }}">
                        <a href="{{ route('configuracion.categorias_costos.index') }}">
                            <div>
                                <i class="bi bi-tags"></i>
                                <span>Categorías de Costos</span>
                            </div>
                        </a>
                    </li>
                    <li class="{{ Request::routeIs('configuracion.tipos_pago_empleado.*') ? 'active' : '' }}">
                        <a href="{{ route('configuracion.tipos_pago_empleado.index') }}">
                            <div>
                                <i class="bi bi-cash-stack"></i>
                                <span>Tipos de Pago de Empleado</span>
                            </div>
                        </a>
                    </li>

                    {{--
                        Los tipos de ingreso van aqui y no en la pantalla de
                        ingresos, y no es una cuestion de donde queda mas comodo:
                        ingresos es una pantalla de la orden, con su cabecera y su
                        detalle, y un catalogo de cuatro nombres no cabe ahi
                        dentro. Ademas el catalogo es dato maestro —lo mismo que
                        las categorias de costo y los tipos de pago— y los datos
                        maestros van todos juntos, que es donde uno va a buscarlos.

                        Y va despues de los tipos de pago de empleado y no junto a
                        las categorias de costo, por el orden en que el taller
                        los va necesitando: primero lo que se usa en cada orden
                        y despues lo que se consulta una vez al registrar el
                        cobro.
                    --}}
                    <li class="{{ Request::routeIs('configuracion.tipos_ingreso.*') ? 'active' : '' }}">
                        <a href="{{ route('configuracion.tipos_ingreso.index') }}">
                            <div>
                                <i class="bi bi-cash-coin"></i>
                                <span>Tipos de Ingreso</span>
                            </div>
                        </a>
                    </li>

                    <li class="{{ Request::routeIs('configuracion.tipos_produccion.*') ? 'active' : '' }}">
                        <a href="{{ route('configuracion.tipos_produccion.index') }}">
                            <div>
                                <i class="bi bi-box-seam"></i>
                                <span>Tipos de Producción</span>
                            </div>
                        </a>
                    </li>

                    <li class="{{ Request::routeIs('configuracion.tipos_participacion.*') ? 'active' : '' }}">
                        <a href="{{ route('configuracion.tipos_participacion.index') }}">
                            <div>
                                <i class="bi bi-people"></i>
                                <span>Tipos de participación</span>
                            </div>
                        </a>
                    </li>

                    <li class="{{ Request::routeIs('configuracion.unidades_medida.*') ? 'active' : '' }}">
                        <a href="{{ route('configuracion.unidades_medida.index') }}">
                            <div>
                                <i class="bi bi-rulers"></i>
                                <span>Unidades de medida</span>
                            </div>
                        </a>
                    </li>

                    {{--
                        El tipo de cambio va aqui y no en Inventario, y no es solo
                        una cuestion de donde queda mas comodo: el tipo de
                        cambio no es ni una compra ni un material. Es el dato
                        maestro que define como se mide todo lo demas, y va con
                        las demas listas maestras del bloque.

                        Aun asi, lo consume el almacen y las compras, que estan
                        en Inventario. Por eso la pantalla avisa de que el
                        material entra con el valor de ese dia, para que se sepa
                        que el almacen no se tasa solo.
                    --}}
                    <li class="{{ Request::routeIs('configuracion.tipos_cambio.*') ? 'active' : '' }}">
                        <a href="{{ route('configuracion.tipos_cambio.index') }}">
                            <div>
                                <i class="bi bi-cash-coin"></i>
                                <span>Tipo de Cambio</span>
                            </div>
                        </a>
                    </li>

                    {{--
                        Las monedas van al lado del tipo de cambio y no antes,
                        porque el tipo de cambio es lo que se mira a diario y el
                        catalogo de monedas se abre una vez, cuando hace falta
                        una moneda nueva. Aunque el orden de los dos parece al
                        reves: el tipo de cambio necesita una moneda para
                        existir, y la lista de monedas es lo que se configura
                        primero.
                    --}}
                    <li class="{{ Request::routeIs('configuracion.monedas.*') ? 'active' : '' }}">
                        <a href="{{ route('configuracion.monedas.index') }}">
                            <div>
                                <i class="bi bi-currency-exchange"></i>
                                <span>Monedas</span>
                            </div>
                        </a>
                    </li>

                    {{--
                        El precio del oro va con el tipo de cambio y no en
                        Inventario, por el mismo motivo: los dos son series por
                        dias que se cargan desde Excel y los dos son datos
                        maestros de como se mide todo lo demas.

                        Y van juntos pero en pantallas distintas, y no juntos en
                        la misma tabla. El tipo de cambio es la equivalencia
                        entre dos monedas; el precio del oro es lo que vale un
                        gramo, que es otra cosa. Con el cero tambien se
                        distinguen: en el tipo de cambio el cero no se admite
                        porque dejaria las compras convertidas a cero, y aqui el
                        cero si se admite y quiere decir "de este dia no se sabe
                        el precio". Meter las dos series en la misma tabla
                        obligaria a decidir que significa el cero, y solo hay
                        una respuesta correcta para cada una.
                    --}}
                    <li class="{{ Request::routeIs('configuracion.precios_oro.*') ? 'active' : '' }}">
                        <a href="{{ route('configuracion.precios_oro.index') }}">
                            <div>
                                <i class="bi bi-gem"></i>
                                <span>Precios del Oro</span>
                            </div>
                        </a>
                    </li>

    {{--
        Las cajas van las ULTIMAS de Configuracion, y el icono es el de una
        caja fuerte porque es una cuenta donde se guarda dinero.

        Van las ultimas por el mismo orden que todo el bloque: el taller va
        necesitando las pantallas en el orden en que las usa, y una caja solo
        aparece cuando llega un cobro, que es el ultimo paso de una orden. Si
        la metiera junto a los tipos de ingreso, que estan al lado de las
        categorias de costo, diria que la caja se elige al registrar un
        ingreso, y no es.
    --}}
    <li class="{{ Request::routeIs('configuracion.cajas.*') ? 'active' : '' }}">
        <a href="{{ route('configuracion.cajas.index') }}">
            <div>
                <i class="bi bi-safe2"></i>
                <span>Cajas</span>
            </div>
        </a>
    </li>

    {{--
        Los metodos de pago van las ULTIMAS de Configuracion, detras de las cajas,
        y por lo mismo que ellas: los dos aparecen solo cuando llega un cobro, que
        es el ultimo paso de una orden.

        Y van detras de las cajas y no antes porque en el cobro se pregunta primero
        donde entra el dinero y despues con que se paga. El icono es el de una
        tarjeta, que es la forma de pago que todo el mundo reconoce sin tener que
        leer el texto.

        Y NO SE CONFUNDEN CON LOS TIPOS DE PAGO DE EMPLEADO, que estan mas arriba
        en el mismo bloque. Aquellos son como se le paga a la gente que trabaja
        aqui; estos son como se le paga a quien compra. Son dos tablas distintas y
        por eso dos pantallas distintas: un cobro no puede acabar guardando un
        metodo de pago de empleado.
    --}}
    <li class="{{ Request::routeIs('configuracion.metodos_pago.*') ? 'active' : '' }}">
        <a href="{{ route('configuracion.metodos_pago.index') }}">
            <div>
                <i class="bi bi-credit-card-2-front"></i>
                <span>Métodos de pago</span>
            </div>
        </a>
    </li>

                </ul>
            </li>

            {{-- =====================================================
                INVENTARIO
            ====================================================== --}}
            <li class="menu menu-heading">
                <div class="heading">
                    <i class="bi bi-box-seam"></i>
                    <span>INVENTARIO</span>
                </div>
            </li>

            @php
                $inventarioActivo = Request::is('inventario/*')
                    || Request::routeIs('procesos.ordenes_trabajo.procesos.materiales.*');
            @endphp

            <li class="menu {{ $inventarioActivo ? 'active' : '' }}">

                <a
                    href="#inventario"
                    data-bs-toggle="collapse"
                    aria-expanded="{{ $inventarioActivo ? 'true' : 'false' }}"
                    class="dropdown-toggle"
                >
                    <div>
                        <i class="bi bi-boxes"></i>
                        <span>Inventario</span>
                    </div>

                    <div>
                        <i class="bi bi-chevron-right"></i>
                    </div>
                </a>

                <ul
                    class="collapse submenu list-unstyled {{ $inventarioActivo ? 'show' : '' }}"
                    id="inventario"
                    data-bs-parent="#accordionExample"
                >

                    <li class="{{ Request::routeIs('inventario.proveedores.*') ? 'active' : '' }}">
                        <a href="{{ route('inventario.proveedores.index') }}">
                            <div>
                                <i class="bi bi-truck"></i>
                                <span>Proveedores</span>
                            </div>
                        </a>
                    </li>

                    <li class="{{ Request::routeIs('inventario.productos.*') ? 'active' : '' }}">
                        <a href="{{ route('inventario.productos.index') }}">
                            <div>
                                <i class="bi bi-box-seam"></i>
                                <span>Materiales</span>
                            </div>
                        </a>
                    </li>

                    <li class="{{ Request::routeIs('inventario.compras.*') ? 'active' : '' }}">
                        <a href="{{ route('inventario.compras.index') }}">
                            <div>
                                <i class="bi bi-bag-plus"></i>
                                <span>Compras</span>
                            </div>
                        </a>
                    </li>

                    <li class="{{ Request::routeIs('inventario.movimientos.*') ? 'active' : '' }}">
                        <a href="{{ route('inventario.movimientos.index') }}">
                            <div>
                                <i class="bi bi-arrow-left-right"></i>
                                <span>Almacén</span>
                            </div>
                        </a>
                    </li>

                </ul>
            </li>

            {{-- =====================================================
                PRODUCCIÓN
            ====================================================== --}}
            <li class="menu menu-heading">
                <div class="heading">
                    <i class="bi bi-bar-chart-line"></i>
                    <span>PRODUCCIÓN</span>
                </div>
            </li>

            @php
                $produccionActiva = Request::routeIs('procesos.recuperaciones.*')
                    || Request::routeIs('procesos.producciones.*')
                    || Request::routeIs('procesos.valoraciones_oro.*')
                    || Request::is('produccion/*');
            @endphp

            {{--
                Producciones registra los productos obtenidos por orden y,
                cuando corresponde, por proceso. Recuperaciones de oro anota
                aparte los gramos de cada partida, y Valoraciones calcula el
                valor de esas recuperaciones con el precio del gramo.

                El prefijo de la url de la pantalla es procesos/ y no
                produccion/, y el motivo es el mismo que en el resto del
                menu: el menu se llama por el area y la url por la parte del
                codigo que la atiende. Aqui los dos coinciden, porque las
                pantallas de produccion las lleva el codigo de procesos.
            --}}
            <li class="menu {{ $produccionActiva ? 'active' : '' }}">

                <a
                    href="#produccion"
                    data-bs-toggle="collapse"
                    aria-expanded="{{ $produccionActiva ? 'true' : 'false' }}"
                    class="dropdown-toggle"
                >
                    <div>
                        <i class="bi bi-gear"></i>
                        <span>Producción</span>
                    </div>

                    <div>
                        <i class="bi bi-chevron-right"></i>
                    </div>
                </a>

                <ul
                    class="collapse submenu list-unstyled {{ $produccionActiva ? 'show' : '' }}"
                    id="produccion"
                    data-bs-parent="#accordionExample"
                >

                    <li class="{{ Request::routeIs('procesos.producciones.*') ? 'active' : '' }}">
                        <a href="{{ route('procesos.producciones.index') }}">
                            <div>
                                <i class="bi bi-box-seam"></i>
                                <span>Producciones</span>
                            </div>
                        </a>
                    </li>

                    <li class="{{ Request::routeIs('procesos.recuperaciones.*') ? 'active' : '' }}">
                        <a href="{{ route('procesos.recuperaciones.index') }}">
                            <div>
                                <i class="bi bi-gem"></i>
                                <span>Recuperaciones de Oro</span>
                            </div>
                        </a>
                    </li>

                    <li class="{{ Request::routeIs('procesos.valoraciones_oro.*') ? 'active' : '' }}">
                        <a href="{{ route('procesos.valoraciones_oro.index') }}">
                            <div>
                                <i class="bi bi-calculator"></i>
                                <span>Valoraciones de Oro</span>
                            </div>
                        </a>
                    </li>

                </ul>
            </li>

            {{-- =====================================================
                COMERCIAL
            ====================================================== --}}
            <li class="menu menu-heading">
                <div class="heading">
                    <i class="bi bi-briefcase"></i>
                    <span>COMERCIAL</span>
                </div>
            </li>

            @php($comercialActivo = Request::routeIs('comercial.cotizaciones.*'))
            <li class="menu {{ $comercialActivo ? 'active' : '' }}">
                <a href="#gestion-comercial" data-bs-toggle="collapse"
                   aria-expanded="{{ $comercialActivo ? 'true' : 'false' }}" class="dropdown-toggle">
                    <div>
                        <i class="bi bi-graph-up-arrow"></i>
                        <span>Gestión Comercial</span>
                    </div>
                    <div><i class="bi bi-chevron-right"></i></div>
                </a>
                <ul class="collapse submenu list-unstyled {{ $comercialActivo ? 'show' : '' }}"
                    id="gestion-comercial" data-bs-parent="#accordionExample">
                    @can('cotizaciones.view')
                        <li class="{{ Request::routeIs('comercial.cotizaciones.*') ? 'active' : '' }}">
                            <a href="{{ route('comercial.cotizaciones.index') }}">
                                <div><i class="bi bi-file-earmark-text"></i><span>Cotizaciones</span></div>
                            </a>
                        </li>
                    @endcan
                </ul>
            </li>

            {{-- =====================================================
                CAJA E INGRESOS
            ====================================================== --}}
            <li class="menu menu-heading">
                <div class="heading">
                    <i class="bi bi-cash-stack"></i>
                    <span>CAJA E INGRESOS</span>
                </div>
            </li>

            {{-- Registro y conciliación del dinero que entra, sale y se cuenta en cada caja. --}}
            @can('cobros.view')
                <li class="menu {{ Request::routeIs('procesos.cobros.*') ? 'active' : '' }}">
                    <a href="{{ route('procesos.cobros.index') }}" aria-expanded="false" class="dropdown-toggle">
                        <div>
                            <i class="bi bi-cash-coin"></i>
                            <span>Cobros</span>
                        </div>
                    </a>
                </li>
            @endcan

            @can('movimientos_caja.view')
            <li class="menu {{ Request::routeIs('procesos.movimientos_caja.*') ? 'active' : '' }}">
                <a href="{{ route('procesos.movimientos_caja.index') }}" aria-expanded="false" class="dropdown-toggle">
                    <div>
                        <i class="bi bi-arrow-left-right"></i>
                        <span>Movimientos de caja</span>
                    </div>
                </a>
            </li>
            @endcan

            @can('cierres_caja.view')
            <li class="menu {{ Request::routeIs('procesos.cierres_caja.*') ? 'active' : '' }}">
                <a href="{{ route('procesos.cierres_caja.index') }}" aria-expanded="false" class="dropdown-toggle">
                    <div>
                        <i class="bi bi-safe2"></i>
                        <span>Cierres de caja</span>
                    </div>
                </a>
            </li>
            @endcan

            {{-- =====================================================
                REPORTES
            ====================================================== --}}
            <li class="menu menu-heading">
                <div class="heading">
                    <i class="bi bi-file-earmark-bar-graph"></i>
                    <span>REPORTES</span>
                </div>
            </li>

            @can('reportes.view')
            <li class="menu {{ Request::routeIs('reportes.*') ? 'active' : '' }}">
                <a href="{{ route('reportes.index') }}" aria-expanded="false" class="dropdown-toggle">
                    <div>
                        <i class="bi bi-file-earmark-text"></i>
                        <span>Reportes</span>
                    </div>
                </a>
            </li>
            @endcan
        </ul>
    </nav>
</div>
