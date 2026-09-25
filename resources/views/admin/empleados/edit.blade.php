<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Editar Empleado' }}
    </x-slot>
    <x-slot name="headerFiles">
    </x-slot>
    <x-breadcrumb :items="$breadcrumbs"/>

    <div class="row layout-top-spacing">

        <div class="col-xl-12 col-lg-12 col-sm-12">

            <div class="widget-content widget-content-area br-8">

                <form
                    action="{{ route('admin.empleados.update', $empleado) }}"
                    method="POST"
                >

                    @csrf

                    @method('PUT')

                    @include('admin.empleados._form')

                </form>

            </div>

        </div>

    </div>

    <x-slot name="footerFiles">
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
