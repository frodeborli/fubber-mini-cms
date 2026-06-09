var pickerResolve = null;
var pickerEl = null;
var pickerState = { slug: '', page: 1, perPage: 10, search: '' };
var debounceTimer = null;

function esc(str) {
    var d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}

function createPickerDOM() {
    if (pickerEl) return;

    var overlay = document.createElement('div');
    overlay.className = 'cms-picker-overlay';
    overlay.innerHTML =
        '<div class="cms-picker-panel">' +
            '<div class="cms-picker-header">' +
                '<h5 class="mb-0" id="cms-picker-title">Select</h5>' +
                '<button type="button" class="btn-close" onclick="CMS.data.cancelPick()"></button>' +
            '</div>' +
            '<div class="cms-picker-search px-3 py-2">' +
                '<input type="search" id="cms-picker-search" class="form-control form-control-sm" placeholder="Search...">' +
            '</div>' +
            '<div class="cms-picker-body" id="cms-picker-body">' +
                '<div class="text-center text-muted py-4">Loading...</div>' +
            '</div>' +
            '<div class="cms-picker-footer" id="cms-picker-footer"></div>' +
        '</div>';

    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) cancelPick();
    });

    document.body.appendChild(overlay);
    pickerEl = overlay;

    document.getElementById('cms-picker-search').addEventListener('input', function() {
        clearTimeout(debounceTimer);
        var val = this.value;
        debounceTimer = setTimeout(function() {
            pickerState.search = val;
            pickerState.page = 1;
            loadPickerData();
        }, 300);
    });
}

function loadPickerData() {
    var params = new URLSearchParams({
        page: pickerState.page,
        perPage: pickerState.perPage
    });
    if (pickerState.search) params.set('search', pickerState.search);

    fetch('/admin/api/data/' + encodeURIComponent(pickerState.slug) + '/?' + params.toString())
        .then(function(r) { return r.json(); })
        .then(renderPickerData);
}

function renderPickerData(data) {
    var body = document.getElementById('cms-picker-body');

    if (data.rows.length === 0) {
        body.innerHTML = '<div class="text-center text-muted py-4">No results found.</div>';
    } else {
        var html = '<div class="list-group list-group-flush">';
        for (var i = 0; i < data.rows.length; i++) {
            var row = data.rows[i];
            html += '<button type="button" class="list-group-item list-group-item-action" data-pick-id="' + esc(row._id) + '" data-pick-display="' + esc(row._display) + '">';
            html += '<strong>' + esc(row._display) + '</strong>';
            html += ' <small class="text-muted">#' + esc(row._id) + '</small>';
            html += '</button>';
        }
        html += '</div>';
        body.innerHTML = html;

        body.querySelectorAll('[data-pick-id]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var result = {
                    id: parseInt(btn.dataset.pickId) || btn.dataset.pickId,
                    display: btn.dataset.pickDisplay
                };
                closePicker();
                if (pickerResolve) pickerResolve(result);
                pickerResolve = null;
            });
        });
    }

    var footer = document.getElementById('cms-picker-footer');
    if (data.pages <= 1) {
        footer.innerHTML = '';
    } else {
        var html = '<nav class="d-flex justify-content-center py-2"><ul class="pagination pagination-sm mb-0">';
        html += '<li class="page-item' + (data.page <= 1 ? ' disabled' : '') + '">';
        html += '<a class="page-link" href="#" data-picker-page="' + (data.page - 1) + '">&laquo;</a></li>';
        for (var p = 1; p <= data.pages; p++) {
            html += '<li class="page-item' + (p === data.page ? ' active' : '') + '">';
            html += '<a class="page-link" href="#" data-picker-page="' + p + '">' + p + '</a></li>';
        }
        html += '<li class="page-item' + (data.page >= data.pages ? ' disabled' : '') + '">';
        html += '<a class="page-link" href="#" data-picker-page="' + (data.page + 1) + '">&raquo;</a></li>';
        html += '</ul></nav>';
        footer.innerHTML = html;

        footer.querySelectorAll('[data-picker-page]').forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                var pg = parseInt(link.dataset.pickerPage);
                if (pg >= 1 && pg <= data.pages) {
                    pickerState.page = pg;
                    loadPickerData();
                }
            });
        });
    }
}

function closePicker() {
    if (pickerEl) {
        pickerEl.classList.remove('open');
        setTimeout(function() { pickerEl.style.display = 'none'; }, 200);
    }
}

export function pick(slug) {
    createPickerDOM();

    pickerState.slug = slug;
    pickerState.page = 1;
    pickerState.search = '';

    document.getElementById('cms-picker-title').textContent = 'Select';
    document.getElementById('cms-picker-search').value = '';
    document.getElementById('cms-picker-body').innerHTML = '<div class="text-center text-muted py-4">Loading...</div>';
    document.getElementById('cms-picker-footer').innerHTML = '';

    pickerEl.style.display = '';
    requestAnimationFrame(function() { pickerEl.classList.add('open'); });

    loadPickerData();

    return new Promise(function(resolve) {
        pickerResolve = resolve;
    });
}

export function cancelPick() {
    closePicker();
    if (pickerResolve) pickerResolve(null);
    pickerResolve = null;
}

export function loadInline(slug, id, targetEl) {
    fetch('/admin/api/data/' + encodeURIComponent(slug) + '/' + encodeURIComponent(id) + '/inline')
        .then(function(r) { return r.text(); })
        .then(function(html) { targetEl.innerHTML = html; });
}
