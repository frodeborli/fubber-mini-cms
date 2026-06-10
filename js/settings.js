import { toast } from './toast.js';

export function changePassword(e) {
    e.preventDefault();
    var form = e.target;
    var current = form.current.value;
    var newPw = form['new'].value;
    var confirm = form.confirm.value;
    var errorEl = document.getElementById('settings-password-error');

    if (errorEl) errorEl.style.display = 'none';

    if (newPw !== confirm) {
        if (errorEl) {
            errorEl.textContent = 'New passwords do not match.';
            errorEl.style.display = '';
        }
        return;
    }

    fetch('/admin/api/password', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ current: current, 'new': newPw })
    }).then(function(res) {
        return res.json().then(function(data) {
            if (!res.ok) throw new Error(data.error || 'Failed to change password');
            toast('Password updated successfully.');
            form.reset();
        });
    }).catch(function(err) {
        if (errorEl) {
            errorEl.textContent = err.message;
            errorEl.style.display = '';
        }
    });
}
