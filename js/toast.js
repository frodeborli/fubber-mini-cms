var toastContainer = document.getElementById('cms-toasts');

export function toast(message, type) {
    type = type || 'success';
    var icons = { success: 'bi-check-circle-fill', error: 'bi-exclamation-triangle-fill', info: 'bi-info-circle-fill' };
    var colors = { success: 'text-success', error: 'text-danger', info: 'text-primary' };
    var el = document.createElement('div');
    el.className = 'toast align-items-center border-0 show';
    el.setAttribute('role', 'alert');
    el.innerHTML =
        '<div class="d-flex">' +
            '<div class="toast-body"><i class="bi ' + (icons[type] || icons.info) + ' ' + (colors[type] || colors.info) + ' me-2"></i>' + message + '</div>' +
            '<button type="button" class="btn-close me-2 m-auto" onclick="this.closest(\'.toast\').remove()"></button>' +
        '</div>';
    toastContainer.appendChild(el);
    setTimeout(function() { el.remove(); }, 3000);
}
