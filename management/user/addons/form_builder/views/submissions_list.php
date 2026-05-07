<div class="panel">
    <div class="panel-heading">
        <div class="title-bar">
            <h3 class="title-bar__title"><?= lang('form_builder_submissions') ?></h3>
            <div class="title-bar__extra-tools">
                <select onchange="window.location.href = '<?= ee('CP/URL', 'addons/settings/form_builder/submissions')->compile() ?>&filter_data=' + this.value">
                        <?php foreach ($form_options as $id => $label): ?>
                            <option value="<?= $id ?>" <?= ($current_form == $id) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <?php if ($current_form): ?>
                    <a href="<?= ee('CP/URL', 'addons/settings/form_builder/download_csv/' . (int)$current_form)->compile() ?>" class="btn btn--secondary btn--small" style="margin-left: 10px;"><?= lang('form_builder_download_csv') ?></a>
                    <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="panel-body">
        <?php if (empty($submissions)): ?>
            <p class="no-results"><?= lang('form_builder_no_submissions') ?></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table--loose">
                    <thead>
                        <tr>
                            <?php if (!empty($form_fields)): ?>
                                <th><a class="sort-header" href="<?= $sort_urls['submitted_at'] ?>"><?= lang('form_builder_date') ?><?= $sort_arrows['submitted_at'] ?></a></th>
                                <?php foreach ($form_fields as $field_key => $field_label): ?>
                                    <th><a class="sort-header" href="<?= $sort_urls[$field_key] ?? '' ?>"><?= htmlspecialchars($field_label) ?><?= $sort_arrows[$field_key] ?? '' ?></a></th>
                                <?php endforeach; ?>
                                <th><a class="sort-header" href="<?= $sort_urls['status'] ?>"><?= lang('form_builder_status') ?><?= $sort_arrows['status'] ?></a></th>
                                <th><a class="sort-header" href="<?= $sort_urls['email_sent'] ?>"><?= lang('form_builder_email_sent') ?><?= $sort_arrows['email_sent'] ?></a></th>
                                <th class="text-right"><?= lang('form_builder_actions') ?></th>
                            <?php else: ?>
                                <th><a class="sort-header" href="<?= $sort_urls['form_id'] ?>"><?= lang('form_builder_form') ?><?= $sort_arrows['form_id'] ?></a></th>
                                <th><a class="sort-header" href="<?= $sort_urls['submitted_at'] ?>"><?= lang('form_builder_date') ?><?= $sort_arrows['submitted_at'] ?></a></th>
                                <th><a class="sort-header" href="<?= $sort_urls['status'] ?>"><?= lang('form_builder_status') ?><?= $sort_arrows['status'] ?></a></th>
                                <th><a class="sort-header" href="<?= $sort_urls['email_sent'] ?>"><?= lang('form_builder_email_sent') ?><?= $sort_arrows['email_sent'] ?></a></th>
                                <th class="text-right"><?= lang('form_builder_actions') ?></th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($submissions as $sub): ?>
                            <tr class="<?= ($sub['status'] === 'new') ? 'unread' : '' ?>">
                                <?php if (!empty($form_fields)): ?>
                                    <td><?= htmlspecialchars($sub['submitted_at']) ?></td>
                                    <?php foreach ($form_fields as $field_key => $field_label): ?>
                                        <td class="sub-cell"><span class="sub-cell__text"><?= htmlspecialchars($sub['submission_data'][$field_key]['value'] ?? '') ?></span></td>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <td class="sub-cell"><span class="sub-cell__text"><?= htmlspecialchars($sub['form_label']) ?></span></td>
                                    <td><?= htmlspecialchars($sub['submitted_at']) ?></td>
                                <?php endif; ?>
                                <td>
                                    <?php if ($sub['status'] === 'new'): ?>
                                        <span class="st-pending" style="font-weight: bold;"><?= lang('form_builder_status_new') ?></span>
                                    <?php elseif ($sub['status'] === 'read'): ?>
                                        <span class="st-open"><?= lang('form_builder_status_read') ?></span>
                                    <?php else: ?>
                                        <span><?= ucfirst($sub['status']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($sub['email_sent'] === 'y'): ?>
                                        <span class="yes"><?= lang('yes') ?></span>
                                    <?php else: ?>
                                        <span class="no"><?= lang('no') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right">
                                    <div class="form-builder-row-actions">
                                        <a href="<?= ee('CP/URL', 'addons/settings/form_builder/view_submission/' . $sub['submission_id']) ?>"
                                            class="btn btn--small"><?= lang('form_builder_view') ?></a>
                                        <form method="post" action="<?= ee('CP/URL', 'addons/settings/form_builder/delete_submission/' . $sub['submission_id']) ?>" style="display:contents;" onsubmit="return confirm('<?= lang('form_builder_confirm_delete_submission') ?>')">
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

            <?php if (isset($pagination) && $pagination['total_pages'] > 1): ?>
                <div class="pagination-container" style="margin-top: 20px; text-align: center;">
                    <div class="pagination fb-pagination">
                        <?php
                        $current_page = $pagination['current_page'];
                        $total_pages = $pagination['total_pages'];
                        $pagination_url = ee('CP/URL', 'addons/settings/form_builder/submissions');
                        $pagination_params = [];
                        if ($current_form) $pagination_params['filter_data'] = $current_form;
                        if ($sort_col !== 'submitted_at') $pagination_params['sort_col'] = $sort_col;
                        if ($sort_dir !== 'desc') $pagination_params['sort_dir'] = $sort_dir;
                        if ($pagination_params) $pagination_url->addQueryStringVariables($pagination_params);
                        $base_url = $pagination_url->compile() . '&page=';
                        ?>

                        <?php if ($current_page > 1): ?>
                            <a href="<?= $base_url . ($current_page - 1) ?>" class="btn btn--small">&laquo; Previous</a>
                        <?php endif; ?>

                        <span style="margin: 0 12px; white-space: nowrap; display: inline-flex; align-items: center; gap: 4px;">
                            Page
                            <input
                                type="number"
                                id="pagination-goto"
                                value="<?= $current_page ?>"
                                min="1"
                                max="<?= $total_pages ?>"
                                style="width: 4em; text-align: center; padding: 2px 4px; margin: 0 4px; -moz-appearance: textfield;"
                                onwheel="this.blur()"
                            >
                            of <?= $total_pages ?>
                            <span style="margin-left: 8px; color: #888;">(<?= number_format($pagination['total']) ?> total)</span>
                        </span>

                        <?php if ($current_page < $total_pages): ?>
                            <a href="<?= $base_url . ($current_page + 1) ?>" class="btn btn--small">Next &raquo;</a>
                        <?php endif; ?>

                        <script>
                        (function () {
                            var input = document.getElementById('pagination-goto');
                            var baseUrl = <?= json_encode($base_url) ?>;
                            var total = <?= (int)$total_pages ?>;
                            input.addEventListener('change', function () {
                                var page = parseInt(this.value, 10);
                                if (isNaN(page) || page < 1) page = 1;
                                if (page > total) page = total;
                                window.location.href = baseUrl + page;
                            });
                        })();
                        </script>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<style>
    .form-builder-row-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 5px; }
    .form-builder-row-actions .btn { margin: 0; }
    #pagination-goto::-webkit-inner-spin-button,
    #pagination-goto::-webkit-outer-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
    tr.unread td {
        background-color: #fffde7;
    }
    .pagination-container {
        padding: 15px;
        border-top: 1px solid #e0e0e0;
    }
    a.sort-header {
        color: inherit;
        text-decoration: none;
        white-space: nowrap;
    }
    a.sort-header:hover {
        text-decoration: underline;
    }
    .fb-pagination {
        display: flex;
        align-items: center;
        justify-content: center;
        flex-wrap: nowrap;
        gap: 4px;
    }
    @media (max-width: 767px) {
        .fb-pagination {
            flex-direction: column;
            gap: 10px;
        }
    }
    tbody tr {
        height: 1px; /* allows td to use height: 100% for stretching */
    }
    td.sub-cell {
        min-width: 150px;
        height: 100%;
        padding-top: 0;
        padding-bottom: 0;
    }
    .sub-cell__text {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        text-overflow: ellipsis;
        max-height: 2.8em;
        line-height: 1.4;
        word-break: break-word;
    }
</style>