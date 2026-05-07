<style>
.title-bar__extra-tools .btn + .btn { margin-left: 8px; }
.form-builder-row-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 5px; }
.form-builder-row-actions .btn { margin: 0; }
</style>
<div class="panel">
    <div class="panel-heading">
        <div class="title-bar">
            <h3 class="title-bar__title"><?= lang('form_builder_fields') ?>: <?= htmlspecialchars($form['form_label']) ?></h3>
            <div class="title-bar__extra-tools">
                <a href="<?= ee('CP/URL', 'addons/settings/form_builder/edit_form/' . $form['form_id']) ?>" class="btn"><?= lang('form_builder_edit_form') ?></a>
                <a href="<?= ee('CP/URL', 'addons/settings/form_builder/edit_field/' . $form['form_id']) ?>" class="btn action"><?= lang('form_builder_add_field') ?></a>
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
                                <td><?= $field_types[$field['field_type']] ?? $field['field_type'] ?></td>
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
