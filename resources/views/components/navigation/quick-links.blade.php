@php($accesosMenu = collect(app(\App\Services\NavegacionMenu::class)->actuales())->where('quick', true))
@if($accesosMenu->isNotEmpty())
<section class="mina-dashboard-shortcuts mb-3" aria-labelledby="mina-shortcuts-title">
    <div class="mina-shortcuts-heading"><div><h2 id="mina-shortcuts-title"><i class="bi bi-lightning-charge" aria-hidden="true"></i> Accesos rápidos</h2><p>Las tareas del día, a un clic.</p></div><span>También puede usar el buscador superior</span></div>
    <div class="mina-shortcuts-grid">
        @foreach($accesosMenu as $acceso)
            <a href="{{ $acceso['url'] }}" class="mina-shortcut-link"><i class="bi {{ $acceso['icon'] }}" aria-hidden="true"></i><span>{{ $acceso['title'] }}</span><i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
        @endforeach
    </div>
</section>
@endif
