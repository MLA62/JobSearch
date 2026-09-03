(() => {
    'use strict';
    const root = document.documentElement;
    const updateViewport = () => {
        root.style.setProperty('--viewport-height', (window.visualViewport?.height || window.innerHeight) + 'px');
        root.style.setProperty('--viewport-width', (window.visualViewport?.width || window.innerWidth) + 'px');
        root.style.setProperty('--topbar-height', (document.querySelector('.topbar')?.getBoundingClientRect().height || 0) + 'px');
    };
    updateViewport();
    window.addEventListener('resize', updateViewport);
    window.visualViewport?.addEventListener('resize', updateViewport);
    const topbar = document.querySelector('.topbar');
    if (topbar) new ResizeObserver(updateViewport).observe(topbar);

    // Navigation targets keep link semantics; command links get button styling.
    document.querySelectorAll('.actions a, .section-head > a, .auth-card > p > a, .help-quickstart article > a, .calendar-add, .filter-note > a:last-child').forEach(link => {
        link.classList.add('button');
    });

    // Editors retain their original form elements and listeners.
    document.querySelectorAll('.split').forEach(workspace => {
        const children = [...workspace.children];
        if (!children.some(child => child.matches('.table-wrap, .cards, .application-list'))) return;
        workspace.classList.add('layout-workspace');
        children.forEach(panel => {
            if (!panel.matches('.panel') || panel.matches('.table-wrap') || !panel.querySelector('form')) return;
            const heading = panel.querySelector(':scope > h2, :scope > .section-head h2');
            if (!heading) return;
            const details = document.createElement('details');
            details.className = 'workspace-editor';
            const summary = document.createElement('summary');
            const editing = [...new URLSearchParams(location.search)].some(([key, value]) => key.startsWith('edit') && value !== '' && value !== '0');
            const target = location.hash ? document.getElementById(decodeURIComponent(location.hash.slice(1))) : null;
            details.open = editing || !!(target && panel.contains(target)) || !!panel.querySelector('.alert.danger');
            panel.before(details);
            summary.append(heading);
            details.append(summary, panel);
            if (panel.id) {
                const openForHash = () => { if (location.hash === '#' + panel.id) details.open = true; };
                window.addEventListener('hashchange', openForHash);
            }
        });
    });

    document.querySelectorAll('.table-wrap table').forEach(table => {
        const headers = [...(table.tHead?.rows[0]?.cells || [])];
        if (!headers.length || table.querySelector('th[rowspan]')) return;
        if (headers[0].classList.contains('bulk-select-column')) {
            [...table.tBodies].flatMap(body => [...body.rows]).forEach(row => {
                if (row.cells.length === 1 && row.cells[0].colSpan > 1) {
                    row.cells[0].colSpan = headers.length;
                } else if (row.cells.length === headers.length - 1 && !row.cells[0].classList.contains('bulk-select-column')) {
                    row.insertCell(0).className = 'bulk-select-column';
                }
            });
        }
        table.classList.add('layout-table');
        headers.forEach((header, index) => {
            const field = header.querySelector('[name="sf_field"]')?.value || '';
            const label = header.querySelector('.sf-head > span')?.textContent.trim() || header.textContent.trim();
            const selection = header.classList.contains('bulk-select-column');
            const date = /(^|_)(date|created_at|updated_at|due_at|starts_at|applied_at)$/.test(field);
            const primary = /^(title|name|job|job_title)$/.test(field);
            const actions = !selection && !field && index === headers.length - 1 &&
                !!table.tBodies[0]?.querySelector('td:last-child button, td:last-child a');
            const width = selection ? 36 : date ? 144 : primary ? 240 : actions ? 160 : field === 'match' ? 72 : 120;
            header.style.setProperty('--column-min', width + 'px');
            if (date) header.classList.add('layout-date');
            if (actions) header.classList.add('layout-actions');
            [...table.tBodies].flatMap(body => [...body.rows]).forEach(row => {
                const cell = row.cells[index];
                if (!cell || cell.colSpan > 1) return;
                cell.dataset.label = label;
                if (date) cell.classList.add('layout-date');
                if (primary) cell.classList.add('layout-primary');
                if (actions) cell.classList.add('layout-actions');
                if (primary) cell.title = cell.textContent.trim();
            });
        });
    });

    // Keep filter popovers inside the visible area, including phone landscape.
    const placeFilter = menu => {
        if (!menu.open) return;
        const button = menu.querySelector('.sf-button');
        const form = menu.querySelector('.sf-form');
        if (!button || !form) return;
        const rect = button.getBoundingClientRect();
        const height = window.visualViewport?.height || innerHeight;
        const width = Math.min(320, innerWidth - 24);
        const top = Math.max(12, Math.min(rect.bottom + 8, height - Math.min(360, height - 24)));
        form.style.width = width + 'px';
        form.style.left = Math.max(12, Math.min(rect.right - width, innerWidth - width - 12)) + 'px';
        form.style.top = top + 'px';
        form.style.maxHeight = (height - top - 12) + 'px';
    };
    document.addEventListener('toggle', event => {
        if (event.target.matches?.('.sf-menu')) placeFilter(event.target);
    }, true);
    window.addEventListener('resize', () => document.querySelectorAll('.sf-menu[open]').forEach(placeFilter));
    window.addEventListener('scroll', () => document.querySelectorAll('.sf-menu[open]').forEach(placeFilter), true);
    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;
        document.querySelectorAll('.sf-menu[open]').forEach(menu => {
            menu.open = false;
            menu.querySelector('summary')?.focus();
        });
    });
    document.addEventListener('pointerdown', event => {
        document.querySelectorAll('.sf-menu[open]').forEach(menu => {
            if (!menu.contains(event.target)) menu.open = false;
        });
    });
})();
