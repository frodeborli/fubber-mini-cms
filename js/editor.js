import { closeTopDrawer } from './drawers.js';
import { toast } from './toast.js';

export function refreshPreview() {
    var iframe = document.getElementById('site-iframe');
    iframe.contentWindow.location.reload();
}

export function saveGroup(btn) {
    var drawer = btn.closest('.cms-drawer-body');
    if (!drawer) return;

    var promises = [];
    drawer.querySelectorAll('.cms-field').forEach(function(field) {
        promises.push(fetch('/admin/api/component/', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                file: field.dataset.file,
                path: field.dataset.path,
                type: field.dataset.type,
                pos: parseInt(field.dataset.pos),
                value: field.value
            })
        }));
    });
    drawer.querySelectorAll('.cms-html-editor').forEach(function(el) {
        promises.push(fetch('/admin/api/component/', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                file: el.dataset.file,
                path: el.dataset.path,
                type: el.dataset.type,
                pos: parseInt(el.dataset.pos),
                value: el.innerHTML
            })
        }));
    });

    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Saving...';
    Promise.all(promises).then(function() {
        refreshPreview();
        closeTopDrawer();
        toast('Changes saved');
    });
}

export function cancelGroup() {
    closeTopDrawer();
}
