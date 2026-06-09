<?php foreach ($fields as $name => $field): ?>
<div class="mb-3">
    <label for="field-<?= \mini\h($name) ?>" class="form-label"><?= \mini\h($field['label']) ?></label>
    <?php
    $value = $item->$name ?? '';
    $hasError = !empty($errors[$name]);
    $errorClass = $hasError ? ' is-invalid' : '';
    ?>
    <?php if ($field['type'] === 'entity'): ?>
    <?php
    $entitySlug = null;
    if (!empty($field['entityClass'])) {
        $entitySlug = \mini\Mini::$mini->get(\MiniCms\Content::class)->findSlugByModelClass($field['entityClass']);
    }
    ?>
    <div class="cms-entity-field">
        <input type="hidden" id="field-<?= \mini\h($name) ?>" name="<?= \mini\h($name) ?>" value="<?= \mini\h($value) ?>">
        <div class="cms-entity-display" id="field-<?= \mini\h($name) ?>-display"><?php
            if ($value && $entitySlug) {
                $content = \mini\Mini::$mini->get(\MiniCms\Content::class);
                $models = $content->models();
                if (isset($models[$entitySlug])) {
                    $related = $models[$entitySlug]->find($value);
                    if ($related) echo $models[$entitySlug]->renderInline($related);
                }
            }
        ?></div>
        <?php if ($entitySlug): ?>
        <button type="button" class="btn btn-sm btn-outline-primary" onclick="(function(){ CMS.data.pick('<?= \mini\h($entitySlug) ?>').then(function(r){ if(!r)return; document.getElementById('field-<?= \mini\h($name) ?>').value=r.id; document.getElementById('field-<?= \mini\h($name) ?>-display').textContent=r.display; }); })()">
            <i class="bi bi-search"></i> Pick
        </button>
        <?php if ($field['nullable']): ?>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('field-<?= \mini\h($name) ?>').value=''; document.getElementById('field-<?= \mini\h($name) ?>-display').innerHTML=''">
            <i class="bi bi-x-lg"></i>
        </button>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php elseif ($field['type'] === 'textarea'): ?>
    <textarea id="field-<?= \mini\h($name) ?>" name="<?= \mini\h($name) ?>" class="form-control<?= $errorClass ?>" rows="4"><?= \mini\h($value) ?></textarea>
    <?php elseif ($field['type'] === 'checkbox'): ?>
    <div class="form-check">
        <input type="hidden" name="<?= \mini\h($name) ?>" value="0">
        <input type="checkbox" id="field-<?= \mini\h($name) ?>" name="<?= \mini\h($name) ?>" value="1" class="form-check-input<?= $errorClass ?>"<?= $value ? ' checked' : '' ?>>
    </div>
    <?php elseif ($field['type'] === 'datetime'): ?>
    <input type="datetime-local" id="field-<?= \mini\h($name) ?>" name="<?= \mini\h($name) ?>" class="form-control<?= $errorClass ?>" value="<?= \mini\h($value instanceof \DateTimeInterface ? $value->format('Y-m-d\TH:i') : $value) ?>">
    <?php elseif ($field['type'] === 'number'): ?>
    <input type="number" id="field-<?= \mini\h($name) ?>" name="<?= \mini\h($name) ?>" class="form-control<?= $errorClass ?>" value="<?= \mini\h($value) ?>" step="any">
    <?php else: ?>
    <input type="<?= \mini\h($field['type']) ?>" id="field-<?= \mini\h($name) ?>" name="<?= \mini\h($name) ?>" class="form-control<?= $errorClass ?>" value="<?= \mini\h($value) ?>">
    <?php endif; ?>
    <?php if ($hasError): ?>
    <div class="invalid-feedback"><?= \mini\h($errors[$name]) ?></div>
    <?php endif; ?>
</div>
<?php endforeach; ?>
