<div class="panel">
    <div class="panel-heading">
        <div class="title-bar">
            <h3 class="title-bar__title"><?= lang('form_builder_all_templates') ?></h3>
            <div class="title-bar__extra-tools">
                <a href="<?= $base_url ?>" class="btn">← <?= lang('form_builder_all_forms') ?></a>
            </div>
        </div>
    </div>
    <div class="panel-body">
        <p style="color:#666;margin-bottom:15px;">
            A template is a snapshot of a form's settings and fields. Start a new form from one, or open any
            form and choose <strong><?= lang('form_builder_save_as_template') ?></strong> to add your own.
        </p>
        <?php if (empty($templates)): ?>
            <p class="no-results"><?= lang('form_builder_no_templates') ?></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table--loose">
                    <thead>
                        <tr>
                            <th><?= lang('form_builder_name') ?></th>
                            <th>Description</th>
                            <th>Fields</th>
                            <th>Source</th>
                            <th class="text-right"><?= lang('form_builder_actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($templates as $tpl): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($tpl['name']) ?></strong></td>
                                <td><?= htmlspecialchars((string) $tpl['description']) ?></td>
                                <td><?= (int) $tpl['field_count'] ?></td>
                                <td><?= $tpl['is_builtin'] === 'y' ? 'Built-in' : 'Saved ' . htmlspecialchars(substr((string) $tpl['created_at'], 0, 10)) ?></td>
                                <td class="text-right">
                                    <div style="display:flex;justify-content:flex-end;gap:5px;">
                                        <a href="<?= ee('CP/URL', 'addons/settings/form_builder/new_from_template/' . (int) $tpl['template_id']) ?>" class="btn btn--small action">Use Template</a>
                                        <form method="post" action="<?= ee('CP/URL', 'addons/settings/form_builder/delete_template/' . (int) $tpl['template_id']) ?>" style="display:contents;" onsubmit="return confirm('<?= lang('form_builder_confirm_delete_template') ?>')">
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
