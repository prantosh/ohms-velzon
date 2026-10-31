/*
|--------------------------------------------------------------------------
| SERVER PAGER -- numbered page navigation + "Showing x to y of z entries"
| for the AJAX-paginated CRUD dashboards, styled like the DataTables-based
| Diagnostic Invoice dashboard (Previous 1 2 3 ... N Next).
|
| Each page keeps its own Previous / Next buttons (#prevPage / #nextPage)
| and their handlers; this only fills the page-number span (#pageNumber)
| between them, restyles the buttons, and writes the info line. Called from
| a page's load function as renderPager(result.pagination, loadFn).
|
| For pages with several pagers or non-standard ids (tabs), pass a suffix
| string (pageNumber{suffix} etc.) or {number, info, prev, next, perPage}.
|--------------------------------------------------------------------------
*/
(function () {

    const loaders = {};

    function pageWindow(current, last) {

        if (last <= 7) {
            return Array.from({ length: last }, (_, i) => i + 1);
        }

        const pages = [1];

        let start = Math.max(2, current - 1);
        let end = Math.min(last - 1, current + 1);

        if (current <= 3) { start = 2; end = 4; }
        if (current >= last - 2) { start = last - 3; end = last - 1; }

        if (start > 2) pages.push('...');

        for (let i = start; i <= end; i++) pages.push(i);

        if (end < last - 1) pages.push('...');

        pages.push(last);

        return pages;
    }

    function el(ref) {
        return typeof ref === 'string' ? document.getElementById(ref) : (ref || null);
    }

    // targets: undefined for the standard ids, a suffix string for pageNumber{suffix}
    // style ids, or {number, info, prev, next, perPage} (ids or elements) otherwise.
    function resolve(targets) {

        if (targets && typeof targets === 'object') {
            return {
                key: typeof targets.number === 'string' ? targets.number : (targets.number && targets.number.id) || '',
                number: el(targets.number), info: el(targets.info),
                prev: el(targets.prev), next: el(targets.next),
                perPage: targets.perPage
            };
        }

        const suffix = targets || '';

        return {
            key: 'pageNumber' + suffix,
            number: el('pageNumber' + suffix), info: el('pagination-info' + suffix),
            prev: el('prevPage' + suffix), next: el('nextPage' + suffix),
            perPage: null
        };
    }

    window.renderPager = function (pagination, loader, targets) {

        const t = resolve(targets);

        const current = parseInt(pagination.current_page) || 1;
        const last = Math.max(parseInt(pagination.last_page) || 1, 1);
        const total = parseInt(pagination.total) || 0;

        const perPageEl = document.getElementById('perPage');
        const perPage = parseInt(pagination.per_page)
            || parseInt(t.perPage)
            || (perPageEl ? parseInt(perPageEl.value) : 0)
            || 10;

        loaders[t.key] = loader;

        if (t.info) {

            const from = total === 0 ? 0 : (pagination.from || (current - 1) * perPage + 1);
            const to = total === 0 ? 0 : (pagination.to || Math.min(current * perPage, total));

            t.info.innerText = `Showing ${from} to ${to} of ${total} entries`;
        }

        if (t.prev) {
            t.prev.className = 'btn btn-sm btn-outline-primary' + (t.prev.dataset.tab ? ' prevPage-btn' : '');
            t.prev.disabled = current <= 1;
        }

        if (t.next) {
            t.next.className = 'btn btn-sm btn-outline-primary' + (t.next.dataset.tab ? ' nextPage-btn' : '');
            t.next.disabled = current >= last;
        }

        if (t.number) {

            t.number.className = 'd-inline-flex align-items-center gap-1';

            t.number.innerHTML = pageWindow(current, last).map(function (p) {

                if (p === '...') {
                    return '<span class="px-1 text-muted">&hellip;</span>';
                }

                return `<button type="button"
                            class="btn btn-sm pager-page ${p === current ? 'btn-primary' : 'btn-outline-primary'}"
                            data-page="${p}"
                            data-key="${t.key}">${p}</button>`;

            }).join('');
        }
    };

    document.addEventListener('click', function (e) {

        const btn = e.target.closest('.pager-page');

        if (!btn) return;

        const loader = loaders[btn.dataset.key];

        if (typeof loader === 'function') {
            loader(parseInt(btn.dataset.page));
        }
    });

})();
