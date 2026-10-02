<style>
.submission-view-tools { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
</style>
<div class="panel">
    <div class="panel-heading">
        <div class="title-bar">
            <h3 class="title-bar__title"><?= lang('form_builder_view_submission') ?></h3>
            <div class="title-bar__extra-tools submission-view-tools">
                <a href="<?= ee('CP/URL', 'addons/settings/form_builder/submissions/' . $submission['form_id']) ?>" class="btn"><?= lang('form_builder_back') ?></a>
                <form method="post" action="<?= ee('CP/URL', 'addons/settings/form_builder/delete_submission/' . $submission['submission_id']) ?>" style="display:contents;" onsubmit="return confirm('<?= lang('form_builder_confirm_delete_submission') ?>')">
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
                                            <a href="<?= rtrim(ee()->config->item('base_url'), '/') ?>/uploads/form_builder/<?= htmlspecialchars((string)$value) ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars((string)$value) ?></a>
                                        <?php elseif ($field['field_type'] === 'url' && !empty($value)): ?>
                                            <?php
                                                $url_value = (string)$value;
                                                $url_scheme = strtolower((string) parse_url($url_value, PHP_URL_SCHEME));
                                                $is_safe_url = in_array($url_scheme, array('http', 'https'), true);
                                            ?>
                                            <?php if ($is_safe_url): ?>
                                                <a href="<?= htmlspecialchars($url_value, ENT_QUOTES) ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars($url_value, ENT_QUOTES) ?></a>
                                            <?php else: ?>
                                                <?= htmlspecialchars($url_value, ENT_QUOTES) ?>
                                            <?php endif; ?>
                                        <?php elseif ($field['field_type'] === 'email' && !empty($value)): ?>
                                            <a href="mailto:<?= htmlspecialchars((string)$value) ?>"><?= htmlspecialchars((string)$value) ?></a>
                                        <?php elseif (Form_builder::isCompositeType($field['field_type'])): ?>
                                            <?php
                                            $comp_schema = Form_builder::compositeSchema($field['field_type']);
                                            $comp_rows   = Form_builder::decodeCompositeValue($value);
                                            ?>
                                            <?php if (empty($comp_rows)): ?>
                                                <em style="color:#888;">(none)</em>
                                            <?php else: ?>
                                                <table class="table--loose" style="margin:0;">
                                                    <thead><tr>
                                                        <?php foreach ($comp_schema['columns'] as $comp_col => $comp_def): ?>
                                                            <th><?= htmlspecialchars($comp_def['label']) ?></th>
                                                        <?php endforeach; ?>
                                                    </tr></thead>
                                                    <tbody>
                                                    <?php foreach ($comp_rows as $comp_row): ?>
                                                        <tr>
                                                        <?php foreach ($comp_schema['columns'] as $comp_col => $comp_def): ?>
                                                            <?php $cv = isset($comp_row[$comp_col]) ? (string) $comp_row[$comp_col] : ''; ?>
                                                            <td style="white-space:pre-wrap;"><?= $comp_def['type'] === 'checkbox' ? ($cv === 'y' ? 'Yes' : '') : htmlspecialchars($cv) ?></td>
                                                        <?php endforeach; ?>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            <?php endif; ?>
                                        <?php elseif ($field['field_type'] === 'textarea'): ?>
                                            <pre style="white-space: pre-wrap; margin: 0;"><?= htmlspecialchars((string)$value) ?></pre>
                                        <?php elseif ($field['field_type'] === 'mailchimp_subscription'): ?>
                                            <?php
                                            $mc_status  = $submission['mailchimp_status'] ?? '';
                                            $mc_failed  = in_array($mc_status, Form_builder::MAILCHIMP_FAILURE_STATUSES, true);
                                            $mc_success = in_array($mc_status, Form_builder::MAILCHIMP_SUCCESS_STATUSES, true);
                                            ?>
                                            <?php if ($value === 'y' && $mc_failed): ?>
                                                Yes &mdash; subscription failed, add user to Mailchimp manually
                                            <?php elseif ($value === 'y' && $mc_success): ?>
                                                Yes &mdash; successfully <?= htmlspecialchars($mc_status, ENT_QUOTES) ?>
                                            <?php elseif ($value === 'y'): ?>
                                                Yes
                                            <?php else: ?>
                                                No
                                            <?php endif; ?>
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
                            <?php if (!empty($submission['mailchimp_status']) && $submission['mailchimp_status'] !== 'none'): ?>
                            <tr>
                                <th><?= lang('form_builder_mailchimp_status') ?></th>
                                <td>
                                    <?php
                                    $mc_status = $submission['mailchimp_status'];
                                    $is_success = in_array($mc_status, Form_builder::MAILCHIMP_SUCCESS_STATUSES, true);
                                    $is_neutral = ($mc_status === 'skipped_unchecked');
                                    $status_class = $is_success ? 'yes' : ($is_neutral ? '' : 'no');
                                    $lang_key = 'form_builder_mailchimp_status_' . $mc_status;
                                    ?>
                                    <span class="<?= $status_class ?>"><?= lang($lang_key) ?: htmlspecialchars($mc_status, ENT_QUOTES) ?></span>
                                </td>
                            </tr>
                            <?php if (!empty($submission['mailchimp_error'])): ?>
                            <tr>
                                <th><?= lang('form_builder_mailchimp_error') ?></th>
                                <td><?= htmlspecialchars($submission['mailchimp_error'], ENT_QUOTES) ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if (($submission['mailchimp_status'] ?? '') === 'failed_permanently_deleted'): ?>
                            <tr>
                                <th><?= lang('form_builder_mailchimp_why') ?></th>
                                <td>
                                    This contact was permanently deleted from Mailchimp and cannot be re-imported via the API.
                                    The contact must re-subscribe through a Mailchimp signup form, or be manually added through
                                    the Mailchimp dashboard (<strong>Audience &rarr; Add a contact</strong>).
                                </td>
                            </tr>
                            <?php endif; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
