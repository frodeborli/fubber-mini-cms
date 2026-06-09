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
                context: field.dataset.context,
                slug: field.dataset.slug,
                type: field.dataset.type,
                value: field.value
            })
        }));
    });
    drawer.querySelectorAll('.cms-html-editor').forEach(function(el) {
        promises.push(fetch('/admin/api/component/', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                context: el.dataset.context,
                slug: el.dataset.slug,
                type: el.dataset.type,
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
