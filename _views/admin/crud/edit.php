<?php $pk = $entity->getPrimaryKeyName(); ?>
<div class="app-content-header">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center">
            <h3>Edit <?= \mini\h($entity->getTitle()) ?></h3>
            <a href="/admin/data/<?= \mini\h($slug) ?>/" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>
    </div>
</div>
<div class="app-content">
    <div class="container-fluid">
        <div class="card">
            <form method="post" action="/admin/data/<?= \mini\h($slug) ?>/<?= \mini\h($item->$pk) ?>">
                <div class="card-body">
                    <?= \mini\render('admin/crud/_form.php', ['fields' => $fields, 'item' => $item, 'errors' => $errors ?? null]) ?>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save</button>
                    <a href="/admin/data/<?= \mini\h($slug) ?>/" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
