<?php $pk = $entity->getPrimaryKeyName(); ?>
<div class="app-content-header">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center">
            <h3><?= \mini\h($entity->getTitle()) ?> #<?= \mini\h($item->$pk) ?></h3>
            <div class="d-flex gap-2">
                <a href="/admin/data/<?= \mini\h($slug) ?>/<?= \mini\h($item->$pk) ?>/edit" class="btn btn-primary btn-sm">
                    <i class="bi bi-pencil"></i> Edit
                </a>
                <a href="/admin/data/<?= \mini\h($slug) ?>/" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>
</div>
<div class="app-content">
    <div class="container-fluid">
        <div class="card">
            <div class="card-body">
                <dl class="row mb-0">
                    <?php foreach ($fields as $name => $field): ?>
                    <dt class="col-sm-3"><?= \mini\h($field['label']) ?></dt>
                    <dd class="col-sm-9">
                        <?php
                        $value = $item->$name ?? '';
                        if ($field['type'] === 'entity' && $value && !empty($field['entityClass'])) {
                            $relSlug = \mini\Mini::$mini->get(\MiniCms\Content::class)->findSlugByModelClass($field['entityClass']);
                            if ($relSlug) {
                                $models = \mini\Mini::$mini->get(\MiniCms\Content::class)->models();
                                $related = isset($models[$relSlug]) ? $models[$relSlug]->find($value) : null;
                                if ($related) {
                                    echo '<a href="/admin/data/' . \mini\h($relSlug) . '/' . \mini\h($value) . '">';
                                    echo $models[$relSlug]->renderInline($related);
                                    echo '</a>';
                                } else {
                                    echo '<span class="text-muted">#' . \mini\h($value) . ' (deleted)</span>';
                                }
                            } else {
                                echo \mini\h($value);
                            }
                        } elseif ($value instanceof \DateTimeInterface) {
                            echo \mini\h(\mini\Fmt::dateShort($value));
                        } elseif (is_bool($value)) {
                            echo $value ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>';
                        } elseif ($value === '' || $value === null) {
                            echo '<span class="text-muted">—</span>';
                        } else {
                            echo \mini\h($value);
                        }
                        ?>
                    </dd>
                    <?php endforeach; ?>
                </dl>
            </div>
        </div>
    </div>
</div>
