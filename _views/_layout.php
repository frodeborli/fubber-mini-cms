<?php
$isPreview = !empty($_GET['_preview']);
$content = \mini\Mini::$mini->get(\MiniCms\Content::class);
$nav = $content->nav($path ?? strtok($_SERVER['REQUEST_URI'] ?? '/', '?'));
?>
<?php $this->extend('_base.php'); ?>
<?php $this->block('body'); ?>
    <?= \mini\render('partials/header.php', ['nav' => $nav, 'siteName' => $siteName ?? 'My Site']) ?>

    <main>
        <?php $this->show('content'); ?>
    </main>

    <?= \mini\render('partials/footer.php', ['siteName' => $siteName ?? 'My Site']) ?>
<?php $this->end(); ?>
<?php if ($isPreview): ?>
<?php $this->block('head'); ?>
<style>
[data-cms-type] { transition: box-shadow 0.15s; }
[data-cms-type].cms-pressing { box-shadow: 0 0 0 2px rgba(59,130,246,.5); border-radius: 2px; }
.cms-edit-mode [data-cms-type] { box-shadow: 0 0 0 2px rgba(59,130,246,.2); border-radius: 2px; }
.cms-edit-mode [data-cms-type]:hover { box-shadow: 0 0 0 2px rgba(59,130,246,.5); }
.cms-edit-mode [data-cms-type]:focus { box-shadow: 0 0 0 2px #3b82f6; outline: none; }
.cms-edit-mode [data-cms-type="image"] { cursor: pointer; }

.cms-toolbar {
    position: sticky; top: 0; z-index: 9001;
    display: none; gap: 2px; padding: 6px 10px;
    background: #1e293b; box-shadow: 0 2px 8px rgba(0,0,0,.3);
    white-space: nowrap; align-items: center;
}
.cms-toolbar.visible { display: flex; }
.cms-toolbar button {
    background: transparent; border: none; color: #e2e8f0; cursor: pointer;
    padding: 5px 9px; border-radius: 4px; font-size: 13px; font-family: inherit; line-height: 1;
}
.cms-toolbar button:hover { background: rgba(255,255,255,.15); }
.cms-toolbar button.active { background: rgba(59,130,246,.6); }
.cms-toolbar .sep { width: 1px; align-self: stretch; background: rgba(255,255,255,.2); margin: 0 6px; }
</style>
<?php $this->end(); ?>
<?php $this->block('scripts'); ?>
<script>
(function() {
    var editing = false;
    var dirty = false;
    var toolbar = null;
    var focusedHtml = null;
    var pendingImages = {};

    function markDirty() {
        if (!dirty) {
            dirty = true;
            window.parent.postMessage({type: 'cms-dirty', dirty: true}, '*');
        }
    }

    function enterEditMode() {
        editing = true;
        dirty = false;
        pendingImages = {};
        document.body.classList.add('cms-edit-mode');

        document.querySelectorAll('[data-cms-type]').forEach(function(el) {
            var type = el.dataset.cmsType;
            if (type === 'text' || type === 'html') el.setAttribute('contenteditable', 'true');
        });

        document.addEventListener('input', onContentInput);
        document.addEventListener('keydown', onTextKeydown);
        document.addEventListener('paste', onTextPaste, true);
        buildToolbar();
    }

    function onContentInput(e) {
        if (e.target.closest('[data-cms-type]')) markDirty();
    }

    function onTextKeydown(e) {
        if (e.key === 'Enter' && e.target.closest('[data-cms-type="text"]')) {
            e.preventDefault();
        }
    }

    function onTextPaste(e) {
        var textEl = e.target.closest('[data-cms-type="text"]');
        if (!textEl) return;
        e.preventDefault();
        var html = e.clipboardData.getData('text/html');
        if (html) {
            var tmp = document.createElement('div');
            tmp.innerHTML = html;
            tmp.querySelectorAll('h1,h2,h3,h4,h5,h6,ul,ol,li,blockquote,table,tr,td,th,hr,img,pre,div,p').forEach(function(el) {
                while (el.firstChild) el.parentNode.insertBefore(el.firstChild, el);
                el.remove();
            });
            document.execCommand('insertHTML', false, tmp.innerHTML);
        } else {
            document.execCommand('insertText', false, e.clipboardData.getData('text/plain'));
        }
    }

    function exitEditMode() {
        var promises = [];
        document.querySelectorAll('[data-cms-type]').forEach(function(el) {
            var type = el.dataset.cmsType;
            var context = el.dataset.cmsContext;
            var slug = el.dataset.cmsSlug;
            var value;
            if (type === 'text') value = cleanInlineHtml(el.innerHTML);
            else if (type === 'html') value = cleanHtml(el.innerHTML);
            else return;

            promises.push(
                fetch('/admin/api/component/', {
                    method: 'PUT',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({context: context, slug: slug, type: type, value: value})
                })
            );

            el.removeAttribute('contenteditable');
        });

        Object.keys(pendingImages).forEach(function(key) {
            var img = pendingImages[key];
            promises.push(
                fetch('/admin/api/component/', {
                    method: 'PUT',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({context: img.context, slug: img.slug, type: 'image', value: img.src})
                })
            );
        });

        editing = false;
        dirty = false;
        pendingImages = {};
        focusedHtml = null;
        currentToolbarMode = null;
        document.body.classList.remove('cms-edit-mode');
        if (toolbar) toolbar.classList.remove('visible');
        document.removeEventListener('input', onContentInput);
        document.removeEventListener('keydown', onTextKeydown);
        document.removeEventListener('paste', onTextPaste, true);

        Promise.all(promises).then(function() {
            window.parent.postMessage({type: 'cms-edit-saved'}, '*');
        });
    }

    function cancelEditMode() {
        editing = false;
        dirty = false;
        pendingImages = {};
        focusedHtml = null;
        currentToolbarMode = null;
        document.body.classList.remove('cms-edit-mode');
        if (toolbar) toolbar.classList.remove('visible');
        document.removeEventListener('input', onContentInput);
        document.removeEventListener('keydown', onTextKeydown);
        document.removeEventListener('paste', onTextPaste, true);
        location.reload();
    }

    function cleanHtml(html) {
        var tmp = document.createElement('div');
        tmp.innerHTML = html;
        tmp.querySelectorAll('.cms-toolbar').forEach(function(n) { n.remove(); });
        return tmp.innerHTML;
    }

    function cleanInlineHtml(html) {
        var tmp = document.createElement('div');
        tmp.innerHTML = html;
        tmp.querySelectorAll('.cms-toolbar').forEach(function(n) { n.remove(); });
        var blocked = tmp.querySelectorAll('h1,h2,h3,h4,h5,h6,ul,ol,li,blockquote,table,tr,td,th,hr,img,pre,div,p');
        blocked.forEach(function(el) {
            while (el.firstChild) el.parentNode.insertBefore(el.firstChild, el);
            el.remove();
        });
        return tmp.innerHTML;
    }

    var toolbarButtonsFull = [
        {cmd: 'bold',          label: '<b>B</b>'},
        {cmd: 'italic',        label: '<i>I</i>'},
        {cmd: 'underline',     label: '<u>U</u>'},
        {sep: true},
        {cmd: 'formatBlock', label: 'H2', value: 'H2'},
        {cmd: 'formatBlock', label: 'H3', value: 'H3'},
        {cmd: 'formatBlock', label: 'P',  value: 'P'},
        {sep: true},
        {cmd: 'insertUnorderedList', label: '&bull; List'},
        {cmd: 'insertOrderedList',   label: '1. List'},
        {sep: true},
        {cmd: 'createLink',   label: '&#128279; Link', prompt: 'URL:'},
        {cmd: 'unlink',       label: 'Unlink'},
        {sep: true},
        {cmd: 'removeFormat', label: 'Clear'},
    ];

    var toolbarButtonsInline = [
        {cmd: 'bold',          label: '<b>B</b>'},
        {cmd: 'italic',        label: '<i>I</i>'},
        {cmd: 'underline',     label: '<u>U</u>'},
        {sep: true},
        {cmd: 'createLink',   label: '&#128279; Link', prompt: 'URL:'},
        {cmd: 'unlink',       label: 'Unlink'},
        {sep: true},
        {cmd: 'removeFormat', label: 'Clear'},
    ];

    var currentToolbarMode = null;

    function buildToolbar() {
        if (toolbar) return;
        toolbar = document.createElement('div');
        toolbar.className = 'cms-toolbar';
        document.body.insertBefore(toolbar, document.body.firstChild);
    }

    function setToolbarMode(mode) {
        if (!toolbar || currentToolbarMode === mode) return;
        currentToolbarMode = mode;
        toolbar.innerHTML = '';
        var buttons = mode === 'text' ? toolbarButtonsInline : toolbarButtonsFull;
        buttons.forEach(function(b) {
            if (b.sep) {
                var sep = document.createElement('span');
                sep.className = 'sep';
                toolbar.appendChild(sep);
                return;
            }
            var btn = document.createElement('button');
            btn.innerHTML = b.label;
            btn.addEventListener('mousedown', function(e) {
                e.preventDefault();
                if (b.prompt) {
                    var val = prompt(b.prompt);
                    if (val) document.execCommand(b.cmd, false, val);
                } else {
                    document.execCommand(b.cmd, false, b.value || null);
                }
                updateToolbarState();
            });
            btn.dataset.cmd = b.cmd;
            if (b.value) btn.dataset.value = b.value;
            toolbar.appendChild(btn);
        });
    }

    function updateToolbarState() {
        if (!toolbar) return;
        toolbar.querySelectorAll('button[data-cmd]').forEach(function(btn) {
            try {
                if (btn.dataset.value) {
                    var block = document.queryCommandValue('formatBlock');
                    btn.classList.toggle('active', block.toLowerCase() === btn.dataset.value.toLowerCase());
                } else {
                    btn.classList.toggle('active', document.queryCommandState(btn.dataset.cmd));
                }
            } catch(e) {}
        });
    }

    document.addEventListener('focusin', function(e) {
        if (!editing) return;
        var el = e.target.closest('[data-cms-type="html"], [data-cms-type="text"]');
        if (el) {
            focusedHtml = el;
            setToolbarMode(el.dataset.cmsType);
            if (toolbar) toolbar.classList.add('visible');
            updateToolbarState();
        }
    });

    document.addEventListener('focusout', function(e) {
        if (!editing || !focusedHtml) return;
        setTimeout(function() {
            var active = document.activeElement;
            if (active && (active.closest('[data-cms-type="html"], [data-cms-type="text"]') || (toolbar && toolbar.contains(active)))) return;
            focusedHtml = null;
            if (toolbar) toolbar.classList.remove('visible');
        }, 0);
    });

    document.addEventListener('selectionchange', function() {
        if (focusedHtml) updateToolbarState();
    });

    document.addEventListener('click', function(e) {
        if (!editing) return;
        if (longPressFired) { longPressFired = false; e.preventDefault(); return; }
        var el = e.target.closest('[data-cms-type="image"]');
        if (el) {
            e.preventDefault();
            window.parent.postMessage({
                type: 'cms-pick-image',
                context: el.dataset.cmsContext,
                slug: el.dataset.cmsSlug,
                aspect: el.dataset.cmsAspect || ''
            }, '*');
        }
    });

    // -- Long-press to enter edit mode on a field --
    var pressTimer = null;
    var pressEl = null;
    var longPressFired = false;
    var PRESS_DURATION = 500;

    function onPressStart(e) {
        if (editing) return;
        var el = e.target.closest('[data-cms-type]');
        if (!el) return;

        pressEl = el;
        longPressFired = false;
        el.classList.add('cms-pressing');

        pressTimer = setTimeout(function() {
            longPressFired = true;
            el.classList.remove('cms-pressing');
            enterEditMode();
            if (window !== top && top.CMS && top.CMS.setEditUI) top.CMS.setEditUI(true, false);

            if (el.dataset.cmsType === 'image') {
                window.parent.postMessage({
                    type: 'cms-pick-image',
                    context: el.dataset.cmsContext,
                    slug: el.dataset.cmsSlug,
                    aspect: el.dataset.cmsAspect || ''
                }, '*');
            } else {
                el.focus();
                var sel = window.getSelection();
                var range = document.createRange();
                range.selectNodeContents(el);
                range.collapse(false);
                sel.removeAllRanges();
                sel.addRange(range);
            }
        }, PRESS_DURATION);
    }

    function onPressEnd() {
        clearTimeout(pressTimer);
        if (pressEl) {
            pressEl.classList.remove('cms-pressing');
            pressEl = null;
        }
    }

    document.addEventListener('mousedown', onPressStart);
    document.addEventListener('mouseup', onPressEnd);
    document.addEventListener('mouseleave', onPressEnd);
    document.addEventListener('touchstart', onPressStart, {passive: true});
    document.addEventListener('touchend', onPressEnd);
    document.addEventListener('touchcancel', onPressEnd);
    document.addEventListener('touchmove', onPressEnd);
    document.addEventListener('contextmenu', function(e) {
        if (pressTimer) e.preventDefault();
    });

    window.addEventListener('message', function(e) {
        if (!e.data || !e.data.type) return;
        if (e.data.type === 'cms-enter-edit') enterEditMode();
        else if (e.data.type === 'cms-exit-edit') exitEditMode();
        else if (e.data.type === 'cms-cancel-edit') cancelEditMode();
        else if (e.data.type === 'cms-image-picked') {
            var el = document.querySelector(
                '[data-cms-type="image"][data-cms-context="' + e.data.context + '"][data-cms-slug="' + e.data.slug + '"]'
            );
            if (el) {
                var img = el.tagName === 'IMG' ? el : el.querySelector('img');
                if (!img) {
                    img = document.createElement('img');
                    img.alt = '';
                    el.appendChild(img);
                }
                img.src = e.data.displaySrc || e.data.src;
                img.removeAttribute('srcset');
                el.dataset.cmsSrc = e.data.src;
                var key = e.data.context + '::' + e.data.slug;
                pendingImages[key] = {context: e.data.context, slug: e.data.slug, src: e.data.src};
                markDirty();
            }
        }
    });
})();
</script>
<?php $this->end(); ?>
<?php endif; ?>
