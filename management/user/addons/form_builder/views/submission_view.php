<div class="panel">
    <div class="panel-heading">
        <div class="title-bar">
            <h3 class="title-bar__title"><?= lang('form_builder_view_submission') ?></h3>
            <div class="title-bar__extra-tools">
                <a href="<?= ee('CP/URL', 'addons/settings/form_builder/submissions/' . $submission['form_id']) ?>" class="btn"><?= lang('form_builder_back') ?></a>
                <form method="post" action="<?= ee('CP/URL', 'addons/settings/form_builder/delete_submission/' . $submission['submission_id']) ?>" style="display:inline;" onsubmit="return confirm('<?= lang('form_builder_confirm_delete_submission') ?>')">
                    <input type="hidden" name="csrf_token" value="<?= CSRF_TOKEN ?>">
                    <button type="submit" class="btn btn--danger"><?= lang('form_builder_delete') ?></button>
                </form>
            </div>
        </div>
    </div>
    <div class="panel-body">
        <div style="display: flex; flex-direction: column; gap: 20px;">
        <style>
            .submission-panel { min-width: 0; overflow-x: auto; }
        </style>
            <!-- Submission Data -->
            <div class="panel submission-panel">
                <div class="panel-heading">
                    <h4><?= lang('form_builder_submission_data') ?></h4>
                </div>
                <div class="panel-body">
                    <table class="table--loose">
                        <tbody>
                            <?php foreach ($fields as $field): ?>
                                <?php
                                $field_name = $field['field_name'];
                                $value = isset($submission['submission_data'][$field_name])
                                    ? $submission['submission_data'][$field_name]['value']
                                    : '';
                                ?>
                                <tr>
                                    <th style="width: 30%;"><?= nl2br(htmlspecialchars($field['field_label'])) ?></th>
                                    <td>
                                        <?php if ($field['field_type'] === 'file' && !empty($value)): ?>
                                            <a href="<?= rtrim(ee()->config->item('base_url'), '/') ?>/uploads/form_builder/<?= htmlspecialchars((string)$value) ?>" target="_blank"><?= htmlspecialchars((string)$value) ?></a>
                                        <?php elseif ($field['field_type'] === 'url' && !empty($value)): ?>
                                            <a href="<?= htmlspecialchars((string)$value) ?>" target="_blank"><?= htmlspecialchars((string)$value) ?></a>
                                        <?php elseif ($field['field_type'] === 'email' && !empty($value)): ?>
                                            <a href="mailto:<?= htmlspecialchars((string)$value) ?>"><?= htmlspecialchars((string)$value) ?></a>
                                        <?php elseif ($field['field_type'] === 'textarea'): ?>
                                            <pre style="white-space: pre-wrap; margin: 0;"><?= htmlspecialchars((string)$value) ?></pre>
                                        <?php else: ?>
                                            <?= htmlspecialchars((string)$value) ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Submission Info -->
            <div class="panel submission-panel">
                <div class="panel-heading">
                    <h4><?= lang('form_builder_submission_info') ?></h4>
                </div>
                <div class="panel-body">
                    <table class="table--loose">
                        <tbody>
                            <tr>
                                <th><?= lang('form_builder_form') ?></th>
                                <td><?= htmlspecialchars($form['form_label']) ?></td>
                            </tr>
                            <tr>
                                <th><?= lang('form_builder_submitted_at') ?></th>
                                <td><?= htmlspecialchars($submission['submitted_at']) ?></td>
                            </tr>
                            <tr>
                                <th><?= lang('form_builder_ip_address') ?></th>
                                <td><?= htmlspecialchars($submission['ip_address']) ?></td>
                            </tr>
                            <tr>
                                <th><?= lang('form_builder_status') ?></th>
                                <td>
                                    <?php if ($submission['status'] === 'new'): ?>
                                        <span class="st-pending"><?= lang('form_builder_status_new') ?></span>
                                    <?php elseif ($submission['status'] === 'read'): ?>
                                        <span class="st-open"><?= lang('form_builder_status_read') ?></span>
                                    <?php else: ?>
                                        <span><?= ucfirst($submission['status']) ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th><?= lang('form_builder_email_sent') ?></th>
                                <td>
                                    <?php if ($submission['email_sent'] === 'y'): ?>
                                        <span class="yes"><?= lang('yes') ?></span>
                                    <?php else: ?>
                                        <span class="no"><?= lang('no') ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th><?= lang('form_builder_confirmation_sent') ?></th>
                                <td>
                                    <?php if ($submission['confirmation_sent'] === 'y'): ?>
                                        <span class="yes"><?= lang('yes') ?></span>
                                    <?php else: ?>
                                        <span class="no"><?= lang('no') ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
