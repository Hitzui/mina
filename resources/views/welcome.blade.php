<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Sistema de Laboratorio' }}
    </x-slot>
    <x-breadcrumb :items="$breadcrumbs"/>
    <x-slot:headerFiles>
    </x-slot>


    <x-slot:footerFiles>
    </x-slot>

</x-base-layout>
