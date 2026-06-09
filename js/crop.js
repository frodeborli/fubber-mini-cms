import { openDialog, resolveTopDialog, closeTopDrawer } from './drawers.js';
import Cropper from 'cropperjs';

var cropper = null;
var currentFile = null;
var currentAspect = '';

function parseAspect(str) {
    var parts = str.split(/[x:\/]/);
    if (parts.length === 2) {
        var w = parseFloat(parts[0]);
        var h = parseFloat(parts[1]);
        if (w > 0 && h > 0) return w / h;
    }
    return NaN;
}

export function start(file, aspect) {
    currentFile = file;
    currentAspect = aspect;

    var dialog = openDialog('crop-tool', 'Crop — ' + aspect);

    setTimeout(function() {
        var img = document.getElementById('crop-image');
        if (!img) return;
        img.src = file.url;

        img.onload = function() {
            if (cropper) cropper.destroy();
            var ratio = parseAspect(aspect);
            cropper = new Cropper(img, {
                aspectRatio: isNaN(ratio) ? NaN : ratio,
                viewMode: 1,
                autoCropArea: 1,
                responsive: true,
                background: false,
            });
        };
    }, 100);

    return dialog;
}

export function apply() {
    if (!cropper || !currentFile) return;

    var data = cropper.getData(true);

    var btn = document.querySelector('#drawer-container .cms-drawer.active .btn-primary');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Processing...';
    }

    fetch('/admin/api/media/crop/', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            image: currentFile.path,
            aspect: currentAspect,
            crop: { x: data.x, y: data.y, width: data.width, height: data.height }
        })
    })
    .then(function(r) { return r.json(); })
    .then(function(result) {
        cropper.destroy();
        cropper = null;
        if (result.ok && result.widths) {
            var keys = Object.keys(result.widths).map(Number).sort(function(a,b){return a-b;});
            var best = keys.reduce(function(prev, w) { return w <= 960 ? w : prev; }, keys[keys.length - 1]);
            resolveTopDialog(result.widths[best]);
        } else {
            closeTopDrawer();
        }
    })
    .catch(function() {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-crop"></i> Apply Crop';
        }
    });
}

export function cancel() {
    if (cropper) {
        cropper.destroy();
        cropper = null;
    }
    closeTopDrawer();
}
