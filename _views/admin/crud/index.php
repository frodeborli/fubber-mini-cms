<?php
$columns = $columns ?? $entity->getListColumns();
$pk = $pk ?? $entity->getPrimaryKeyName();
$baseUrl = '/admin/data/' . \mini\h($slug);
$apiUrl = '/admin/api/data/' . urlencode($slug) . '/';
?>
<div class="app-content-header">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center">
            <h3><?= \mini\h($entity->getPluralTitle()) ?> <small class="text-muted fw-normal" id="crud-total"></small></h3>
            <a href="<?= $baseUrl ?>/create" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg"></i> New <?= \mini\h($entity->getTitle()) ?>
            </a>
        </div>
    </div>
</div>
<div class="app-content">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2 py-2">
                <input type="search" id="crud-search" class="form-control form-control-sm" style="max-width:260px" placeholder="Search <?= \mini\h(strtolower($entity->getPluralTitle())) ?>...">
                <div class="ms-auto d-flex align-items-center gap-2">
                    <select id="crud-perpage" class="form-select form-select-sm" style="width:auto">
                        <option value="10">10</option>
                        <option value="25" selected>25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                    <span class="text-muted" style="font-size:0.8rem;white-space:nowrap" id="crud-info"></span>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped mb-0" id="crud-table">
                        <thead>
                            <tr>
                                <th class="crud-sortable" data-col="<?= \mini\h($pk) ?>" style="width:60px">#</th>
                                <?php foreach ($columns as $col): ?>
                                <th class="crud-sortable" data-col="<?= \mini\h($col) ?>"><?= \mini\h(ucfirst(str_replace('_', ' ', $col))) ?></th>
                                <?php endforeach; ?>
                                <th style="width:100px">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="crud-tbody">
                            <tr><td colspan="<?= count($columns) + 2 ?>" class="text-center text-muted py-4">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-center py-2">
                <nav id="crud-pagination"></nav>
            </div>
        </div>
    </div>
</div>
<script>
(function() {
    var apiUrl = <?= json_encode($apiUrl) ?>;
    var baseUrl = <?= json_encode($baseUrl) ?>;
    var cols = <?= json_encode($columns) ?>;
    var entityTitle = <?= json_encode(strtolower($entity->getTitle())) ?>;

    var state = { page: 1, perPage: 25, search: '', sort: null, dir: 'asc' };
    var debounceTimer = null;

    function esc(str) {
        var d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    function load() {
        var params = new URLSearchParams({
            page: state.page,
            perPage: state.perPage
        });
        if (state.search) params.set('search', state.search);
        if (state.sort) {
            params.set('sort', state.sort);
            params.set('dir', state.dir);
        }

        fetch(apiUrl + '?' + params.toString())
            .then(function(r) { return r.json(); })
            .then(render);
    }

    function render(data) {
        document.getElementById('crud-total').textContent = '(' + data.total + ')';

        var start = (data.page - 1) * data.perPage + 1;
        var end = Math.min(data.page * data.perPage, data.total);
        document.getElementById('crud-info').textContent = data.total > 0
            ? start + '–' + end + ' of ' + data.total
            : 'No results';

        var tbody = document.getElementById('crud-tbody');
        if (data.rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="' + (cols.length + 2) + '" class="text-center text-muted py-4">No ' + esc(entityTitle) + 's found.</td></tr>';
        } else {
            var html = '';
            for (var i = 0; i < data.rows.length; i++) {
                var row = data.rows[i];
                var id = row._id;
                html += '<tr data-href="' + esc(baseUrl) + '/' + esc(id) + '">';
                html += '<td>' + esc(id) + '</td>';
                for (var j = 0; j < cols.length; j++) {
                    html += '<td>' + esc(row[cols[j]] || '') + '</td>';
                }
                html += '<td><div class="d-flex gap-1">';
                html += '<a href="' + esc(baseUrl) + '/' + esc(id) + '/edit" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>';
                html += '<form method="post" action="' + esc(baseUrl) + '/' + esc(id) + '/delete" onsubmit="return confirm(\'Delete this ' + esc(entityTitle) + '?\')">';
                html += '<button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button></form>';
                html += '</div></td></tr>';
            }
            tbody.innerHTML = html;
        }

        renderPagination(data.page, data.pages);
        renderSortIndicators();
    }

    function renderPagination(page, pages) {
        var nav = document.getElementById('crud-pagination');
        if (pages <= 1) { nav.innerHTML = ''; return; }

        var html = '<ul class="pagination pagination-sm mb-0">';
        html += '<li class="page-item' + (page <= 1 ? ' disabled' : '') + '"><a class="page-link" href="#" data-page="' + (page - 1) + '">&laquo;</a></li>';

        var start = Math.max(1, page - 2);
        var end = Math.min(pages, page + 2);
        if (start > 1) html += '<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>' + (start > 2 ? '<li class="page-item disabled"><span class="page-link">…</span></li>' : '');

        for (var i = start; i <= end; i++) {
            html += '<li class="page-item' + (i === page ? ' active' : '') + '"><a class="page-link" href="#" data-page="' + i + '">' + i + '</a></li>';
        }

        if (end < pages) html += (end < pages - 1 ? '<li class="page-item disabled"><span class="page-link">…</span></li>' : '') + '<li class="page-item"><a class="page-link" href="#" data-page="' + pages + '">' + pages + '</a></li>';
        html += '<li class="page-item' + (page >= pages ? ' disabled' : '') + '"><a class="page-link" href="#" data-page="' + (page + 1) + '">&raquo;</a></li>';
        html += '</ul>';
        nav.innerHTML = html;
    }

    function renderSortIndicators() {
        document.querySelectorAll('.crud-sortable').forEach(function(th) {
            var icon = th.querySelector('.sort-icon');
            if (!icon) {
                icon = document.createElement('i');
                icon.className = 'sort-icon bi ms-1';
                icon.style.fontSize = '0.7rem';
                th.appendChild(icon);
            }
            if (th.dataset.col === state.sort) {
                icon.className = 'sort-icon bi ms-1 bi-caret-' + (state.dir === 'asc' ? 'up-fill' : 'down-fill');
            } else {
                icon.className = 'sort-icon bi ms-1 bi-caret-up text-muted';
                icon.style.opacity = '0.3';
            }
        });
    }

    // -- Event handlers --

    document.getElementById('crud-search').addEventListener('input', function() {
        clearTimeout(debounceTimer);
        var val = this.value;
        debounceTimer = setTimeout(function() {
            state.search = val;
            state.page = 1;
            load();
        }, 300);
    });

    document.getElementById('crud-perpage').addEventListener('change', function() {
        state.perPage = parseInt(this.value);
        state.page = 1;
        load();
    });

    document.getElementById('crud-pagination').addEventListener('click', function(e) {
        var link = e.target.closest('[data-page]');
        if (!link) return;
        e.preventDefault();
        var p = parseInt(link.dataset.page);
        if (p >= 1) { state.page = p; load(); }
    });

    document.querySelectorAll('.crud-sortable').forEach(function(th) {
        th.style.cursor = 'pointer';
        th.addEventListener('click', function() {
            var col = th.dataset.col;
            if (state.sort === col) {
                state.dir = state.dir === 'asc' ? 'desc' : 'asc';
            } else {
                state.sort = col;
                state.dir = 'asc';
            }
            state.page = 1;
            load();
        });
    });

    document.getElementById('crud-table').addEventListener('click', function(e) {
        if (e.target.closest('a, button, form')) return;
        var row = e.target.closest('tr[data-href]');
        if (row) window.location = row.dataset.href;
    });

    load();
})();
</script>
