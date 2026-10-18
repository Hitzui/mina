@php($destinosMenu = app(\App\Services\NavegacionMenu::class)->actuales())
<div class="mina-menu-search" data-menu-search>
    <button type="button" class="mina-search-trigger" data-search-open aria-label="Buscar en el menú" aria-haspopup="dialog" aria-controls="mina-menu-panel" aria-expanded="false">
        <i class="bi bi-search" aria-hidden="true"></i>
        <span class="mina-search-placeholder">Buscar en el menú…</span>
        <kbd>Ctrl + /</kbd>
    </button>
    <button type="button" class="mina-quick-trigger" data-search-quick aria-label="Abrir accesos rápidos" aria-haspopup="dialog" aria-controls="mina-menu-panel" aria-expanded="false">
        <i class="bi bi-lightning-charge" aria-hidden="true"></i><span>Accesos rápidos</span>
    </button>
    <section id="mina-menu-panel" class="mina-menu-panel" role="dialog" aria-labelledby="mina-menu-title" hidden>
        <div class="mina-menu-panel-heading">
            <div><span class="mina-menu-eyebrow">NAVEGACIÓN MINA</span><h2 id="mina-menu-title">¿Qué necesita hacer?</h2></div>
            <button type="button" class="mina-menu-close" data-search-close aria-label="Cerrar buscador"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
        </div>
        <form role="search" class="mina-menu-form" data-search-form>
            <label for="mina-menu-query" class="visually-hidden">Buscar pantallas y tareas del menú</label>
            <i class="bi bi-search" aria-hidden="true"></i>
            <input id="mina-menu-query" type="search" placeholder="Pruebe: cobrar, acuerdos, almacén, oro…" autocomplete="off" spellcheck="false"
                   role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="mina-menu-results" aria-describedby="mina-menu-help">
        </form>
        <div class="mina-menu-result-heading"><h3 data-search-heading>Accesos rápidos</h3><span data-search-count aria-live="polite" aria-atomic="true"></span></div>
        <div id="mina-menu-results" class="mina-menu-results" role="listbox" aria-label="Destinos del menú"></div>
        <div class="mina-menu-empty" data-search-empty hidden><i class="bi bi-search" aria-hidden="true"></i><strong>No encontramos esa opción</strong><p>Pruebe con otra palabra, como «OT», «cobrar» o «materiales». Solo aparecen las tareas disponibles para su usuario.</p></div>
        <div class="mina-menu-help" id="mina-menu-help"><span><kbd>↑</kbd> <kbd>↓</kbd> elegir · <kbd>Enter</kbd> abrir · <kbd>Esc</kbd> cerrar</span><span>Busque pantallas y tareas del sistema</span></div>
    </section>
    <script type="application/json" data-search-destinations>{!! json_encode($destinosMenu, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>
</div>
