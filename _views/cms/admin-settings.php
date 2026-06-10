<!doctype html>
<html lang="en">
<head>
    <script>if (window !== window.top) window.top.location.href = window.location.href;</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CMS - Settings</title>
    <link rel="stylesheet" href="/admin/vendor/source-sans-3/index.css">
    <link rel="stylesheet" href="/admin/vendor/overlayscrollbars/overlayscrollbars.min.css">
    <link rel="stylesheet" href="/admin/vendor/bootstrap-icons/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/admin/vendor/adminlte/adminlte.min.css">
    <style>
        .cms-sidebar { background: #fefefe !important; color: #333; }
        .cms-sidebar .nav-link { color: #444; }
        .cms-sidebar .nav-link:hover { color: #111; background: rgba(0,0,0,.06); }
        .cms-sidebar .nav-link.active { color: #1a56db; background: rgba(26,86,219,.08); }
        .cms-sidebar .nav-header { color: #777; }
        .cms-sidebar .sidebar-brand { border-bottom: 1px solid rgba(0,0,0,.08); padding: 0; height: 5.5rem; display: flex; align-items: center; }
        .cms-sidebar .brand-link { display: block; padding: 0; }
        .cms-sidebar .brand-logo { width: 100%; height: auto; display: block; }
        .cms-sidebar .btn-outline-secondary { color: #555; border-color: #bbb; }
        .cms-sidebar .btn-outline-secondary:hover { background: rgba(0,0,0,.06); color: #333; }
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
                        <span class="nav-link fw-semibold"><i class="bi bi-gear"></i> Settings</span>
                    </li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link text-danger" href="/login?logout=1" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
                    </li>
                </ul>
            </div>
        </nav>

        <aside class="app-sidebar shadow cms-sidebar">
            <div class="sidebar-brand">
                <a href="/" class="brand-link">
                    <img src="/admin/logo.png" alt="Mini CMS" class="brand-logo">
                </a>
            </div>
            <div class="sidebar-wrapper" style="display: flex; flex-direction: column; height: 100%;">
                <nav class="mt-2" style="flex: 1; overflow-y: auto;">
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
                            <a href="/admin/ai/" class="nav-link">
                                <i class="nav-icon bi bi-chat-dots"></i>
                                <p>AI Assistant</p>
                            </a>
                        </li>
                    </ul>
                </nav>
                <div style="padding: 0.5rem; border-top: 1px solid rgba(0,0,0,0.1); margin-top: auto; display: flex; gap: 0.35rem;">
                    <a href="/admin/settings/" class="btn btn-sm btn-outline-secondary active" title="Settings"><i class="bi bi-gear"></i></a>
                    <a href="/login?logout=1" class="btn btn-sm btn-outline-secondary flex-grow-1"><i class="bi bi-box-arrow-right"></i> Logout</a>
                </div>
            </div>
        </aside>

        <main class="app-main">
            <div class="app-content-header">
                <h3><i class="bi bi-gear"></i> Settings</h3>
            </div>
            <div class="app-content">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Change Password</h5>
                                </div>
                                <div class="card-body">
                                    <form id="settings-password-form" onsubmit="CMS.settings.changePassword(event)">
                                        <div class="mb-3">
                                            <label class="form-label">Current password</label>
                                            <input type="password" name="current" class="form-control" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">New password</label>
                                            <input type="password" name="new" class="form-control" required minlength="8">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Confirm new password</label>
                                            <input type="password" name="confirm" class="form-control" required minlength="8">
                                        </div>
                                        <div id="settings-password-error" class="text-danger mb-2" style="display: none;"></div>
                                        <button type="submit" class="btn btn-primary">Update Password</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:9999;" id="cms-toasts"></div>
    <script src="/admin/dist/cms.min.js"></script>
</body>
</html>
