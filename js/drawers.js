var container = document.getElementById('drawer-container');

var panel = null;
var dialogs = [];

function createDrawerEl(templateId, title, extraClass) {
    var tmpl = document.getElementById('tmpl-' + templateId);
    var drawer = document.createElement('div');
    drawer.className = 'cms-drawer';
    if (extraClass) drawer.classList.add(extraClass);
    drawer.innerHTML =
        '<div class="cms-drawer-header">' +
            '<span class="cms-drawer-tab">' + title + '</span>' +
            '<h6>' + title + '</h6>' +
            '<button class="cms-drawer-close" title="Close">&times;</button>' +
        '</div>' +
        '<div class="cms-drawer-body">' +
            (tmpl ? tmpl.innerHTML : '<p class="text-muted">No content</p>') +
        '</div>';
    return drawer;
}

function updateStates() {
    var all = [];
    if (panel) all.push(panel.el);
    dialogs.forEach(function(d) { all.push(d.el); });

    all.forEach(function(el, i) {
        var isTop = (i === all.length - 1);
        el.classList.toggle('active', isTop);
        el.classList.toggle('collapsed', !isTop);
    });
}

export function openPanel(templateId, title, extraClass) {
    if (panel && panel.id === templateId) {
        closePanel();
        return;
    }

    closeAll();

    var drawer = createDrawerEl(templateId, title, extraClass);
    panel = { id: templateId, el: drawer, title: title };

    drawer.querySelector('.cms-drawer-close').addEventListener('click', closePanel);

    container.appendChild(drawer);
    requestAnimationFrame(function() {
        drawer.classList.add('open', 'active');
        updateStates();
    });
}

export function closePanel() {
    closeAllDialogs();
    if (panel) {
        panel.el.remove();
        panel = null;
    }
    updateStates();
}

export function openDialog(templateId, title, extraClass) {
    return new Promise(function(resolve) {
        var drawer = createDrawerEl(templateId, title, extraClass);
        var entry = { id: templateId, el: drawer, title: title, resolve: resolve, resolved: false };

        function close(value) {
            if (entry.resolved) return;
            entry.resolved = true;
            var idx = dialogs.indexOf(entry);
            if (idx !== -1) {
                for (var i = dialogs.length - 1; i >= idx; i--) {
                    if (!dialogs[i].resolved) {
                        dialogs[i].resolved = true;
                        dialogs[i].resolve(null);
                    }
                    dialogs[i].el.remove();
                }
                dialogs.splice(idx);
            }
            updateStates();
            resolve(value === undefined ? null : value);
        }

        entry.close = close;

        drawer.querySelector('.cms-drawer-close').addEventListener('click', function() {
            close(null);
        });

        drawer.addEventListener('click', function() {
            if (!drawer.classList.contains('collapsed')) return;
            var idx = dialogs.indexOf(entry);
            if (idx !== -1 && idx !== dialogs.length - 1) {
                dialogs.splice(idx, 1);
                drawer.remove();
                dialogs.push(entry);
                container.appendChild(drawer);
                updateStates();
            }
        });

        dialogs.push(entry);
        container.appendChild(drawer);
        requestAnimationFrame(function() {
            drawer.classList.add('open', 'active');
            updateStates();
        });
    });
}

export function resolveTopDialog(value) {
    if (dialogs.length === 0) return;
    var top = dialogs[dialogs.length - 1];
    top.close(value);
}

function closeAllDialogs() {
    for (var i = dialogs.length - 1; i >= 0; i--) {
        if (!dialogs[i].resolved) {
            dialogs[i].resolved = true;
            dialogs[i].resolve(null);
        }
        dialogs[i].el.remove();
    }
    dialogs = [];
}

export function closeAll() {
    closeAllDialogs();
    closePanel();
}

export function closeTopDrawer() {
    if (dialogs.length > 0) {
        var top = dialogs[dialogs.length - 1];
        top.close(null);
    } else {
        closePanel();
    }
}

// Legacy compat — sidebar onclick still calls openDrawer for component groups
export function openDrawer(templateId, title, extraClass) {
    openPanel(templateId, title, extraClass);
}
