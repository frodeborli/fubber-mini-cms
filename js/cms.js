import 'bootstrap';
import 'admin-lte';
import { openDrawer, closeTopDrawer, closeAll } from './drawers.js';
import { toast } from './toast.js';
import { saveGroup, cancelGroup, refreshPreview } from './editor.js';
import * as media from './media.js';
import * as crop from './crop.js';
import * as ai from './ai.js';
import * as data from './data.js';

// -- Image picking (async, used by both sidebar editors and iframe edit mode) --

async function pickFile(aspect) {
    var file = await media.pick();
    if (!file) return null;

    var displayUrl = file.url;

    if (aspect) {
        var r = await fetch('/admin/api/media/versions/?path=' + encodeURIComponent(file.path));
        var versions = await r.json();
        if (versions[aspect] && Object.keys(versions[aspect].widths).length) {
            var widths = versions[aspect].widths;
            var keys = Object.keys(widths).map(Number).sort(function(a,b){return a-b;});
            var best = keys.reduce(function(prev, w) { return w <= 960 ? w : prev; }, keys[keys.length - 1]);
            displayUrl = widths[best];
        } else {
            var cropUrl = await crop.start(file, aspect);
            if (!cropUrl) return null;
            displayUrl = cropUrl;
        }
    }

    return { url: file.url, path: file.path, displayUrl: displayUrl };
}

function pickImage(fieldEl) {
    var aspect = fieldEl.dataset.aspect || '';
    pickFile(aspect).then(function(result) {
        if (!result) return;
        updateImageField(fieldEl, result.url);
    });
}

function updateImageField(fieldEl, url) {
    var thumb = fieldEl.querySelector('.cms-image-thumb');
    thumb.innerHTML = '<img src="' + url + '" alt="">';

    fetch('/admin/api/component/', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            file: fieldEl.dataset.file,
            path: fieldEl.dataset.path,
            type: 'image',
            pos: parseInt(fieldEl.dataset.pos),
            value: url
        })
    }).then(function(res) { if (res.ok) refreshPreview(); });
}

// -- Edit mode toggle --

var editMode = false;
var editDirty = false;
var editToggleEl = document.querySelector('.cms-edit-toggle');
var editCancelEl = document.querySelector('.cms-edit-cancel');

function setEditUI(mode, isDirty) {
    editMode = mode;
    editDirty = isDirty;
    if (!editToggleEl) editToggleEl = document.querySelector('.cms-edit-toggle');
    if (!editCancelEl) editCancelEl = document.querySelector('.cms-edit-cancel');
    if (mode) {
        if (editToggleEl) {
            editToggleEl.innerHTML = '<i class="bi bi-check-lg"></i> Save';
            editToggleEl.classList.add('text-success');
        }
        if (editCancelEl) editCancelEl.style.display = '';
    } else {
        if (editToggleEl) {
            editToggleEl.innerHTML = '<i class="bi bi-pencil-square"></i> Edit';
            editToggleEl.classList.remove('text-success');
        }
        if (editCancelEl) editCancelEl.style.display = 'none';
    }
}

function getIframeWindow() {
    var iframe = document.getElementById('site-iframe');
    try { return iframe && iframe.contentWindow; } catch(e) { return null; }
}

function toggleEdit() {
    var iw = getIframeWindow();
    if (!iw) return;

    if (!editMode) {
        closeAll();
        setEditUI(true, false);
        iw.postMessage({ type: 'cms-enter-edit' }, '*');
    } else {
        setEditUI(false, false);
        iw.postMessage({ type: 'cms-exit-edit' }, '*');
    }
}

function cancelEdit() {
    var iframe = document.getElementById('site-iframe');
    if (!iframe || !iframe.contentWindow) return;

    if (editDirty) {
        if (!confirm('You have unsaved changes. Discard them?')) return;
    }
    setEditUI(false, false);
    iframe.contentWindow.postMessage({ type: 'cms-cancel-edit' }, '*');
}

// -- Message listener (iframe -> parent) --

window.addEventListener('message', function(e) {
    if (!e.data || !e.data.type) return;
    if (e.data.type === 'cms-edit-saved') {
        setEditUI(false, false);
    }
    if (e.data.type === 'cms-dirty') {
        editDirty = e.data.dirty;
    }
    if (e.data.type === 'cms-pick-image') {
        var msg = e.data;
        var iframe = document.getElementById('site-iframe');
        pickFile(msg.aspect).then(function(result) {
            if (!result) return;
            iframe.contentWindow.postMessage({
                type: 'cms-image-picked',
                file: msg.file, path: msg.path, src: result.url, displaySrc: result.displayUrl
            }, '*');
        });
    }
});

window.addEventListener('beforeunload', function(e) {
    if (editMode && editDirty) {
        e.preventDefault();
        e.returnValue = '';
    }
});

// -- Iframe link rewriting --

var siteIframe = document.getElementById('site-iframe');
if (siteIframe) {
    siteIframe.addEventListener('load', function() {
        try {
            var doc = siteIframe.contentDocument;
            if (!doc) return;
            doc.querySelectorAll('a[href]').forEach(function(a) {
                if (!a.target && !a.getAttribute('href').match(/^(#|javascript:|mailto:|tel:)/)) {
                    a.target = '_top';
                }
            });
            doc.querySelectorAll('form[action]').forEach(function(f) {
                if (!f.target) f.target = '_top';
            });
        } catch (e) {}
    });
}

// -- Keyboard shortcuts --

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeTopDrawer();
});

// -- Sidebar navigation --

document.querySelectorAll('a[data-cms-nav]').forEach(function(link) {
    link.addEventListener('click', function(e) {
        if (e.target.closest('.nav-arrow')) {
            e.preventDefault();
            e.stopImmediatePropagation();
            link.closest('.nav-item').classList.toggle('menu-open');
            return;
        }
        if (editMode && editDirty) {
            if (!confirm('You have unsaved changes. Discard them?')) {
                e.preventDefault();
            }
        }
    });
});

// -- Public API --

window.CMS = {
    openDrawer: openDrawer,
    closeTopDrawer: closeTopDrawer,
    refreshPreview: refreshPreview,
    openMediaLibrary: function() { media.open(); },
    pickImage: pickImage,
    saveGroup: saveGroup,
    cancelGroup: cancelGroup,
    toggleEdit: toggleEdit,
    cancelEdit: cancelEdit,
    setEditUI: setEditUI,
    media: {
        open: media.open,
        pick: media.pick,
        navigateFolder: media.navigateFolder,
        uploadFiles: media.uploadFiles,
        createFolder: media.createFolder,
        deleteFile: media.deleteFile,
        copyUrl: media.copyUrl,
        previewFile: media.previewFile
    },
    crop: {
        start: crop.start,
        apply: crop.apply,
        cancel: crop.cancel
    },
    ai: {
        init: ai.init,
        send: ai.send,
        newConversation: ai.newConversation,
        pickImage: ai.pickImage,
        pickColor: ai.pickColor,
        closeMediaPicker: ai.closeMediaPicker,
        mediaNav: ai.mediaNav,
        selectMedia: ai.selectMedia
    },
    data: {
        pick: data.pick,
        cancelPick: data.cancelPick,
        loadInline: data.loadInline
    }
};
