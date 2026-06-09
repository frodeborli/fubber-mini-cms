<!doctype html>
<html lang="en">
<head>
    <script>if (window !== window.top) window.top.location.href = window.location.href;</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CMS - <?= \mini\h($entity->getPluralTitle()) ?></title>
    <link rel="stylesheet" href="/admin/vendor/source-sans-3/index.css">
    <link rel="stylesheet" href="/admin/vendor/overlayscrollbars/overlayscrollbars.min.css">
    <link rel="stylesheet" href="/admin/vendor/bootstrap-icons/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/admin/vendor/adminlte/adminlte.min.css">
    <link rel="stylesheet" href="/admin/vendor/simple-datatables/simple-datatables.css">
    <style>
        .app-content-header { padding: 0.75rem 1.25rem; }
        .app-content-header h3 { margin: 0; font-size: 1.25rem; }
        .datatable-wrapper .datatable-top,
        .datatable-wrapper .datatable-bottom { padding: 0.75rem 1rem; }
        .datatable-wrapper .datatable-search input { font-size: 0.875rem; }
        .datatable-wrapper .datatable-container { border: none; }
        .datatable-wrapper .datatable-table { margin-bottom: 0; }
        tr[data-href] { cursor: pointer; }
        tr[data-href]:hover td { background: #e8f0fe; }

        .cms-picker-overlay { display: none; position: fixed; inset: 0; z-index: 1060; background: rgba(0,0,0,0.4); opacity: 0; transition: opacity 0.2s; }
        .cms-picker-overlay.open { display: flex !important; opacity: 1; justify-content: center; align-items: flex-start; padding-top: 5vh; }
        .cms-picker-panel { background: #fff; border-radius: 8px; width: 480px; max-width: 95vw; max-height: 80vh; display: flex; flex-direction: column; box-shadow: 0 8px 32px rgba(0,0,0,0.2); transform: translateY(-10px); transition: transform 0.2s; }
        .cms-picker-overlay.open .cms-picker-panel { transform: translateY(0); }
        .cms-picker-header { display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1rem; border-bottom: 1px solid #dee2e6; }
        .cms-picker-body { flex: 1; overflow-y: auto; }
        .cms-picker-body .list-group-item-action:hover { background: #e8f0fe; }
        .cms-picker-footer { border-top: 1px solid #dee2e6; }

        .cms-entity-field { display: flex; align-items: center; gap: 0.5rem; }
        .cms-entity-field .cms-entity-display { flex: 1; padding: 0.375rem 0.75rem; border: 1px solid #dee2e6; border-radius: 0.375rem; min-height: 38px; background: #f8f9fa; }
        .cms-entity-field .cms-entity-display:empty::after { content: 'None selected'; color: #6c757d; }
    </style>
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
    <div class="app-wrapper">
        <!-- Header -->
        <nav class="app-header navbar navbar-expand bg-body">
            <div class="container-fluid">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button"><i class="bi bi-list"></i></a>
                    </li>
                    <li class="nav-item">
                        <span class="nav-link text-muted"><?= \mini\h($entity->getPluralTitle()) ?></span>
                    </li>
                </ul>
                <ul class="navbar-nav ms-auto">
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
                        <li class="nav-header">PATHS</li>
                        <?php
                        $routeTree = \mini\Mini::$mini->get(\MiniCms\Content::class)->routeTree();
                        $renderTree = function(array $nodes) use (&$renderTree, $currentPath) {
                            foreach ($nodes as $node):
                                $hasChildren = !empty($node['children']);
                                $hasPath = isset($node['path']);
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
                            <a href="/admin/data/<?= \mini\h($modelSlug) ?>/" class="nav-link<?= $modelSlug === $slug ? ' active' : '' ?>">
                                <i class="nav-icon bi <?= \mini\h($modelEntity->getIcon()) ?>"></i>
                                <p><?= \mini\h($modelEntity->getPluralTitle()) ?></p>
                            </a>
                        </li>
                        <?php endforeach; ?>
                        <?php endif; ?>
                        <li class="nav-header">TOOLS</li>
                        <li class="nav-item">
                            <a href="/admin/ai/" class="nav-link">
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
            <?= \mini\render($contentView, get_defined_vars()) ?>
        </main>
    </div>

    <script src="/admin/dist/cms.min.js"></script>
</body>
</html>
