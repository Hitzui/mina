<x-base-layout :scrollspy="false">
    <x-slot:pageTitle>{{ $title }}</x-slot:pageTitle>
    <x-breadcrumb :items="$breadcrumbs"/>
    <x-slot:headerFiles></x-slot:headerFiles>
    <div class="row layout-top-spacing">
        <div class="col-12">
            <div class="widget-content widget-content-area br-8">
                <h4 class="mb-4">Editar borrador {{ $cotizacion->codigo }}</h4>
                @include('comercial.cotizaciones._form')
            </div>
        </div>
    </div>
    <x-slot:footerFiles>@vite(['resources/assets/js/select2/select2-init.js'])</x-slot>
</x-base-layout>
