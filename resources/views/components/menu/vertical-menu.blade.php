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

            <li class="menu {{ Request::is('/') ? 'active' : '' }}">
                <a href="{{ url('/') }}" aria-expanded="false" class="dropdown-toggle">
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

                    <li class="{{ Request::routeIs('inventario.productos.*') ? 'active' : '' }}">
                        <a href="{{ route('inventario.productos.index') }}">
                            <div>
                                <i class="bi bi-box-seam"></i>
                                <span>Materiales</span>
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

            <li class="menu">
                <a href="javascript:void(0);" aria-expanded="false" class="dropdown-toggle disabled">
                    <div>
                        <i class="bi bi-gear"></i>
                        <span>Producción</span>
                    </div>
                </a>
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

            <li class="menu">
                <a href="javascript:void(0);" aria-expanded="false" class="dropdown-toggle disabled">
                    <div>
                        <i class="bi bi-graph-up-arrow"></i>
                        <span>Gestión Comercial</span>
                    </div>
                </a>
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

            <li class="menu">
                <a href="javascript:void(0);" aria-expanded="false" class="dropdown-toggle disabled">
                    <div>
                        <i class="bi bi-safe2"></i>
                        <span>Caja e Ingresos</span>
                    </div>
                </a>
            </li>

            {{-- =====================================================
                REPORTES
            ====================================================== --}}
            <li class="menu menu-heading">
                <div class="heading">
                    <i class="bi bi-file-earmark-bar-graph"></i>
                    <span>REPORTES</span>
                </div>
            </li>

            <li class="menu">
                <a href="javascript:void(0);" aria-expanded="false" class="dropdown-toggle disabled">
                    <div>
                        <i class="bi bi-file-earmark-text"></i>
                        <span>Reportes</span>
                    </div>
                </a>
            </li>
        </ul>
    </nav>
</div>
