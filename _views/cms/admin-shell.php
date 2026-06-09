<!doctype html>
<html lang="en">
<head>
    <script>if (window !== window.top) window.top.location.href = window.location.href;</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CMS - <?= \mini\h($currentPath) ?></title>
    <link rel="stylesheet" href="/admin/vendor/source-sans-3/index.css">
    <link rel="stylesheet" href="/admin/vendor/overlayscrollbars/overlayscrollbars.min.css">
    <link rel="stylesheet" href="/admin/vendor/bootstrap-icons/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/admin/vendor/cropper/cropper.min.css">
    <link rel="stylesheet" href="/admin/vendor/adminlte/adminlte.min.css">
    <style>
        html, body { height: 100%; overflow: hidden; }
        .app-wrapper { height: 100vh !important; min-height: 100vh !important; }

        .app-main {
            overflow: hidden !important;
            position: relative;
        }

        /* Workspace: just the iframe, full size */
        .cms-workspace {
            height: 100%;
            position: relative;
        }

        #site-iframe {
            border: none;
            width: 100%;
            height: 100%;
            display: block;
        }

        /*
         * Drawer container overlays the preview area.
         * Positioned over app-main, anchored to left edge.
         * Uses flex row: [collapsed tabs...] [active drawer]
         * No reflow of anything underneath.
         */
        #drawer-container {
            position: absolute;
            top: 0;
            left: 0;
            bottom: 0;
            z-index: 100;
            display: flex;
            pointer-events: none; /* clicks pass through to iframe when no drawers */
        }

        /* Each drawer is a flex column (header + body), placed side by side */
        .cms-drawer {
            pointer-events: auto;
            display: flex;
            flex-direction: column;
            background: #fff;
            border-right: 1px solid #c0c0c0;
            box-shadow: 2px 0 8px rgba(0,0,0,0.08);
            overflow: hidden;
            /* Animate width */
            transition: width 0.2s ease;
            width: 0;
        }

        /* Active (topmost) drawer */
        .cms-drawer.active {
            width: 560px;
        }
        .cms-drawer.active.drawer-wide {
            width: 700px;
        }

        /* Collapsed drawers: narrow vertical tab */
        .cms-drawer.collapsed {
            width: 36px;
            cursor: pointer;
        }

        /* -- Drawer header -- */
        .cms-drawer-header {
            display: flex;
            align-items: center;
            padding: 0.5rem 0.75rem;
            border-bottom: 1px solid #dee2e6;
            background: #f8f9fa;
            flex-shrink: 0;
            gap: 0.5rem;
            min-height: 42px;
        }

        .cms-drawer-header h6 {
            margin: 0;
            font-size: 0.85rem;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            flex: 1;
        }

        .cms-drawer-close {
            border: none;
            background: none;
            padding: 0.15rem 0.35rem;
            cursor: pointer;
            border-radius: 3px;
            font-size: 1rem;
            line-height: 1;
            color: #666;
            flex-shrink: 0;
        }
        .cms-drawer-close:hover { background: #e9ecef; color: #333; }

        /* -- Drawer body -- */
        .cms-drawer-body {
            flex: 1;
            overflow-y: auto;
            padding: 0.75rem;
        }

        /* -- Collapsed state: vertical tab appearance -- */
        .cms-drawer.collapsed .cms-drawer-body { display: none; }
        .cms-drawer.collapsed .cms-drawer-header h6 { display: none; }
        .cms-drawer.collapsed .cms-drawer-close { display: none; }

        .cms-drawer.collapsed .cms-drawer-header {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            padding: 0.75rem 0.3rem;
            border-bottom: none;
            justify-content: flex-start;
            height: 100%;
            background: #eef0f2;
        }

        .cms-drawer.collapsed:hover .cms-drawer-header {
            background: #dde0e4;
        }

        .cms-drawer-tab {
            font-size: 0.78rem;
            font-weight: 600;
            color: #555;
            white-space: nowrap;
        }

        /* Hide tab label in active drawer (title shown in h6 instead) */
        .cms-drawer.active .cms-drawer-tab { display: none; }

        /* -- Component editor styles -- */
        .cms-field-group { margin-bottom: 1rem; }
        .cms-field-group-title {
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            color: #888;
            padding: 0.4rem 0;
            border-bottom: 1px solid #eee;
            margin-bottom: 0.6rem;
        }
        .cms-field-label {
            font-weight: 600;
            font-size: 0.82rem;
            margin-bottom: 0.2rem;
        }
        .cms-field-wrap { margin-bottom: 0.75rem; }

        .tiptap-editor {
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
            min-height: 100px;
            padding: 0.5rem;
            background: #fff;
        }
        .tiptap-editor:focus { outline: none; border-color: #86b7fe; box-shadow: 0 0 0 0.2rem rgba(13,110,253,.25); }

        /* -- Media Library -- */
        .media-layout {
            display: flex;
            height: 100%;
            margin: -0.75rem; /* undo drawer-body padding */
        }
        .media-folders {
            width: 160px;
            flex-shrink: 0;
            border-right: 1px solid #eee;
            overflow-y: auto;
            padding: 0.5rem 0;
            background: #fafafa;
        }
        .media-files {
            flex: 1;
            overflow-y: auto;
            padding: 0.5rem;
            position: relative;
        }

        .media-folder-item {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.3rem 0.6rem;
            font-size: 0.82rem;
            cursor: pointer;
            color: #444;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
        }
        .media-folder-item:hover { background: #eef0f2; }
        .media-folder-item.active { background: #dde4ea; font-weight: 600; }
        .media-folder-item .bi { font-size: 0.9rem; }

        .media-folder-children {
            padding-left: 1rem;
        }

        .media-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 0.5rem;
        }

        .media-thumb {
            aspect-ratio: 1;
            border: 2px solid transparent;
            border-radius: 4px;
            overflow: hidden;
            cursor: pointer;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            transition: border-color 0.15s;
        }
        .media-thumb:hover { border-color: #86b7fe; }
        .media-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .media-thumb-icon {
            font-size: 2rem;
            color: #aaa;
        }
        .media-thumb-name {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0,0,0,0.6);
            color: #fff;
            font-size: 0.65rem;
            padding: 2px 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .media-toolbar {
            display: flex;
            gap: 0.4rem;
            padding: 0 0.5rem 0.5rem;
            align-items: center;
            flex-shrink: 0;
            border-bottom: 1px solid #eee;
            margin-bottom: 0.5rem;
        }

        .media-breadcrumb {
            font-size: 0.8rem;
            color: #666;
            flex: 1;
        }
        .media-breadcrumb a { color: #0d6efd; text-decoration: none; }
        .media-breadcrumb a:hover { text-decoration: underline; }

        .media-dropzone {
            border: 2px dashed #ccc;
            border-radius: 6px;
            padding: 2rem;
            text-align: center;
            color: #aaa;
            margin-bottom: 0.5rem;
            display: none;
        }
        .media-dropzone.dragover {
            border-color: #86b7fe;
            background: #f0f7ff;
            color: #0d6efd;
        }
        .media-files.dragging .media-dropzone { display: block; }

        /* Drag-and-drop states */
        .media-dragging { opacity: 0.4; }
        .media-drop-hover {
            outline: 2px solid #0d6efd !important;
            outline-offset: -2px;
            background: #e8f0fe !important;
        }
        .media-thumb[draggable] { cursor: grab; }
        .media-thumb[draggable]:active { cursor: grabbing; }

        /* Media preview sub-drawer */
        .media-preview { text-align: center; }
        .media-preview img {
            max-width: 100%;
            max-height: 300px;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        .media-preview-meta { font-size: 0.82rem; color: #666; }
        .media-preview-meta dt { font-weight: 600; }
        .media-preview-meta dd { margin-bottom: 0.5rem; }
        .media-preview-url {
            font-size: 0.78rem;
            background: #f5f5f5;
            padding: 0.3rem 0.5rem;
            border-radius: 3px;
            word-break: break-all;
            user-select: all;
            cursor: pointer;
        }

        /* Image field in editor */
        .cms-image-field {
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
            overflow: hidden;
            cursor: pointer;
            transition: border-color 0.15s;
        }
        .cms-image-field:hover { border-color: #86b7fe; }
        .cms-image-thumb {
            background: #f0f0f0;
            min-height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .cms-image-thumb img {
            max-width: 100%;
            max-height: 200px;
            display: block;
        }
        .cms-image-thumb-empty {
            color: #aaa;
            font-size: 0.82rem;
            padding: 1.5rem;
            text-align: center;
        }
        .cms-image-actions {
            padding: 0.4rem;
            background: #f8f9fa;
            border-top: 1px solid #eee;
            display: flex;
            gap: 0.3rem;
            align-items: center;
            font-size: 0.78rem;
        }
        .cms-image-aspect-badge {
            font-size: 0.7rem;
            background: #e9ecef;
            padding: 0.1rem 0.4rem;
            border-radius: 3px;
            color: #666;
        }

        /* AI Chat */
        .ai-chat {
            display: flex;
            flex-direction: column;
            height: 100%;
            margin: -0.75rem;
        }
        .ai-messages {
            flex: 1;
            overflow-y: auto;
            padding: 0.75rem;
        }
        .ai-msg {
            margin-bottom: 0.75rem;
            font-size: 0.85rem;
            line-height: 1.5;
        }
        .ai-msg-user {
            background: #e8f0fe;
            border-radius: 8px 8px 2px 8px;
            padding: 0.5rem 0.75rem;
            margin-left: 2rem;
        }
        .ai-msg-assistant {
            background: #f8f9fa;
            border-radius: 8px 8px 8px 2px;
            padding: 0.5rem 0.75rem;
            margin-right: 1rem;
        }
        .ai-msg-assistant pre {
            background: #1e1e1e;
            color: #d4d4d4;
            border-radius: 4px;
            padding: 0.5rem;
            font-size: 0.78rem;
            overflow-x: auto;
            margin: 0.3rem 0;
        }
        .ai-msg-tool {
            font-size: 0.75rem;
            color: #666;
            border-left: 3px solid #dee2e6;
            padding: 0.25rem 0.5rem;
            margin: 0.3rem 0 0.3rem 0.5rem;
        }
        .ai-msg-tool .tool-name {
            font-weight: 600;
            color: #495057;
        }
        .ai-msg-error {
            color: #dc3545;
            font-size: 0.82rem;
            padding: 0.3rem 0.5rem;
        }
        .ai-input-bar {
            display: flex;
            gap: 0.4rem;
            padding: 0.5rem 0.75rem;
            border-top: 1px solid #dee2e6;
            background: #fff;
            align-items: flex-end;
        }
        .ai-prompt-wrap {
            flex: 1;
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
            overflow: hidden;
        }
        .ai-prompt-toolbar {
            display: flex;
            gap: 2px;
            padding: 3px 4px;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
        }
        .ai-prompt-toolbar button {
            border: none;
            background: none;
            padding: 2px 6px;
            cursor: pointer;
            border-radius: 3px;
            font-size: 0.82rem;
            color: #555;
        }
        .ai-prompt-toolbar button:hover { background: #e9ecef; color: #333; }
        .ai-prompt-toolbar input[type=color] {
            width: 22px;
            height: 22px;
            padding: 0;
            border: 1px solid #ccc;
            border-radius: 3px;
            cursor: pointer;
        }
        .ai-prompt-content {
            min-height: 2.4em;
            max-height: 8em;
            overflow-y: auto;
            padding: 0.35rem 0.5rem;
            font-size: 0.85rem;
            outline: none;
            line-height: 1.5;
        }
        .ai-prompt-content:empty:before {
            content: attr(data-placeholder);
            color: #aaa;
            pointer-events: none;
        }
        .ai-prompt-content img {
            max-width: 80px;
            max-height: 60px;
            vertical-align: middle;
            border-radius: 3px;
            border: 1px solid #dee2e6;
            margin: 0 2px;
        }
        .ai-prompt-content .ai-color-swatch {
            display: inline-block;
            width: 16px;
            height: 16px;
            border-radius: 3px;
            border: 1px solid rgba(0,0,0,0.2);
            vertical-align: middle;
            margin: 0 2px;
        }
        .ai-input-bar > button {
            flex-shrink: 0;
            height: 36px;
            width: 36px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .ai-streaming-indicator {
            display: inline-block;
            width: 6px;
            height: 12px;
            background: #666;
            animation: blink 0.8s infinite;
            vertical-align: text-bottom;
        }
        @keyframes blink { 50% { opacity: 0; } }

        /* AI media picker modal */
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
            width: 600px;
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
        .ai-media-modal-header h6 { margin: 0; font-size: 0.9rem; }
        .ai-media-modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 0.75rem;
        }
        .ai-media-modal .media-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
            gap: 0.4rem;
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
        .ai-media-modal .media-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .ai-media-modal .media-breadcrumb {
            font-size: 0.8rem;
            margin-bottom: 0.5rem;
            color: #666;
        }
        .ai-media-modal .media-breadcrumb a { color: #0d6efd; cursor: pointer; text-decoration: none; }
        .ai-media-modal .media-breadcrumb a:hover { text-decoration: underline; }
        .ai-media-modal .media-folder-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.3rem 0.6rem;
            font-size: 0.8rem;
            background: #f0f0f0;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            cursor: pointer;
            margin: 0 0.3rem 0.3rem 0;
        }
        .ai-media-modal .media-folder-btn:hover { background: #e0e0e0; }

        /* Crop tool */
        .crop-container { max-height: 400px; overflow: hidden; }
        .crop-container img { max-width: 100%; display: block; }

        /* Derivatives list in media preview */
        .derivatives-list { text-align: left; margin-top: 1rem; }
        .derivatives-list h6 { font-size: 0.82rem; font-weight: 600; margin-bottom: 0.4rem; }
        .derivative-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.3rem 0;
            font-size: 0.78rem;
            border-bottom: 1px solid #f0f0f0;
        }
        .derivative-item img {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 3px;
        }
    </style>
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:9999;" id="cms-toasts"></div>
    <div class="app-wrapper">
        <!-- Header -->
        <nav class="app-header navbar navbar-expand bg-body">
            <div class="container-fluid">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button"><i class="bi bi-list"></i></a>
                    </li>
                    <li class="nav-item">
                        <span class="nav-link text-muted"><?= \mini\h($currentPath) ?></span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link cms-edit-toggle" href="#" onclick="CMS.toggleEdit(); return false;" title="Edit page content"><i class="bi bi-pencil-square"></i> Edit</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link cms-edit-cancel text-danger" href="#" onclick="CMS.cancelEdit(); return false;" title="Discard changes" style="display:none"><i class="bi bi-x-lg"></i> Cancel</a>
                    </li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#" onclick="CMS.refreshPreview(); return false;" title="Refresh preview"><i class="bi bi-arrow-clockwise"></i></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= \mini\h($currentPath) ?>?_preview=1" target="_blank" title="Open in new tab"><i class="bi bi-box-arrow-up-right"></i></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-danger" href="/login?logout=1" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Sidebar -->
        <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
            <div class="sidebar-brand">
                <a href="/" class="brand-link">
                    <span class="brand-text fw-light">CMS</span>
                </a>
            </div>
            <div class="sidebar-wrapper">
                <nav class="mt-2">
                    <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu">
                        <?php foreach ($components as $groupName => $fields): $groupSlug = preg_replace('/[^a-z0-9]+/', '-', strtolower($groupName)); ?>
                        <li class="nav-item">
                            <a href="#" class="nav-link" onclick="CMS.openDrawer('group-<?= \mini\h($groupSlug) ?>', 'Edit <?= \mini\h($groupName) ?>'); return false;">
                                <i class="nav-icon bi bi-pencil-square"></i>
                                <p>Edit <?= \mini\h($groupName) ?></p>
                            </a>
                        </li>
                        <?php endforeach; ?>
                        <li class="nav-item">
                            <a href="#" class="nav-link" onclick="CMS.openMediaLibrary(); return false;">
                                <i class="nav-icon bi bi-images"></i>
                                <p>Media Library</p>
                            </a>
                        </li>
                        <li class="nav-header">PATHS</li>
                        <?php
                        $routeTree = \mini\Mini::$mini->get(\MiniCms\Content::class)->routeTree();
                        $subtreeContains = function(array $nodes, string $path) use (&$subtreeContains): bool {
                            foreach ($nodes as $node) {
                                if (isset($node['path']) && $node['path'] === $path) return true;
                                if (!empty($node['children']) && $subtreeContains($node['children'], $path)) return true;
                            }
                            return false;
                        };
                        $renderTree = function(array $nodes, int $depth = 0) use (&$renderTree, &$subtreeContains, $currentPath) {
                            foreach ($nodes as $node):
                                $hasChildren = !empty($node['children']);
                                $hasPath = isset($node['path']);
                                $isActive = $hasPath && $currentPath === $node['path'];
                                if ($hasChildren):
                                    $isOpen = $isActive || $subtreeContains($node['children'], $currentPath);
                        ?>
                        <li class="nav-item<?= $isOpen ? ' menu-open' : '' ?>">
                            <?php if ($hasPath): ?>
                            <a href="<?= \mini\h($node['path']) ?>" class="nav-link<?= $isActive ? ' active' : '' ?>" data-cms-nav>
                                <i class="nav-icon bi bi-folder"></i>
                                <p><?= \mini\h($node['label']) ?> <i class="nav-arrow bi bi-chevron-right"></i></p>
                            </a>
                            <?php else: ?>
                            <a href="#" class="nav-link">
                                <i class="nav-icon bi bi-folder"></i>
                                <p><?= \mini\h($node['label']) ?> <i class="nav-arrow bi bi-chevron-right"></i></p>
                            </a>
                            <?php endif; ?>
                            <ul class="nav nav-treeview">
                                <?php $renderTree($node['children'], $depth + 1); ?>
                            </ul>
                        </li>
                                <?php else: ?>
                        <li class="nav-item">
                            <a href="<?= \mini\h($node['path'] ?? '#') ?>" class="nav-link<?= $isActive ? ' active' : '' ?>">
                                <i class="nav-icon bi bi-link-45deg"></i>
                                <p><?= \mini\h($node['label']) ?></p>
                            </a>
                        </li>
                        <?php endif;
                            endforeach;
                        };
                        $renderTree($routeTree);
                        ?>
                        <?php
                        $cmsModels = \mini\Mini::$mini->get(\MiniCms\Content::class)->models();
                        if ($cmsModels): ?>
                        <li class="nav-header">DATA</li>
                        <?php foreach ($cmsModels as $slug => $entity): ?>
                        <li class="nav-item">
                            <a href="/admin/data/<?= \mini\h($slug) ?>/" class="nav-link<?= str_starts_with($currentPath, '/admin/data/' . $slug) ? ' active' : '' ?>">
                                <i class="nav-icon bi <?= \mini\h($entity->getIcon()) ?>"></i>
                                <p><?= \mini\h($entity->getPluralTitle()) ?></p>
                            </a>
                        </li>
                        <?php endforeach; ?>
                        <?php endif; ?>
                        <li class="nav-header">TOOLS</li>
                        <li class="nav-item">
                            <a href="#" class="nav-link" onclick="CMS.openDrawer('ai-assistant', 'AI Assistant'); CMS.ai.init(<?= \mini\h(json_encode($currentPath)) ?>); return false;">
                                <i class="nav-icon bi bi-chat-dots"></i>
                                <p>AI Assistant</p>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="app-main">
            <div class="cms-workspace">
                <iframe id="site-iframe" name="site-iframe" src="<?= \mini\h($iframeUrl) ?>"></iframe>
                <div id="drawer-container"></div>
            </div>
        </main>
    </div>

    <!-- Drawer content templates (hidden, cloned into drawers) — one per component group -->
    <?php foreach ($components as $groupName => $fields): $groupSlug = preg_replace('/[^a-z0-9]+/', '-', strtolower($groupName)); ?>
    <template id="tmpl-group-<?= \mini\h($groupSlug) ?>">
        <?php foreach ($fields as $fieldName => $field): ?>
        <div class="cms-field-wrap">
            <div class="cms-field-label"><?= \mini\h($fieldName) ?></div>
            <?php if ($field['type'] === 'text'): ?>
            <input type="text" class="form-control form-control-sm cms-field"
                data-file="<?= \mini\h($field['file']) ?>"
                data-path="<?= \mini\h($field['path']) ?>"
                data-type="text"
                data-pos="<?= $field['pos'] ?>"
                value="<?= \mini\h($field['value'] ?? $field['default']) ?>">
            <?php elseif ($field['type'] === 'html'): ?>
            <div class="cms-html-editor tiptap-editor"
                contenteditable="true"
                data-file="<?= \mini\h($field['file']) ?>"
                data-path="<?= \mini\h($field['path']) ?>"
                data-type="html"
                data-pos="<?= $field['pos'] ?>"><?= $field['value'] ?? $field['default'] ?></div>
            <?php elseif ($field['type'] === 'image'): ?>
            <div class="cms-image-field"
                data-file="<?= \mini\h($field['file']) ?>"
                data-path="<?= \mini\h($field['path']) ?>"
                data-type="image"
                data-pos="<?= $field['pos'] ?>"
                data-aspect="<?= \mini\h($field['aspect'] ?? '') ?>"
                onclick="CMS.pickImage(this)">
                <div class="cms-image-thumb">
                    <?php $imgVal = $field['value'] ?? $field['default']; ?>
                    <?php if ($imgVal): ?>
                    <img src="<?= \mini\h($imgVal) ?>" alt="">
                    <?php else: ?>
                    <div class="cms-image-thumb-empty"><i class="bi bi-image" style="font-size:2rem;"></i><br>Click to choose image</div>
                    <?php endif; ?>
                </div>
                <div class="cms-image-actions">
                    <button class="btn btn-sm btn-outline-primary" type="button"><i class="bi bi-images"></i> Choose</button>
                    <?php if (!empty($field['aspect'])): ?>
                    <span class="cms-image-aspect-badge"><?= \mini\h($field['aspect']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <div class="cms-editor-actions d-flex gap-2 mt-3 pt-3 border-top">
            <button class="btn btn-sm btn-primary cms-save-btn" onclick="CMS.saveGroup(this)"><i class="bi bi-check-lg"></i> Save</button>
            <button class="btn btn-sm btn-secondary cms-cancel-btn" onclick="CMS.cancelGroup(this)">Cancel</button>
        </div>
    </template>
    <?php endforeach; ?>

    <template id="tmpl-media-library">
        <div class="media-layout">
            <div class="media-folders">
                <button class="media-folder-item active" data-path="" onclick="CMS.media.navigateFolder('')">
                    <i class="bi bi-folder-fill"></i> uploads
                </button>
                <div id="media-folder-tree"></div>
                <div style="padding: 0.5rem 0.6rem; border-top: 1px solid #eee; margin-top: 0.5rem;">
                    <button class="btn btn-sm btn-outline-secondary w-100" onclick="CMS.media.createFolder()">
                        <i class="bi bi-folder-plus"></i> New Folder
                    </button>
                </div>
            </div>
            <div class="media-files" id="media-files-panel">
                <div class="media-toolbar">
                    <div class="media-breadcrumb" id="media-breadcrumb">
                        <a href="#" onclick="CMS.media.navigateFolder(''); return false;">uploads</a>
                    </div>
                    <label class="btn btn-sm btn-primary mb-0">
                        <i class="bi bi-upload"></i> Upload
                        <input type="file" multiple hidden onchange="CMS.media.uploadFiles(this.files)">
                    </label>
                </div>
                <div class="media-dropzone" id="media-dropzone">
                    <i class="bi bi-cloud-arrow-up" style="font-size:2rem;"></i><br>
                    Drop files here to upload
                </div>
                <div class="media-grid" id="media-grid"></div>
            </div>
        </div>
    </template>

    <template id="tmpl-media-preview">
        <div class="media-preview" id="media-preview-content"></div>
    </template>

    <template id="tmpl-crop-tool">
        <div class="crop-container">
            <img id="crop-image" src="">
        </div>
        <div class="mt-3 d-flex gap-2 justify-content-end">
            <button class="btn btn-sm btn-secondary" onclick="CMS.crop.cancel()">Cancel</button>
            <button class="btn btn-sm btn-primary" onclick="CMS.crop.apply()"><i class="bi bi-crop"></i> Apply Crop</button>
        </div>
    </template>

    <template id="tmpl-ai-assistant">
        <div class="ai-chat">
            <div class="ai-messages" id="ai-messages">
                <div class="text-center text-muted py-3">
                    <i class="bi bi-chat-dots" style="font-size:2rem;"></i>
                    <p class="mt-2 mb-0" style="font-size:0.85rem;">Ask Claude to edit views, create components, modify styles, or manage data.</p>
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
                <button id="ai-send" class="btn btn-sm btn-primary" onclick="CMS.ai.send()"><i class="bi bi-send"></i></button>
            </div>
            <div style="padding:0 0.75rem 0.4rem;text-align:right;">
                <a href="#" onclick="CMS.ai.newConversation(); return false;" style="font-size:0.75rem;color:#888;text-decoration:none;">New conversation</a>
            </div>
        </div>
    </template>

<?php if (!empty($replayData)): ?>
    <script>
    (function() {
        var form = document.createElement('form');
        form.method = <?= json_encode($replayMethod) ?>;
        form.action = <?= json_encode($replayAction) ?>;
        form.target = 'site-iframe';
        form.style.display = 'none';
        var fields = <?= json_encode($replayData) ?>;
        fields.forEach(function(f) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = f.name;
            input.value = f.value;
            form.appendChild(input);
        });
        document.body.appendChild(form);
        form.submit();
        form.remove();
    })();
    </script>
<?php endif; ?>
    <script src="/admin/dist/cms.min.js"></script>
</body>
</html>
