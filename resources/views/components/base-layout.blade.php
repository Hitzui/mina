{{--

/**
*
* Created a new component <x-base-layout/>.
*
*/

--}}

@php
    $isBoxed = layoutConfig()['boxed'];
    $isAltMenu = layoutConfig()['alt-menu'];
@endphp
    <!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, shrink-to-fit=no">
    <title>{{ $pageTitle }}</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}"/>
    @vite(['resources/scss/layouts/modern-light-menu/light/loader.scss'])

    @vite(['resources/layouts/modern-light-menu/loader.js'])

    <link href="https://fonts.googleapis.com/css?family=Nunito:400,600,700" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="{{asset('plugins/bootstrap/bootstrap.min.css')}}">
    <link rel="stylesheet" type="text/css" href="{{asset('plugins/font-icons/fontawesome/css/all.min.css')}}">
    <link rel="stylesheet" type="text/css" href="{{asset('plugins/flatpickr/flatpickr.css')}}">
    <link rel="stylesheet" href='{{ asset('plugins/bootstrap-icon/bootstrap-icons.min.css') }}' />
    @vite(['resources/scss/light/assets/main.scss', 'resources/scss/dark/assets/main.scss'])
    {{--
        Estilos de las tablas: los botones de exportar y el
        comportamiento en pantallas pequeñas. Se carga siempre, y no solo
        en las paginas con tabla, porque el layout es comun a todas.
    --}}
    @vite(['resources/scss/light/plugins/table/datatable/datatable-movil.scss'])

    @if (
            !Request::routeIs('404') &&
            !Request::routeIs('maintenance') &&
            !Request::routeIs('signin') &&
            !Request::routeIs('signup') &&
            !Request::routeIs('lockscreen') &&
            !Request::routeIs('password-reset') &&
            !Request::routeIs('2Step') &&

            // Real Logins
            !Request::routeIs('login')
        )
        @if ($scrollspy == 1)
            @vite(['resources/scss/light/assets/scrollspyNav.scss', 'resources/scss/dark/assets/scrollspyNav.scss'])
        @endif
        <link rel="stylesheet" type="text/css" href="{{asset('plugins/waves/waves.min.css')}}">
        <link rel="stylesheet" type="text/css" href="{{asset('plugins/highlight/styles/monokai-sublime.css')}}">
        @vite([ 'resources/scss/light/plugins/perfect-scrollbar/perfect-scrollbar.scss'])


        @vite([
             'resources/scss/layouts/modern-light-menu/light/structure.scss',
             'resources/scss/layouts/modern-light-menu/dark/structure.scss',
         ])

    @endif

    <!-- BEGIN GLOBAL MANDATORY STYLES -->
    {{$headerFiles}}
    <!-- END GLOBAL MANDATORY STYLES -->

</head>
<body @class([
        // 'layout-dark' => $isDark,
        'layout-boxed' => $isBoxed,
        'alt-menu' => $isAltMenu || Request::routeIs('collapsibleMenu'),
        'error' => Request::routeIs('404'),
        'maintanence' => Request::routeIs('maintenance'),
    ]) @if ($scrollspy == 1) {{ $scrollspyConfig }} @else {{''}} @endif   @if (Request::routeIs('fullWidth')) layout="full-width"  @endif >

<!-- BEGIN LOADER -->
<x-layout-loader/>
<!--  END LOADER -->

{{--

/*
*
*   Check if the routes are not single pages ( which does not contains sidebar or topbar  ) such as :-
*   - 404
*   - maintenance
*   - authentication
*
*/

--}}

@if (
        !Request::routeIs('404') &&
        !Request::routeIs('maintenance') &&
        !Request::routeIs('signin') &&
        !Request::routeIs('signup') &&
        !Request::routeIs('lockscreen') &&
        !Request::routeIs('password-reset') &&
        !Request::routeIs('2Step') &&

        // Real Logins
        !Request::routeIs('login')
    )

    <x-navbar.style-vertical-menu
        classes="{{ ($isBoxed ? 'container-xxl' : '') }}"
    />

    <!--  BEGIN MAIN CONTAINER  -->
    <div class="main-container " id="container">

        <!--  BEGIN LOADER  -->
        <x-layout-overlay/>
        <!--  END LOADER  -->

        <x-menu.vertical-menu/>


        <!--  BEGIN CONTENT AREA  -->
        <div id="content" class="main-content {{(Request::routeIs('blank') ? 'ms-0 mt-0' : '')}}">

            @if ($scrollspy == 1)
                <div class="container">
                    <div class="container">
                        {{ $slot }}
                    </div>
                </div>
            @else
                <div class="layout-px-spacing">
                    <div class="middle-content {{($isBoxed ? 'container-xxl' : '')}} p-0">
                        {{ $slot }}
                    </div>
                </div>
            @endif

            <!--  BEGIN FOOTER  -->
            <x-layout-footer/>
            <!--  END FOOTER  -->

        </div>
        <!--  END CONTENT AREA  -->

    </div>
    <!--  END MAIN CONTAINER  -->

@else
    {{ $slot }}
@endif

@if (
        !Request::routeIs('404') &&
        !Request::routeIs('maintenance') &&
        !Request::routeIs('signin') &&
        !Request::routeIs('signup') &&
        !Request::routeIs('lockscreen') &&
        !Request::routeIs('password-reset') &&
        !Request::routeIs('2Step') &&

        // Real Logins
        !Request::routeIs('login')
    )
    <!-- BEGIN GLOBAL MANDATORY STYLES -->
    {{--
        El jQuery sale del proyecto, no de una CDN.

        Antes venía de code.jquery.com, y sin internet no llegaba: entonces el $
        no existía en ninguna pantalla y todo lo que lo usa se caía, que es la
        aplicación entera. El fallo se veía como un críptico
        "$ is not a function" en la consola de una pantalla concreta, sin
        relación aparente con la conexión.

        El archivo de public/plugins/jquery es el mismo jquery 3.7.1 que se
        usaba, byte a byte, y estaba ya en node_modules sin usarse. Con el
        aquí, además de no depender de internet, se carga en el mismo momento
        que los demás scripts normales, antes que los del pie de cada
        pantalla, que es donde viven los que usan el $.
    --}}
    <script src="{{ asset('plugins/jquery/jquery.min.js') }}"></script>
    <script src="{{asset('plugins/bootstrap/bootstrap.bundle.min.js')}}"></script>
    <script src="{{ asset('plugins/table/datatable/dataTables.js') }}"></script>
    <script src="{{ asset('plugins/table/datatable/dataTables.bootstrap5.js') }}"></script>
    {{--
        NO se carga la extension de botones de DataTables.

        El core que trae el theme es el 2.3.8, que registra sus
        extensions con DataTable.feature.register (en singular). Todos los
        botones disponibles en npm, del 2.1.0 al 2.3.6, llaman a
        DataTable.ext.features.register, que en ese core no existe. Al
        cargarlos salia en consola, en cada tabla:

          e.ext.features.register is not a function
          Cannot extend unknown button type: reset

        y el boton de reset se resolvia antes que el resto, asi que la
        tabla se quedaba a medio construir.

        While no haya un build compatible, los botones de exportar no se
        declaran tampoco: declararlos sin que hagan nada es exactamente
        la confianza falsa que se quiere evitar. En cuanto se consiga el
        build del 2.3.8, se anaden aqui los tres scripts y se quita este
        comentario.
    --}}
    <script src="{{ asset('js/datatables/columnas-visibles.js') }}"></script>

    <script src="{{asset('plugins/perfect-scrollbar/perfect-scrollbar.min.js')}}"></script>
    <script src="{{asset('plugins/mousetrap/mousetrap.min.js')}}"></script>
    <script src="{{asset('plugins/waves/waves.min.js')}}"></script>
    <script src="{{asset('plugins/highlight/highlight.pack.js')}}"></script>
    <script src="{{asset('plugins/flatpickr/flatpickr.js')}}"></script>
    <script src="{{asset('plugins/flatpickr/es.js')}}"></script>

    @if ($scrollspy == 1)
        @vite(['resources/assets/js/scrollspyNav.js'])
    @endif

    @sweetAlert

    @vite(['resources/layouts/modern-light-menu/app.js'])
@endif

{{$footerFiles}}

</body>
</html>
