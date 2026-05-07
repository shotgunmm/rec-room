<?php
/**
 * Calendar - Events Index
 *
 * @var array  $table          ee('CP/Table')->viewData()
 * @var string $pagination     Rendered pagination HTML
 * @var int    $per_page       Current per-page value
 * @var int    $total          Total event count
 * @var object $base_url       CP/URL object
 * @var string $type_filter    Current type filter (all|single|recurring)
 * @var string $status_filter  Current status filter (all|open|closed)
 * @var int    $cat_filter     Current category filter (0 = all)
 * @var array  $all_categories Available categories [{cat_id, cat_name}]
 */
?>
<div class="tbl-ctrls">
    <div id="event-filters" data-base-url="<?= htmlspecialchars($base_url->compile()) ?>">
        <label for="per_page"><?= lang('per_page') ?></label>
        <select name="per_page" id="per_page" onchange="applyEventFilters()">
            <?php foreach ([10, 25, 50, 100] as $option): ?>
                <option value="<?= $option ?>"<?= (int) $per_page === $option ? ' selected' : '' ?>>
                    <?= $option ?>
                </option>
            <?php endforeach ?>
        </select>

        <label for="type"><?= lang('filter_type') ?></label>
        <select name="type" id="type" onchange="applyEventFilters()">
            <option value="all"<?= $type_filter === 'all' ? ' selected' : '' ?>><?= lang('filter_type_all') ?></option>
            <option value="single"<?= $type_filter === 'single' ? ' selected' : '' ?>><?= lang('single_event') ?></option>
            <option value="recurring"<?= $type_filter === 'recurring' ? ' selected' : '' ?>><?= lang('recurring_event') ?></option>
        </select>

        <label for="status_filter"><?= lang('filter_status') ?></label>
        <select name="status_filter" id="status_filter" onchange="applyEventFilters()">
            <option value="all"<?= $status_filter === 'all' ? ' selected' : '' ?>><?= lang('filter_status_all') ?></option>
            <option value="open"<?= $status_filter === 'open' ? ' selected' : '' ?>><?= lang('open') ?></option>
            <option value="closed"<?= $status_filter === 'closed' ? ' selected' : '' ?>><?= lang('closed') ?></option>
        </select>

        <label for="cat_id"><?= lang('filter_category') ?></label>
        <select name="cat_id" id="cat_id" onchange="applyEventFilters()">
            <option value="0"<?= $cat_filter === 0 ? ' selected' : '' ?>><?= lang('filter_category_all') ?></option>
            <?php foreach ($all_categories as $cat): ?>
                <option value="<?= (int) $cat['cat_id'] ?>"<?= $cat_filter === (int) $cat['cat_id'] ? ' selected' : '' ?>>
                    <?= htmlspecialchars($cat['cat_name']) ?>
                </option>
            <?php endforeach ?>
        </select>
    </div>
    <script>
    function applyEventFilters() {
        var base    = document.getElementById('event-filters').dataset.baseUrl;
        var perPage = document.getElementById('per_page').value;
        var type    = document.getElementById('type').value;
        var status  = document.getElementById('status_filter').value;
        var cat     = document.getElementById('cat_id').value;
        window.location.href = base
            + '&per_page='      + encodeURIComponent(perPage)
            + '&type='          + encodeURIComponent(type)
            + '&status_filter=' + encodeURIComponent(status)
            + '&cat_id='        + encodeURIComponent(cat);
    }
    </script>
    <p><?= sprintf(lang('showing_events'), (int) $total) ?></p>
</div>

<?php $this->embed('ee:_shared/table', $table) ?>

<?= $pagination ?>
