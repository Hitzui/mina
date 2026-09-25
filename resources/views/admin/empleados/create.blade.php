<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Ingresar Empleado' }}
    </x-slot>
    <x-slot name="headerFiles">
        @vite(['resources/scss/light/plugins/select2/custom-select2.scss'])
    </x-slot>
    <x-breadcrumb :items="$breadcrumbs"/>

    <div class="row layout-top-spacing">

        <div class="col-xl-12 col-lg-12 col-sm-12">

            <div class="widget-content widget-content-area br-8">

                <form
                    action="{{ route('admin.empleados.store') }}"
                    method="POST"
                >

                    @csrf

                    @include('admin.empleados._form')

                </form>

            </div>

        </div>

    </div>

    <x-slot name="footerFiles">
        @vite(['resources/assets/js/select2/select2-init.js'])
        <script>
            $(function(){
               flatpickr("#fecha_ingreso",{
                   locale: 'es',
                   altFormat: "F j, Y",
                   dateFormat: "Y-m-d",
                   defaultDate: new Date(),
               });
            });
        </script>
    </x-slot>
</x-base-layout>
