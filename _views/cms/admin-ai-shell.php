<!doctype html>
<html lang="en">
<head>
    <script>if (window !== window.top) window.top.location.href = window.location.href;</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CMS - AI Assistant</title>
    <link rel="stylesheet" href="/admin/vendor/source-sans-3/index.css">
    <link rel="stylesheet" href="/admin/vendor/overlayscrollbars/overlayscrollbars.min.css">
    <link rel="stylesheet" href="/admin/vendor/bootstrap-icons/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/admin/vendor/adminlte/adminlte.min.css">
    <style>
        html, body { height: 100%; }
        .app-wrapper { min-height: 100vh; }
        .app-main { display: flex; flex-direction: column; }
        .ai-page {
            display: flex;
            flex-direction: column;
            flex: 1;
            height: calc(100vh - 57px);
        }
        .ai-messages {
            flex: 1;
            overflow-y: auto;
            padding: 1rem 1.5rem;
        }
        .ai-msg {
            margin-bottom: 0.75rem;
            font-size: 0.9rem;
            line-height: 1.6;
            max-width: 800px;
        }
        .ai-msg-user {
            background: #e8f0fe;
            border-radius: 8px 8px 2px 8px;
            padding: 0.6rem 1rem;
            margin-left: 4rem;
        }
        .ai-msg-user img {
            max-width: 120px;
            max-height: 80px;
            border-radius: 4px;
            border: 1px solid #ccc;
            vertical-align: middle;
        }
        .ai-msg-user .ai-color-swatch {
            display: inline-block;
            width: 18px;
            height: 18px;
            border-radius: 3px;
            border: 1px solid rgba(0,0,0,0.2);
            vertical-align: middle;
        }
        .ai-msg-assistant {
            background: #f8f9fa;
            border-radius: 8px 8px 8px 2px;
            padding: 0.6rem 1rem;
            margin-right: 2rem;
        }
        .ai-msg-assistant pre {
            background: #1e1e1e;
            color: #d4d4d4;
            border-radius: 4px;
            padding: 0.6rem;
            font-size: 0.82rem;
            overflow-x: auto;
            margin: 0.4rem 0;
        }
        .ai-msg-tool {
            font-size: 0.78rem;
            color: #666;
            border-left: 3px solid #dee2e6;
            padding: 0.25rem 0.6rem;
            margin: 0.3rem 0 0.3rem 0.5rem;
        }
        .ai-msg-tool .tool-name { font-weight: 600; color: #495057; }
        .ai-msg-error { color: #dc3545; font-size: 0.85rem; padding: 0.4rem 0.6rem; }
        .ai-input-bar {
            display: flex;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-top: 1px solid #dee2e6;
            background: #fff;
            align-items: flex-end;
        }
        .ai-prompt-wrap {
            flex: 1;
            max-width: 800px;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            overflow: hidden;
        }
        .ai-prompt-toolbar {
            display: flex;
            gap: 2px;
            padding: 4px 6px;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
        }
        .ai-prompt-toolbar button {
            border: none;
            background: none;
            padding: 3px 8px;
            cursor: pointer;
            border-radius: 3px;
            font-size: 0.9rem;
            color: #555;
        }
        .ai-prompt-toolbar button:hover { background: #e9ecef; color: #333; }
        .ai-prompt-toolbar input[type=color] {
            width: 26px;
            height: 26px;
            padding: 0;
            border: 1px solid #ccc;
            border-radius: 3px;
            cursor: pointer;
        }
        .ai-prompt-content {
            min-height: 2.8em;
            max-height: 10em;
            overflow-y: auto;
            padding: 0.5rem 0.75rem;
            font-size: 0.9rem;
            outline: none;
            line-height: 1.5;
        }
        .ai-prompt-content:empty:before {
            content: attr(data-placeholder);
            color: #aaa;
            pointer-events: none;
        }
        .ai-prompt-content img {
            max-width: 100px;
            max-height: 70px;
            vertical-align: middle;
            border-radius: 4px;
            border: 1px solid #dee2e6;
            margin: 0 3px;
        }
        .ai-prompt-content .ai-color-swatch {
            display: inline-block;
            width: 18px;
            height: 18px;
            border-radius: 3px;
            border: 1px solid rgba(0,0,0,0.2);
            vertical-align: middle;
            margin: 0 2px;
        }
        .ai-input-bar > button {
            flex-shrink: 0;
            height: 40px;
            width: 40px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .ai-streaming-indicator {
            display: inline-block;
            width: 6px;
            height: 14px;
            background: #666;
            animation: blink 0.8s infinite;
            vertical-align: text-bottom;
        }
        @keyframes blink { 50% { opacity: 0; } }

        .ai-media-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .ai-media-modal {
            background: #fff;
            border-radius: 8px;
            width: 640px;
            max-width: 90vw;
            max-height: 80vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 8px 32px rgba(0,0,0,0.2);
        }
        .ai-media-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #dee2e6;
        }
        .ai-media-modal-header h6 { margin: 0; font-size: 0.95rem; }
        .ai-media-modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 0.75rem 1rem;
        }
        .ai-media-modal .media-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 0.5rem;
        }
        .ai-media-modal .media-thumb {
            aspect-ratio: 1;
            border: 2px solid transparent;
            border-radius: 4px;
            overflow: hidden;
            cursor: pointer;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .ai-media-modal .media-thumb:hover { border-color: #86b7fe; }
        .ai-media-modal .media-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .ai-media-modal .media-breadcrumb { font-size: 0.82rem; margin-bottom: 0.5rem; color: #666; }
        .ai-media-modal .media-breadcrumb a { color: #0d6efd; cursor: pointer; text-decoration: none; }
        .ai-media-modal .media-breadcrumb a:hover { text-decoration: underline; }
        .ai-media-modal .media-folder-btn {
            display: inline-flex; align-items: center; gap: 0.3rem;
            padding: 0.3rem 0.6rem; font-size: 0.82rem;
            background: #f0f0f0; border: 1px solid #dee2e6;
            border-radius: 4px; cursor: pointer; margin: 0 0.3rem 0.3rem 0;
        }
        .ai-media-modal .media-folder-btn:hover { background: #e0e0e0; }
    </style>
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
    <div class="app-wrapper">
        <nav class="app-header navbar navbar-expand bg-body">
            <div class="container-fluid">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button"><i class="bi bi-list"></i></a>
                    </li>
                    <li class="nav-item">
                        <span class="nav-link fw-semibold"><i class="bi bi-chat-dots"></i> AI Assistant</span>
                    </li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link text-danger" href="/login?logout=1" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
                    </li>
                </ul>
            </div>
        </nav>

        <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
            <div class="sidebar-brand">
                <a href="/" class="brand-link">
                    <span class="brand-text fw-light">CMS</span>
                </a>
            </div>
            <div class="sidebar-wrapper">
                <nav class="mt-2">
                    <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu">
                        <li class="nav-header">PATHS</li>
                        <?php
                        $routeTree = \mini\Mini::$mini->get(\MiniCms\Content::class)->routeTree();
                        $renderTree = function(array $nodes) use (&$renderTree) {
                            foreach ($nodes as $node):
                                $hasChildren = !empty($node['children']);
                        ?>
                        <li class="nav-item">
                            <?php if ($hasChildren): ?>
                            <a href="<?= \mini\h($node['path'] ?? '#') ?>" class="nav-link">
                                <i class="nav-icon bi bi-folder"></i>
                                <p><?= \mini\h($node['label']) ?> <i class="nav-arrow bi bi-chevron-right"></i></p>
                            </a>
                            <ul class="nav nav-treeview">
                                <?php $renderTree($node['children']); ?>
                            </ul>
                            <?php else: ?>
                            <a href="<?= \mini\h($node['path'] ?? '#') ?>" class="nav-link">
                                <i class="nav-icon bi bi-link-45deg"></i>
                                <p><?= \mini\h($node['label']) ?></p>
                            </a>
                            <?php endif; ?>
                        </li>
                        <?php endforeach;
                        };
                        $renderTree($routeTree);
                        ?>
                        <?php
                        $cmsModels = \mini\Mini::$mini->get(\MiniCms\Content::class)->models();
                        if ($cmsModels): ?>
                        <li class="nav-header">DATA</li>
                        <?php foreach ($cmsModels as $modelSlug => $modelEntity): ?>
                        <li class="nav-item">
                            <a href="/admin/data/<?= \mini\h($modelSlug) ?>/" class="nav-link">
                                <i class="nav-icon bi <?= \mini\h($modelEntity->getIcon()) ?>"></i>
                                <p><?= \mini\h($modelEntity->getPluralTitle()) ?></p>
                            </a>
                        </li>
                        <?php endforeach; ?>
                        <?php endif; ?>
                        <li class="nav-header">TOOLS</li>
                        <li class="nav-item">
                            <a href="/admin/ai/" class="nav-link active">
                                <i class="nav-icon bi bi-chat-dots"></i>
                                <p>AI Assistant</p>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        </aside>

        <main class="app-main">
            <div class="ai-page">
                <div class="ai-messages" id="ai-messages">
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-chat-dots" style="font-size:2.5rem;"></i>
                        <p class="mt-2">Ask Claude to edit views, create components, modify styles, or manage data.<br>
                        Use the image button to reference images from the media library, or the color picker to specify colors.</p>
                    </div>
                </div>
                <div class="ai-input-bar">
                    <div class="ai-prompt-wrap">
                        <div class="ai-prompt-toolbar">
                            <button type="button" title="Insert image from media library" onclick="CMS.ai.pickImage()"><i class="bi bi-image"></i></button>
                            <button type="button" title="Insert color" onclick="CMS.ai.pickColor()"><i class="bi bi-palette"></i></button>
                            <input type="color" id="ai-color-input" value="#000000">
                        </div>
                        <div id="ai-prompt" class="ai-prompt-content" contenteditable="true" data-placeholder="Ask Claude to edit the site..."></div>
                    </div>
                    <button id="ai-send" class="btn btn-primary" onclick="CMS.ai.send()"><i class="bi bi-send"></i></button>
                </div>
                <div style="padding:0.3rem 1.5rem;text-align:right;">
                    <a href="#" onclick="CMS.ai.newConversation(); return false;" style="font-size:0.78rem;color:#888;text-decoration:none;">New conversation</a>
                </div>
            </div>
        </main>
    </div>

    <script src="/admin/dist/cms.min.js"></script>
    <script>CMS.ai.init(null);</script>
</body>
</html>
