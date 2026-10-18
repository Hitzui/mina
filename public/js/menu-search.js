(function () {
    'use strict';

    function iniciar() {
        const root = document.querySelector('[data-menu-search]');
        if (!root) return;

        const panel = root.querySelector('#mina-menu-panel');
        const input = root.querySelector('#mina-menu-query');
        const results = root.querySelector('#mina-menu-results');
        const heading = root.querySelector('[data-search-heading]');
        const count = root.querySelector('[data-search-count]');
        const empty = root.querySelector('[data-search-empty]');
        const triggers = [root.querySelector('[data-search-open]'), root.querySelector('[data-search-quick]')];
        const destinations = JSON.parse(root.querySelector('[data-search-destinations]').textContent);
        const overlay = document.createElement('div');
        overlay.className = 'mina-menu-backdrop';
        overlay.hidden = true;
        document.body.append(overlay);
        let opener = triggers[0];
        let selected = -1;

        function normalize(text) {
            return String(text).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim();
        }

        const entries = destinations.map(item => ({...item,
            normalizedTitle: normalize(item.title),
            searchWords: normalize([item.title, item.group, item.description, item.keywords].join(' ')).split(' '),
        }));

        function activate(index) {
            const links = Array.from(results.querySelectorAll('a'));
            selected = links.length ? (index + links.length) % links.length : -1;
            links.forEach((link, i) => {
                link.classList.toggle('is-active', i === selected);
                link.setAttribute('aria-selected', String(i === selected));
            });
            if (selected >= 0) {
                input.setAttribute('aria-activedescendant', links[selected].id);
                links[selected].scrollIntoView({block: 'nearest'});
            } else {
                input.removeAttribute('aria-activedescendant');
            }
        }

        function render() {
            const query = normalize(input.value);
            const ignored = new Set(['de', 'del', 'el', 'la', 'los', 'las', 'un', 'una', 'y', 'para', 'en', 'quiero', 'necesito', 'como', 'donde']);
            const tokens = query.split(' ').filter(token => token && !ignored.has(token));
            let matches;
            if (!query) {
                matches = entries.filter(item => item.quick);
                heading.textContent = 'Accesos rápidos';
                results.classList.add('is-quick');
            } else {
                matches = entries.filter(item => tokens.every(token => item.searchWords.some(word => token.length <= 2 ? word === token : word.includes(token))));
                const score = item => (item.normalizedTitle === query ? 100 : 0)
                    + (item.normalizedTitle.startsWith(query) ? 40 : 0)
                    + tokens.reduce((sum, token) => sum + (item.normalizedTitle.includes(token) ? 10 : 0), 0);
                matches.sort((a, b) => score(b) - score(a));
                heading.textContent = 'Resultados del menú';
                results.classList.remove('is-quick');
            }
            const shown = matches.slice(0, 14);
            count.textContent = query ? `${matches.length} ${matches.length === 1 ? 'opción disponible' : 'opciones disponibles'}` : 'Tareas habituales';
            results.replaceChildren();
            shown.forEach((item, index) => {
                const link = document.createElement('a');
                link.href = item.url;
                link.className = 'mina-menu-result';
                link.id = `mina-menu-option-${index}`;
                link.setAttribute('role', 'option');
                link.setAttribute('aria-selected', 'false');
                link.tabIndex = -1;
                const icon = document.createElement('i');
                icon.className = `bi ${item.icon} mina-result-icon`;
                icon.setAttribute('aria-hidden', 'true');
                const content = document.createElement('span');
                content.className = 'mina-result-content';
                const title = document.createElement('strong');
                title.textContent = item.title;
                const group = document.createElement('span');
                group.className = 'mina-result-group';
                group.textContent = item.group;
                const description = document.createElement('span');
                description.className = 'mina-result-description';
                description.textContent = item.description;
                content.append(title, group, description);
                const arrow = document.createElement('i');
                arrow.className = 'bi bi-arrow-up-right mina-result-arrow';
                arrow.setAttribute('aria-hidden', 'true');
                link.append(icon, content, arrow);
                link.addEventListener('mousemove', () => {
                    if (selected !== index) activate(index);
                });
                results.append(link);
            });
            selected = -1;
            input.removeAttribute('aria-activedescendant');
            empty.hidden = shown.length > 0;
            if (!query && shown.length === 0) {
                empty.querySelector('strong').textContent = 'Busque una opción del menú';
            } else {
                empty.querySelector('strong').textContent = 'No encontramos esa opción';
            }
            if (matches.length > shown.length) count.textContent += ` · mostrando ${shown.length}; escriba algo más para precisar`;
        }

        function open(trigger, quick = false) {
            opener = trigger;
            if (quick) input.value = '';
            panel.hidden = false;
            overlay.hidden = false;
            document.body.classList.add('mina-menu-open');
            triggers.forEach(button => button.setAttribute('aria-expanded', 'true'));
            input.setAttribute('aria-expanded', 'true');
            render();
            input.focus();
            input.select();
        }

        function close(restoreFocus = false) {
            panel.hidden = true;
            overlay.hidden = true;
            document.body.classList.remove('mina-menu-open');
            triggers.forEach(button => button.setAttribute('aria-expanded', 'false'));
            input.setAttribute('aria-expanded', 'false');
            input.removeAttribute('aria-activedescendant');
            if (restoreFocus) opener.focus();
        }

        triggers[0].addEventListener('click', () => open(triggers[0]));
        triggers[1].addEventListener('click', () => open(triggers[1], true));
        root.querySelector('[data-search-close]').addEventListener('click', () => close(true));
        overlay.addEventListener('click', () => close(true));
        input.addEventListener('input', render);
        results.addEventListener('click', event => {
            if (event.target.closest('a')) close();
        });
        root.querySelector('[data-search-form]').addEventListener('submit', event => {
            event.preventDefault();
            const links = results.querySelectorAll('a');
            if (links.length) links[selected >= 0 ? selected : 0].click();
        });
        input.addEventListener('keydown', event => {
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                const length = results.querySelectorAll('a').length;
                activate(selected < 0 ? (event.key === 'ArrowDown' ? 0 : length - 1) : selected + (event.key === 'ArrowDown' ? 1 : -1));
            }
        });
        document.addEventListener('keydown', event => {
            if ((event.ctrlKey || event.metaKey) && (event.key === '/' || event.code === 'Slash')) {
                event.preventDefault();
                if (!document.querySelector('.modal.show, .offcanvas.show')) open(triggers[0]);
            } else if (event.key === 'Escape' && !panel.hidden) {
                event.preventDefault();
                close(true);
            }
        });
        document.addEventListener('focusin', event => {
            if (!panel.hidden && !root.contains(event.target)) close();
        });
        window.addEventListener('pageshow', () => close());
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar);
    else iniciar();
})();
