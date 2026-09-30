<style>
.title-bar__extra-tools { align-items: center; }
.title-bar__extra-tools .add-field-picker { margin-left: 10px; }
.form-builder-row-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 5px; }
.form-builder-row-actions .btn { margin: 0; }
.add-field-picker { position: relative; display: inline-block; }
.add-field-picker details { display: inline-block; margin: 0; padding: 0; }
.add-field-picker details > summary { list-style: none; cursor: pointer; }
.add-field-picker details > summary::-webkit-details-marker { display: none; }
.add-field-picker details > summary::before { content: none; }
.add-field-picker details > summary::after { font-family: "Font Awesome 6 Pro"; font-weight: 600; content: '\f054'; font-size: 10px; display: inline-block; margin-left: 5px; position: relative; top: -1px; transition: transform 0.15s ease; }
.add-field-picker details[open] > summary::after { transform: rotate(90deg); }
.add-field-menu {
    position: absolute;
    top: 100%;
    right: 0;
    margin-top: 4px;
    min-width: 220px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    padding: 6px 0;
    z-index: 1000;
}
.add-field-menu__header {
    font-size: 0.7em;
    text-transform: uppercase;
    color: #888;
    padding: 8px 14px 4px;
    letter-spacing: 0.05em;
    font-weight: 600;
}
.add-field-menu__item {
    display: block;
    padding: 6px 14px;
    color: #333;
    text-decoration: none;
}
.add-field-menu__item:hover {
    background: #f0f4ff;
    text-decoration: none;
}
</style>
<div class="panel">
    <div class="panel-heading">
        <div class="title-bar">
            <h3 class="title-bar__title"><?= lang('form_builder_fields') ?>: <?= htmlspecialchars($form['form_label']) ?></h3>
            <div class="title-bar__extra-tools">
                <a href="<?= ee('CP/URL', 'addons/settings/form_builder/edit_form/' . $form['form_id']) ?>" class="btn"><?= lang('form_builder_edit_form') ?></a>
                <div class="add-field-picker">
                    <details>
                        <summary class="btn action"><?= lang('form_builder_add_field') ?></summary>
                        <div class="add-field-menu">
                            <?php foreach ($field_type_groups as $group_key => $group): ?>
                                <div class="add-field-menu__header"><?= htmlspecialchars(lang($group['label_key']), ENT_QUOTES) ?></div>
                                <?php foreach ($group['types'] as $type_key): ?>
                                    <a class="add-field-menu__item"
                                       href="<?= ee('CP/URL', 'addons/settings/form_builder/edit_field/' . $form['form_id'] . '/0/' . $type_key)->compile() ?>">
                                        <?= htmlspecialchars($field_types[$type_key] ?? $type_key, ENT_QUOTES) ?>
                                    </a>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </div>
                    </details>
                </div>
            </div>
        </div>
    </div>
    <div class="panel-body">
        <?php if (empty($fields)): ?>
            <p class="no-results"><?= lang('form_builder_no_fields') ?></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table--loose" id="fields-table">
                    <thead>
                        <tr>
                            <th style="width: 40px;"><?= lang('form_builder_order') ?></th>
                            <th><?= lang('form_builder_label') ?></th>
                            <th><?= lang('form_builder_name') ?></th>
                            <th><?= lang('form_builder_type') ?></th>
                            <th><?= lang('form_builder_is_required') ?></th>
                            <th class="text-right"><?= lang('form_builder_actions') ?></th>
                        </tr>
                    </thead>
                    <tbody id="sortable-fields">
                        <?php foreach ($fields as $field): ?>
                            <tr data-field-id="<?= $field['field_id'] ?>">
                                <td class="drag-handle" style="cursor: move;">&#9776;</td>
                                <td><?= htmlspecialchars($field['field_label']) ?></td>
                                <td><code><?= htmlspecialchars($field['field_name']) ?></code></td>
                                <td>
                                    <?= $field_types[$field['field_type']] ?? $field['field_type'] ?>
                                    <?php if (!empty($field['is_misconfigured'])): ?>
                                        <span class="st-pending" title="<?= lang('form_builder_mailchimp_field_misconfigured') ?>" style="margin-left:5px;cursor:help;">&#9888; Config needed</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($field['is_required'] === 'y'): ?>
                                        <span class="yes"><?= lang('yes') ?></span>
                                    <?php else: ?>
                                        <span class="no"><?= lang('no') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right">
                                    <div class="form-builder-row-actions">
                                        <a href="<?= ee('CP/URL', 'addons/settings/form_builder/edit_field/' . $form['form_id'] . '/' . $field['field_id']) ?>" class="btn btn--small"><?= lang('form_builder_edit') ?></a>
                                        <form method="post" action="<?= ee('CP/URL', 'addons/settings/form_builder/delete_field/' . $form['form_id'] . '/' . $field['field_id']) ?>" style="display:contents;" onsubmit="return confirm('<?= lang('form_builder_confirm_delete_field') ?>')">
                                            <input type="hidden" name="csrf_token" value="<?= CSRF_TOKEN ?>">
                                            <button type="submit" class="btn btn--small btn--danger"><?= lang('form_builder_delete') ?></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tbody = document.getElementById('sortable-fields');
    if (!tbody) return;

    let draggedRow = null;

    tbody.querySelectorAll('tr').forEach(row => {
        row.draggable = true;

        row.addEventListener('dragstart', function(e) {
            draggedRow = this;
            this.style.opacity = '0.5';
        });

        row.addEventListener('dragend', function(e) {
            this.style.opacity = '1';
            draggedRow = null;
            saveOrder();
        });

        row.addEventListener('dragover', function(e) {
            e.preventDefault();
            const rect = this.getBoundingClientRect();
            const midY = rect.top + rect.height / 2;
            if (e.clientY < midY) {
                this.parentNode.insertBefore(draggedRow, this);
            } else {
                this.parentNode.insertBefore(draggedRow, this.nextSibling);
            }
        });
    });

    function saveOrder() {
        const fields = [];
        tbody.querySelectorAll('tr').forEach(row => {
            fields.push(row.dataset.fieldId);
        });

        fetch('<?= ee('CP/URL', 'addons/settings/form_builder/reorder_fields') ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'fields[]=' + fields.join('&fields[]=') + '&csrf_token=<?= CSRF_TOKEN ?>'
        });
    }
});
</script>
