<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Sistema de Laboratorio' }}
    </x-slot>

    <x-slot:headerFiles>
    </x-slot>

    {{ $slot }}

    <x-slot:footerFiles>
    </x-slot>

</x-base-layout>
