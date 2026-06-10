var streaming = false;
var currentPage = null;

function getElements() {
    var messages = document.getElementById('ai-messages');
    var prompt = document.getElementById('ai-prompt');
    var sendBtn = document.getElementById('ai-send');
    return { messages: messages, prompt: prompt, sendBtn: sendBtn };
}

function appendMessage(role, html) {
    var els = getElements();
    if (!els.messages) return null;
    var div = document.createElement('div');
    div.className = 'ai-msg ai-msg-' + role;
    div.innerHTML = html;
    els.messages.appendChild(div);
    els.messages.scrollTop = els.messages.scrollHeight;
    return div;
}

function escapeHtml(str) {
    var d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}

function getPromptContent() {
    var el = getElements().prompt;
    if (!el) return '';
    return el.innerHTML.trim();
}

function clearPrompt() {
    var el = getElements().prompt;
    if (el) el.innerHTML = '';
}

function isPromptEmpty() {
    var el = getElements().prompt;
    if (!el) return true;
    var text = el.textContent.trim();
    var hasImages = el.querySelector('img');
    return !text && !hasImages;
}

export function init(page) {
    currentPage = page || null;
    fetch('/admin/api/ai/status')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.available === false) {
                showUnavailable();
                return;
            }
            loadHistory();
        })
        .catch(function() {
            loadHistory();
        });
}

export function checkStatus() {
    return fetch('/admin/api/ai/status')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.available === false) return false;
            return data.processing === true;
        })
        .catch(function() { return false; });
}

function showUnavailable() {
    var els = getElements();
    if (!els.messages) return;
    els.messages.innerHTML =
        '<div class="text-center py-5" style="max-width:420px;margin:0 auto;">' +
            '<i class="bi bi-cpu" style="font-size:2.5rem;color:#aaa;"></i>' +
            '<h5 class="mt-3" style="font-size:1rem;">No AI Assistant Available</h5>' +
            '<p style="font-size:0.85rem;color:#666;line-height:1.6;">' +
                'No supported coding agent was detected in this environment. ' +
                'Install one of the following to enable the AI assistant:' +
            '</p>' +
            '<div style="text-align:left;background:#f8f9fa;border-radius:6px;padding:0.75rem 1rem;font-size:0.85rem;">' +
                '<div style="margin-bottom:0.5rem;"><strong>Claude Code</strong><br>' +
                    '<span style="color:#666;">Install via <code>npm install -g @anthropic-ai/claude-code</code></span></div>' +
                '<div><strong>Gemini CLI</strong><br>' +
                    '<span style="color:#666;">Install via <code>npm install -g @google/gemini-cli</code></span></div>' +
            '</div>' +
        '</div>';
    if (els.prompt) els.prompt.contentEditable = 'false';
    if (els.sendBtn) els.sendBtn.disabled = true;
}

export function resumeStream() {
    if (streaming) return;
    var els = getElements();
    if (!els.messages) return;

    setStreamingUI(true);
    streamState = {
        div: appendMessage('assistant', '<span class="ai-streaming-indicator"></span>'),
        currentMsgId: null,
        contentBuffer: '',
        toolBuffer: [],
        lastToolUses: {},
        streamPos: 0,
        retries: 0
    };
    connectStream();
}

function loadHistory() {
    var els = getElements();
    if (!els.messages) return;

    fetch('/admin/api/ai/history')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.messages || !data.messages.length) return;

            var welcome = els.messages.querySelector('.text-center');
            if (welcome) welcome.remove();

            for (var i = 0; i < data.messages.length; i++) {
                var msg = data.messages[i];
                appendMessage(msg.role, renderBlocks(msg.content));
            }
            els.messages.scrollTop = els.messages.scrollHeight;
        })
        .catch(function() {});
}

function renderBlocks(content) {
    if (typeof content === 'string') return formatText(content);
    if (!Array.isArray(content)) return '';
    var html = '';
    for (var i = 0; i < content.length; i++) {
        var block = content[i];
        if (block.type === 'text') {
            html += formatText(block.text);
        } else if (block.type === 'tool_use') {
            html += renderToolUse(block);
        }
    }
    return html;
}

export function newConversation() {
    fetch('/admin/api/ai/reset', { method: 'POST' })
        .then(function() {
            var els = getElements();
            if (els.messages) {
                els.messages.innerHTML =
                    '<div class="text-center text-muted py-3">' +
                    '<i class="bi bi-chat-dots" style="font-size:2rem;"></i>' +
                    '<p class="mt-2 mb-0" style="font-size:0.85rem;">New conversation started.</p>' +
                    '</div>';
            }
        });
}

export function send() {
    var els = getElements();
    if (!els.prompt || streaming) return;

    if (isPromptEmpty()) return;

    var html = getPromptContent();
    clearPrompt();
    setStreamingUI(true);

    var welcome = els.messages.querySelector('.text-center');
    if (welcome) welcome.remove();

    appendMessage('user', html);

    streamState = {
        div: appendMessage('assistant', '<span class="ai-streaming-indicator"></span>'),
        currentMsgId: null,
        contentBuffer: '',
        toolBuffer: [],
        lastToolUses: {},
        streamPos: 0,
        retries: 0
    };

    fetch('/admin/api/ai/prompt', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ prompt: html, page: currentPage })
    }).then(function(response) {
        if (response.status === 409) throw new Error('Agent is already working');
        if (!response.ok) throw new Error('HTTP ' + response.status);
        return response.json();
    }).then(function() {
        connectStream();
    }).catch(function(err) {
        appendMessage('error', '<i class="bi bi-exclamation-triangle"></i> ' + escapeHtml(err.message));
        finishStream();
    });
}

var streamState = null;
var MAX_RETRIES = 15;

function connectStream() {
    if (!streamState) return;

    fetch('/admin/api/ai/stream?pos=' + streamState.streamPos)
        .then(function(response) {
            if (!response.ok) throw new Error('HTTP ' + response.status);
            streamState.retries = 0;
            var reader = response.body.getReader();
            var decoder = new TextDecoder();
            var buffer = '';

            function pump() {
                return reader.read().then(function(result) {
                    if (result.done) {
                        finishStream();
                        return;
                    }

                    buffer += decoder.decode(result.value, { stream: true });
                    var lines = buffer.split('\n');
                    buffer = lines.pop();

                    for (var i = 0; i < lines.length; i++) {
                        var line = lines[i].trim();
                        if (!line) continue;
                        try {
                            var wrapper = JSON.parse(line);
                            if (wrapper.pos !== undefined) {
                                streamState.streamPos = wrapper.pos;
                            }
                            if (wrapper.msg) {
                                handleStreamMessage(wrapper.msg);
                            }
                        } catch (e) {}
                    }

                    return pump();
                });
            }

            return pump();
        }).catch(function() {
            if (!streamState) return;
            streamState.retries++;
            if (streamState.retries <= MAX_RETRIES) {
                setTimeout(connectStream, Math.min(1000 * streamState.retries, 5000));
            } else {
                appendMessage('error', '<i class="bi bi-exclamation-triangle"></i> Connection lost after multiple retries.');
                finishStream();
            }
        });
}

function handleStreamMessage(msg) {
    if (!streamState) return;

    if (msg.type === 'assistant' && msg.message && msg.message.content) {
        var msgId = msg.message.id;

        if (msgId && msgId !== streamState.currentMsgId) {
            if (streamState.currentMsgId) {
                finalizeCurrentDiv();
                streamState.div = appendMessage('assistant', '<span class="ai-streaming-indicator"></span>');
            }
            streamState.currentMsgId = msgId;
            streamState.contentBuffer = '';
            streamState.toolBuffer = [];
        }

        var content = msg.message.content;
        streamState.contentBuffer = '';
        streamState.toolBuffer = [];

        for (var i = 0; i < content.length; i++) {
            var block = content[i];
            if (block.type === 'text') {
                streamState.contentBuffer += block.text;
            } else if (block.type === 'tool_use') {
                streamState.toolBuffer.push(block);
                streamState.lastToolUses[block.id] = block;
            }
        }

        renderAssistant();
    }

    if (msg.type === 'user' && msg.message && msg.message.content) {
        for (var i = 0; i < msg.message.content.length; i++) {
            var block = msg.message.content[i];
            if (block.type === 'tool_result' && block.tool_use_id) {
                var existing = document.querySelector('[data-tool-id="' + block.tool_use_id + '"]');
                if (existing) {
                    var icon = existing.querySelector('.tool-status-icon');
                    if (icon) {
                        icon.innerHTML = block.is_error ? '&#10007;' : '&#10003;';
                    }
                    if (block.is_error) existing.classList.add('ai-msg-tool-error');
                }
            }
        }
    }

    if (msg.type === 'error') {
        appendMessage('error', '<i class="bi bi-exclamation-triangle"></i> ' + escapeHtml(msg.message || 'Unknown error'));
    }
}

function finalizeCurrentDiv() {
    if (!streamState || !streamState.div) return;
    var indicator = streamState.div.querySelector('.ai-streaming-indicator');
    if (indicator) indicator.remove();
    if (!streamState.div.innerHTML.trim()) streamState.div.remove();
}


function renderAssistant() {
    if (!streamState || !streamState.div) return;
    var html = '';
    if (streamState.contentBuffer) {
        html += formatText(streamState.contentBuffer);
    }
    for (var i = 0; i < streamState.toolBuffer.length; i++) {
        html += renderToolUse(streamState.toolBuffer[i]);
    }
    html += '<span class="ai-streaming-indicator"></span>';
    streamState.div.innerHTML = html;
    var msgs = getElements().messages;
    if (msgs) msgs.scrollTop = msgs.scrollHeight;
}

function setStreamingUI(active) {
    streaming = active;
    var els = getElements();
    if (els.prompt) els.prompt.contentEditable = active ? 'false' : 'true';
    if (els.sendBtn) els.sendBtn.disabled = active;
    if (!active && els.prompt) els.prompt.focus();
}

function finishStream() {
    if (streamState && streamState.div) {
        var indicator = streamState.div.querySelector('.ai-streaming-indicator');
        if (indicator) indicator.remove();
        if (!streamState.div.innerHTML.trim()) streamState.div.remove();
    }
    streamState = null;
    setStreamingUI(false);
}

function formatText(text) {
    var parts = text.split(/(```[\s\S]*?```)/g);
    var html = '';
    for (var i = 0; i < parts.length; i++) {
        var part = parts[i];
        if (part.startsWith('```')) {
            var inner = part.slice(3);
            var nlIdx = inner.indexOf('\n');
            if (nlIdx > -1) inner = inner.slice(nlIdx + 1);
            if (inner.endsWith('```')) inner = inner.slice(0, -3);
            html += '<pre>' + escapeHtml(inner.trim()) + '</pre>';
        } else {
            html += escapeHtml(part).replace(/\n/g, '<br>');
        }
    }
    return html;
}

function toolSummary(block) {
    var input = block.input || {};

    if (input.description) return input.description;

    if (input.file_path) {
        var parts = input.file_path.split('/');
        return parts.length > 3
            ? '…/' + parts.slice(-2).join('/')
            : input.file_path;
    }

    if (input.command) {
        var cmd = input.command
            .replace(/^export\s+PATH=[^;]*;\s*/g, '')
            .replace(/^cd\s+\S+\s*&&\s*/g, '')
            .trim();
        return cmd.length > 80 ? cmd.substring(0, 80) + '…' : cmd;
    }

    return '';
}

function toolLabel(name) {
    var labels = {
        Bash: 'Terminal',
        Read: 'Read',
        Write: 'Write',
        Edit: 'Edit',
        Grep: 'Search'
    };
    return labels[name] || name || 'tool';
}

function renderToolUse(block) {
    var label = toolLabel(block.name);
    var summary = toolSummary(block);
    var idAttr = block.id ? ' data-tool-id="' + escapeHtml(block.id) + '"' : '';
    return '<div class="ai-msg-tool"' + idAttr + '>'
        + '<span class="tool-status-icon"><i class="bi bi-hourglass-split"></i></span> '
        + '<span class="tool-name">' + escapeHtml(label) + '</span>'
        + (summary ? ' <span style="color:#888">' + escapeHtml(summary) + '</span>' : '')
        + '</div>';
}

// -- Image picker (modal media browser) --

var mediaResolve = null;

function openMediaPicker() {
    return new Promise(function(resolve) {
        mediaResolve = resolve;
        var overlay = document.createElement('div');
        overlay.className = 'ai-media-overlay';
        overlay.id = 'ai-media-overlay';
        overlay.innerHTML =
            '<div class="ai-media-modal">' +
                '<div class="ai-media-modal-header">' +
                    '<h6>Select Image</h6>' +
                    '<button type="button" style="border:none;background:none;font-size:1.2rem;cursor:pointer;" onclick="CMS.ai.closeMediaPicker()">&times;</button>' +
                '</div>' +
                '<div class="ai-media-modal-body">' +
                    '<div class="media-breadcrumb" id="ai-media-breadcrumb"></div>' +
                    '<div id="ai-media-folders"></div>' +
                    '<div class="media-grid" id="ai-media-grid"></div>' +
                '</div>' +
            '</div>';
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) closeMediaPicker();
        });
        document.body.appendChild(overlay);
        loadMediaFolder('');
    });
}

function loadMediaFolder(path) {
    fetch('/admin/api/media/?path=' + encodeURIComponent(path) + '&_t=' + Date.now())
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var bc = document.getElementById('ai-media-breadcrumb');
            var folders = document.getElementById('ai-media-folders');
            var grid = document.getElementById('ai-media-grid');
            if (!bc || !grid) return;

            var parts = data.path ? data.path.split('/') : [];
            var bcHtml = '<a onclick="CMS.ai.mediaNav(\'\')">uploads</a>';
            var accum = '';
            for (var i = 0; i < parts.length; i++) {
                accum += (accum ? '/' : '') + parts[i];
                bcHtml += ' / <a onclick="CMS.ai.mediaNav(\'' + escapeHtml(accum) + '\')">' + escapeHtml(parts[i]) + '</a>';
            }
            bc.innerHTML = bcHtml;

            var foldersHtml = '';
            if (data.folders) {
                for (var i = 0; i < data.folders.length; i++) {
                    var f = data.folders[i];
                    foldersHtml += '<button class="media-folder-btn" onclick="CMS.ai.mediaNav(\'' + escapeHtml(f.path) + '\')">'
                        + '<i class="bi bi-folder-fill"></i> ' + escapeHtml(f.name) + '</button>';
                }
            }
            folders.innerHTML = foldersHtml;

            var gridHtml = '';
            if (data.files) {
                for (var i = 0; i < data.files.length; i++) {
                    var file = data.files[i];
                    if (!file.type || !file.type.startsWith('image/')) continue;
                    gridHtml += '<div class="media-thumb" onclick="CMS.ai.selectMedia(\'' + escapeHtml(file.url) + '\')">'
                        + '<img src="' + escapeHtml(file.url) + '" alt="' + escapeHtml(file.name) + '">'
                        + '</div>';
                }
            }
            grid.innerHTML = gridHtml || '<p class="text-muted" style="font-size:0.82rem;">No images in this folder.</p>';
        });
}

export function mediaNav(path) {
    loadMediaFolder(path);
}

export function selectMedia(url) {
    closeMediaPicker();
    if (mediaResolve) {
        mediaResolve(url);
        mediaResolve = null;
    }
}

export function closeMediaPicker() {
    var overlay = document.getElementById('ai-media-overlay');
    if (overlay) overlay.remove();
    if (mediaResolve) {
        mediaResolve(null);
        mediaResolve = null;
    }
}

export function pickImage() {
    openMediaPicker().then(function(url) {
        if (!url) return;
        var el = getElements().prompt;
        if (!el) return;
        el.focus();
        var img = document.createElement('img');
        img.src = url;
        img.alt = url.split('/').pop();
        insertNodeAtCaret(img);
    });
}

export function pickColor() {
    var input = document.getElementById('ai-color-input');
    if (!input) return;
    input.click();
}

function handleColorChange(e) {
    var color = e.target.value;
    var el = getElements().prompt;
    if (!el) return;
    el.focus();
    var swatch = document.createElement('span');
    swatch.className = 'ai-color-swatch';
    swatch.style.backgroundColor = color;
    swatch.title = color;
    swatch.dataset.color = color;
    insertNodeAtCaret(swatch);
    var space = document.createTextNode(' ');
    insertNodeAtCaret(space);
}

function insertNodeAtCaret(node) {
    var sel = window.getSelection();
    if (sel.rangeCount) {
        var range = sel.getRangeAt(0);
        range.collapse(false);
        range.insertNode(node);
        range.setStartAfter(node);
        range.collapse(true);
        sel.removeAllRanges();
        sel.addRange(range);
    }
}

// -- Event listeners --

document.addEventListener('keydown', function(e) {
    if (e.target && e.target.id === 'ai-prompt' && e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        send();
    }
    if (e.key === 'Escape') {
        closeMediaPicker();
    }
});

document.addEventListener('change', function(e) {
    if (e.target && e.target.id === 'ai-color-input') {
        handleColorChange(e);
    }
});
