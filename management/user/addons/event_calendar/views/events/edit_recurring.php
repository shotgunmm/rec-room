<?php
/**
 * Calendar - Edit Recurring Event
 *
 * @var array  $errors          Validation error strings
 * @var int    $event_id        Event ID
 * @var string $title           Pre-filled title (htmlspecialchars'd)
 * @var string $description     Pre-filled description (htmlspecialchars'd)
 * @var string $day_of_week     Selected day of week (0-6 string)
 * @var string $start_time      Pre-filled start time (htmlspecialchars'd)
 * @var string $end_time        Pre-filled end time (htmlspecialchars'd)
 * @var string $status          Current status value
 * @var array  $category_ids    Selected category IDs (int[])
 * @var array  $all_categories  Available categories [{cat_id, cat_name, cat_url_title}]
 * @var array  $days_options    Array of day_of_week int => label string
 * @var object $form_url        CP/URL object
 */
?>
<?php if (!empty($errors)): ?>
    <?php
        $alert = ee('CP/Alert')->makeInline('calendar-form-errors')
            ->asIssue()
            ->withTitle(lang('error'));
        foreach ($errors as $error) {
            $alert->addToBody($error);
        }
        echo $alert->render();
    ?>
<?php endif ?>

<form method="post" action="<?= htmlspecialchars($form_url->compile()) ?>">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(CSRF_TOKEN) ?>">
    <input type="hidden" name="event_id" value="<?= (int) $event_id ?>">

    <fieldset class="fieldset-required">
        <div class="field-instruct">
            <label><?= lang('title') ?></label>
        </div>
        <div class="field-control">
            <input type="text" name="title" value="<?= $title ?>" maxlength="255">
        </div>
    </fieldset>

    <fieldset>
        <div class="field-instruct">
            <label><?= lang('description') ?></label>
        </div>
        <div class="field-control">
            <textarea name="description"><?= $description ?></textarea>
        </div>
    </fieldset>

    <fieldset class="fieldset-required">
        <div class="field-instruct">
            <label><?= lang('day_of_week') ?></label>
        </div>
        <div class="field-control">
            <select name="day_of_week">
                <option value=""><?= lang('day_of_week') ?></option>
                <?php foreach ($days_options as $val => $label): ?>
                    <option value="<?= (int) $val ?>"<?= (string) $day_of_week === (string) $val ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                <?php endforeach ?>
            </select>
        </div>
    </fieldset>

    <fieldset class="fieldset-required">
        <div class="field-instruct">
            <label><?= lang('start_time') ?></label>
        </div>
        <div class="field-control">
            <input type="time" name="start_time" value="<?= $start_time ?>">
        </div>
    </fieldset>

    <fieldset class="fieldset-required">
        <div class="field-instruct">
            <label><?= lang('end_time') ?></label>
        </div>
        <div class="field-control">
            <input type="time" name="end_time" value="<?= $end_time ?>">
        </div>
    </fieldset>

    <fieldset>
        <div class="field-instruct">
            <label><?= lang('status') ?></label>
        </div>
        <div class="field-control">
            <select name="status">
                <option value="open"<?= $status === 'open' ? ' selected' : '' ?>><?= lang('open') ?></option>
                <option value="closed"<?= $status === 'closed' ? ' selected' : '' ?>><?= lang('closed') ?></option>
            </select>
        </div>
    </fieldset>

    <fieldset>
        <div class="field-instruct">
            <label><?= lang('categories') ?></label>
        </div>
        <div class="field-control">
            <?php if (empty($all_categories)): ?>
                <p><?= lang('no_categories') ?></p>
            <?php else: ?>
                <?php foreach ($all_categories as $cat): ?>
                    <label class="checkbox-label">
                        <input type="checkbox" name="category_ids[]" value="<?= (int) $cat['cat_id'] ?>"<?= in_array((int) $cat['cat_id'], $category_ids, true) ? ' checked' : '' ?>>
                        <div class="checkbox-label__text"><?= htmlspecialchars($cat['cat_name']) ?></div>
                    </label>
                <?php endforeach ?>
            <?php endif ?>
        </div>
    </fieldset>

    <div class="form-btns">
        <input type="submit" name="submit" value="<?= lang('save') ?>" class="button button--primary" data-submit-text="<?= lang('save') ?>" data-work-text="<?= lang('btn_saving') ?>">
        <a href="<?= htmlspecialchars(ee('CP/URL')->make('addons/settings/event_calendar')->compile()) ?>" class="button button--default">
            <?= lang('cancel') ?>
        </a>
    </div>
</form>
