import { openPanel, openDialog, resolveTopDialog, closeTopDrawer } from './drawers.js';
import { toast } from './toast.js';
import { escHtml, escAttr, formatSize } from './utils.js';

var currentPath = '';
var isPicker = false;

export function open() {
    isPicker = false;
    openPanel('media-library', 'Media Library', 'drawer-wide');
    load('');
}

export function pick() {
    isPicker = true;
    var dialog = openDialog('media-library', 'Select Image', 'drawer-wide');
    load('');
    return dialog;
}

function load(path) {
    currentPath = path;
    fetch('/admin/api/media/?path=' + encodeURIComponent(path) + '&_t=' + Date.now())
        .then(function(r) { return r.json(); })
        .then(function(data) {
            renderBreadcrumb(data.path);
            renderGrid(data.folders, data.files);
            highlightFolder(path);
            loadFolderTree();
            initDragDrop();
        });
}

function loadFolderTree() {
    fetch('/admin/api/media/?path=&_t=' + Date.now())
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var tree = document.getElementById('media-folder-tree');
            if (!tree) return;
            tree.innerHTML = '';
            data.folders.forEach(function(f) {
                var btn = document.createElement('button');
                btn.className = 'media-folder-item media-droppable' + (currentPath === f.path ? ' active' : '');
                btn.dataset.path = f.path;
                btn.dataset.dropPath = f.path;
                btn.innerHTML = '<i class="bi bi-folder"></i> ' + escHtml(f.name);
                btn.onclick = function() { navigateFolder(f.path); };
                setupDropTarget(btn);
                tree.appendChild(btn);
            });

            var rootBtn = document.querySelector('.media-folder-item[data-path=""]');
            if (rootBtn && !rootBtn._dropInit) {
                rootBtn._dropInit = true;
                rootBtn.classList.add('media-droppable');
                rootBtn.dataset.dropPath = '';
                setupDropTarget(rootBtn);
            }
        });
}

export function navigateFolder(path) {
    load(path);
}

function highlightFolder(path) {
    var panel = document.querySelector('.media-folders');
    if (!panel) return;
    panel.querySelectorAll('.media-folder-item').forEach(function(el) {
        el.classList.toggle('active', el.dataset.path === path);
    });
}

function renderBreadcrumb(path) {
    var el = document.getElementById('media-breadcrumb');
    if (!el) return;
    var html = '<a href="#" onclick="CMS.media.navigateFolder(\'\'); return false;">uploads</a>';
    if (path) {
        var parts = path.split('/');
        var acc = '';
        parts.forEach(function(p) {
            acc += (acc ? '/' : '') + p;
            var segPath = acc;
            html += ' / <a href="#" onclick="CMS.media.navigateFolder(\'' + escAttr(segPath) + '\'); return false;">' + escHtml(p) + '</a>';
        });
    }
    el.innerHTML = html;
}

function renderGrid(folders, files) {
    var grid = document.getElementById('media-grid');
    if (!grid) return;
    grid.innerHTML = '';

    folders.forEach(function(f) {
        var thumb = document.createElement('div');
        thumb.className = 'media-thumb media-droppable';
        thumb.draggable = true;
        thumb.dataset.dragPath = f.path;
        thumb.dataset.dropPath = f.path;
        thumb.innerHTML = '<span class="media-thumb-icon"><i class="bi bi-folder-fill"></i></span>' +
            '<span class="media-thumb-name">' + escHtml(f.name) + '</span>';
        thumb.onclick = function() { navigateFolder(f.path); };
        setupDraggable(thumb);
        setupDropTarget(thumb);
        grid.appendChild(thumb);
    });

    files.forEach(function(f) {
        var thumb = document.createElement('div');
        thumb.className = 'media-thumb';
        thumb.draggable = true;
        thumb.dataset.dragPath = f.path;
        if (f.isImage) {
            thumb.innerHTML = '<img src="' + escAttr(f.url) + '" alt="' + escAttr(f.name) + '" loading="lazy">';
        } else {
            var icons = { pdf: 'bi-file-earmark-pdf', mp4: 'bi-file-earmark-play', webm: 'bi-file-earmark-play' };
            var icon = icons[f.ext] || 'bi-file-earmark';
            thumb.innerHTML = '<span class="media-thumb-icon"><i class="bi ' + icon + '"></i></span>';
        }
        thumb.innerHTML += '<span class="media-thumb-name">' + escHtml(f.name) + '</span>';
        thumb.onclick = function() {
            if (isPicker && f.isImage) {
                resolveTopDialog(f);
            } else {
                previewFile(f);
            }
        };
        setupDraggable(thumb);
        grid.appendChild(thumb);
    });

    if (!folders.length && !files.length) {
        grid.innerHTML = '<div class="text-muted p-3" style="grid-column:1/-1;">This folder is empty.</div>';
    }
}

function setupDraggable(el) {
    el.addEventListener('dragstart', function(e) {
        e.dataTransfer.setData('text/plain', el.dataset.dragPath);
        e.dataTransfer.effectAllowed = 'move';
        el.classList.add('media-dragging');
    });
    el.addEventListener('dragend', function() {
        el.classList.remove('media-dragging');
    });
}

function setupDropTarget(el) {
    el.addEventListener('dragover', function(e) {
        var src = e.dataTransfer.types.indexOf('text/plain') !== -1;
        if (!src) return;
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        el.classList.add('media-drop-hover');
    });
    el.addEventListener('dragleave', function() {
        el.classList.remove('media-drop-hover');
    });
    el.addEventListener('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        el.classList.remove('media-drop-hover');
        var fromPath = e.dataTransfer.getData('text/plain');
        var toPath = el.dataset.dropPath;
        if (fromPath && fromPath !== toPath) {
            moveItem(fromPath, toPath);
        }
    });
}

function moveItem(fromPath, toFolderPath) {
    fetch('/admin/api/media/move/', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ from: fromPath, to: toFolderPath })
    }).then(function(r) { return r.json(); }).then(function(data) {
        if (data.ok) {
            load(currentPath);
        } else {
            toast(data.error || 'Move failed', 'error');
        }
    });
}

export function previewFile(f) {
    openDialog('media-preview', f.name);
    var el = document.getElementById('media-preview-content');
    if (!el) return;

    var html = '';
    if (f.isImage) {
        html += '<img src="' + escAttr(f.url) + '" alt="' + escAttr(f.name) + '">';
    } else if (f.ext === 'mp4' || f.ext === 'webm') {
        html += '<video controls style="max-width:100%;max-height:300px;margin-bottom:1rem;"><source src="' + escAttr(f.url) + '"></video>';
    } else if (f.ext === 'pdf') {
        html += '<iframe src="' + escAttr(f.url) + '" style="width:100%;height:300px;border:1px solid #ddd;border-radius:4px;margin-bottom:1rem;"></iframe>';
    } else {
        html += '<div class="p-4"><i class="bi bi-file-earmark" style="font-size:4rem;color:#aaa;"></i></div>';
    }

    html += '<div class="media-preview-url" title="Click to copy">' + escHtml(f.url) + '</div>';

    if (f.isImage) {
        var alt = (f.meta && f.meta.alt) || '';
        html += '<div class="media-meta-form mt-3 text-start">';
        html += '<label style="font-size:0.82rem;font-weight:600;display:block;margin-bottom:0.25rem;">Alt text</label>';
        html += '<div class="d-flex gap-2">';
        html += '<input type="text" id="media-meta-alt" class="form-control form-control-sm" value="' + escAttr(alt) + '" placeholder="Describe this image...">';
        html += '<button class="btn btn-sm btn-primary" onclick="CMS.media.saveMeta(\'' + escAttr(f.path) + '\')">Save</button>';
        html += '</div>';
        html += '</div>';
    }

    html += '<dl class="media-preview-meta mt-3 text-start">';
    html += '<dt>Filename</dt><dd>' + escHtml(f.name) + '</dd>';
    html += '<dt>Size</dt><dd>' + formatSize(f.size) + '</dd>';
    html += '<dt>Modified</dt><dd>' + new Date(f.modified).toLocaleString() + '</dd>';
    html += '</dl>';
    html += '<div class="mt-3 d-flex gap-2 justify-content-center">';
    html += '<button class="btn btn-sm btn-outline-primary" onclick="CMS.media.copyUrl(\'' + escAttr(f.url) + '\')"><i class="bi bi-clipboard"></i> Copy URL</button>';
    html += '<button class="btn btn-sm btn-outline-danger" onclick="CMS.media.deleteFile(\'' + escAttr(f.path) + '\')"><i class="bi bi-trash"></i> Delete</button>';
    html += '</div>';
    html += '<div id="media-derivatives" class="derivatives-list"></div>';
    el.innerHTML = html;

    if (f.isImage) {
        loadDerivatives(f.path);
    }
}

export function uploadFiles(files) {
    if (!files || !files.length) return;
    var form = new FormData();
    for (var i = 0; i < files.length; i++) form.append('files[]', files[i]);
    fetch('/admin/api/media/upload/?path=' + encodeURIComponent(currentPath), {
        method: 'POST',
        body: form
    }).then(function(r) { return r.json(); }).then(function() {
        load(currentPath);
    });
}

export function createFolder() {
    var name = prompt('Folder name:');
    if (!name) return;
    fetch('/admin/api/media/mkdir/?path=' + encodeURIComponent(currentPath) + '&name=' + encodeURIComponent(name), {
        method: 'POST'
    }).then(function(r) { return r.json(); }).then(function() {
        load(currentPath);
    });
}

export function deleteFile(path) {
    if (!confirm('Delete this file?')) return;
    fetch('/admin/api/media/file/?path=' + encodeURIComponent(path), {
        method: 'DELETE'
    }).then(function(r) { return r.json(); }).then(function() {
        closeTopDrawer();
        load(currentPath);
    });
}

export function saveMeta(path) {
    var altInput = document.getElementById('media-meta-alt');
    if (!altInput) return;
    fetch('/admin/api/media/meta/?path=' + encodeURIComponent(path), {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ alt: altInput.value })
    }).then(function(r) { return r.json(); }).then(function(data) {
        if (data.ok) toast('Metadata saved');
    });
}

export function copyUrl(url) {
    var full = window.location.origin + url;
    navigator.clipboard.writeText(full).then(function() {
        toast('URL copied');
    });
}

function isExternalDrag(e) {
    return e.dataTransfer.types.indexOf('Files') !== -1
        && e.dataTransfer.types.indexOf('text/plain') === -1;
}

function handleDroppedUrl(url) {
    if (!url || !url.match(/^https?:\/\//)) return;
    toast('Downloading image...');
    fetch('/admin/api/media/fetch/', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ url: url, path: currentPath })
    }).then(function(r) { return r.json(); }).then(function(data) {
        if (data.error) {
            toast(data.error, 'error');
        } else {
            load(currentPath);
        }
    }).catch(function() {
        toast('Download failed', 'error');
    });
}

function extractImageUrl(e) {
    var html = e.dataTransfer.getData('text/html');
    if (html) {
        var m = html.match(/<img[^>]+src=["']([^"']+)["']/i);
        if (m) return m[1];
    }
    var uri = e.dataTransfer.getData('text/uri-list') || e.dataTransfer.getData('text/plain');
    if (uri && uri.match(/^https?:\/\/.*\.(jpg|jpeg|png|gif|webp|svg|ico)/i)) return uri;
    return null;
}

function initDragDrop() {
    var panel = document.getElementById('media-files-panel');
    var dz = document.getElementById('media-dropzone');
    if (!panel || !dz || panel._dragInit) return;
    panel._dragInit = true;

    panel.addEventListener('dragenter', function(e) {
        e.preventDefault();
        panel.classList.add('dragging');
    });
    panel.addEventListener('dragover', function(e) {
        e.preventDefault();
    });
    panel.addEventListener('dragleave', function(e) {
        if (!panel.contains(e.relatedTarget)) panel.classList.remove('dragging');
    });
    panel.addEventListener('drop', function() {
        panel.classList.remove('dragging');
    });

    dz.addEventListener('dragover', function(e) {
        e.preventDefault();
        dz.classList.add('dragover');
    });
    dz.addEventListener('dragleave', function() {
        dz.classList.remove('dragover');
    });
    dz.addEventListener('drop', function(e) {
        e.preventDefault();
        dz.classList.remove('dragover');
        panel.classList.remove('dragging');
        if (e.dataTransfer.files.length) {
            uploadFiles(e.dataTransfer.files);
        } else {
            var url = extractImageUrl(e);
            if (url) handleDroppedUrl(url);
        }
    });

    panel.addEventListener('paste', function(e) {
        var files = [];
        if (e.clipboardData && e.clipboardData.items) {
            for (var i = 0; i < e.clipboardData.items.length; i++) {
                var item = e.clipboardData.items[i];
                if (item.type.indexOf('image/') === 0) {
                    var file = item.getAsFile();
                    if (file) files.push(file);
                }
            }
        }
        if (files.length) {
            e.preventDefault();
            uploadFiles(files);
        }
    });
}

function loadDerivatives(path) {
    fetch('/admin/api/media/versions/?path=' + encodeURIComponent(path) + '&_t=' + Date.now())
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var el = document.getElementById('media-derivatives');
            if (!el) return;
            var aspects = Object.keys(data);
            if (!aspects.length) return;

            var html = '<h6><i class="bi bi-layers"></i> Derivatives</h6>';
            aspects.forEach(function(aspect) {
                var info = data[aspect];
                var widthKeys = Object.keys(info.widths);
                html += '<div class="derivative-item">';
                if (widthKeys.length) {
                    var smallestUrl = info.widths[widthKeys[0]];
                    html += '<img src="' + escAttr(smallestUrl) + '" alt="">';
                }
                html += '<span><strong>' + escHtml(aspect) + '</strong> &mdash; ' + widthKeys.join(', ') + 'px</span>';
                html += '</div>';
            });
            el.innerHTML = html;
        });
}
