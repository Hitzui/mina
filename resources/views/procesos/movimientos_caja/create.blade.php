<x-base-layout :scrollspy="false">
    <x-slot:pageTitle>{{ $title }}</x-slot:pageTitle>
    <x-breadcrumb :items="$breadcrumbs"/>
    <x-slot:headerFiles></x-slot:headerFiles>

    <div class="row layout-top-spacing">
        <div class="col-xl-9 col-lg-12">
            <div class="widget-content widget-content-area br-8">
                <h4 class="mb-4">{{ $title }}</h4>
                @include('procesos.movimientos_caja._form')
            </div>
        </div>
    </div>

    <x-slot:footerFiles></x-slot:footerFiles>
</x-base-layout>
