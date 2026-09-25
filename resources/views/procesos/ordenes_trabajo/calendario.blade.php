<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Sistema de Laboratorio' }}
    </x-slot>
    <x-breadcrumb :items="$breadcrumbs"/>
    <x-slot:headerFiles>
        <!--  BEGIN CUSTOM STYLE FILE  -->
        <!-- STYLESHEETS -->

        <link href="{{ asset('plugins/fullcalendar/skeleton.css') }}" rel="stylesheet" />
        <link href="{{ asset('plugins/fullcalendar/bootstrap.theme.css') }}" rel="stylesheet" />
        @vite(['resources/scss/light/assets/components/modal.scss'])
        @vite(['resources/scss/dark/plugins/fullcalendar/custom-fullcalendar.scss'])
        @vite(['resources/scss/dark/assets/components/modal.scss'])
        <!--  END CUSTOM STYLE FILE  -->
    </x-slot>

    <div class="row layout-top-spacing layout-spacing" id="cancel-row">
        <div class="col-xl-12 col-lg-12 col-md-12">
            <div class="calendar-container">
                <div id="calendar"></div>
            </div>
        </div>
    </div>

    <x-slot:footerFiles>
        <script src="{{asset('plugins/fullcalendar/fullcalendar.global.js')}}"></script>
        <script src="{{asset('plugins/fullcalendar/bootstrap.global.js')}}"></script>
        <script src="{{asset('plugins/fullcalendar/locales-all/global.js')}}"></script>
        <script src="{{asset('plugins/uuid/uuid4.min.js')}}"></script>
        <script src="{{asset('js/ordenes/calendario.js')}}"></script>
    </x-slot>

</x-base-layout>
