<?php
/**
 * Calendar - Create Single Event
 *
 * @var array  $errors          Validation error strings
 * @var string $title           Pre-filled title (htmlspecialchars'd)
 * @var string $slug            Pre-filled slug (htmlspecialchars'd)
 * @var string $short_description_field   Rendered EE Rich Text Editor field HTML
 * @var string $event_details_field   Rendered EE Rich Text Editor field HTML
 * @var string $url             Pre-filled link URL (htmlspecialchars'd)
 * @var string $banner_image_field   Rendered EE drag-and-drop file field HTML
 * @var string $start_time      Pre-filled start time (htmlspecialchars'd)
 * @var string $end_time        Pre-filled end time (htmlspecialchars'd)
 * @var string $status          Current status value
 * @var array  $category_ids    Selected category IDs (int[])
 * @var array  $all_categories  Available categories [{cat_id, cat_name, cat_url_title}]
 * @var object $form_url        CP/URL object
 * @var array  $name_badges     field_name => rendered copyable "{field_name}" badge HTML
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

    <fieldset class="fieldset-required">
        <div class="field-instruct">
            <label><?= lang('title') ?></label>
            <?= $name_badges['title'] ?? '' ?>
        </div>
        <div class="field-control">
            <input type="text" name="title" value="<?= $title ?>" maxlength="255">
        </div>
    </fieldset>

    <fieldset>
        <div class="field-instruct">
            <label><?= lang('slug') ?></label>
            <?= $name_badges['slug'] ?? '' ?>
            <em><?= lang('slug_hint') ?></em>
        </div>
        <div class="field-control">
            <input type="text" name="slug" value="<?= $slug ?>" maxlength="255" placeholder="auto-generated from title if left blank">
        </div>
    </fieldset>

    <fieldset class="fieldset-required">
        <div class="field-instruct">
            <label><?= lang('start_time') ?></label>
            <?= $name_badges['start_time'] ?? '' ?>
        </div>
        <div class="field-control">
            <input type="text" name="start_time" value="<?= $start_time ?>" rel="date-picker" data-include_time="true" autocomplete="off">
        </div>
    </fieldset>

    <fieldset class="fieldset-required">
        <div class="field-instruct">
            <label><?= lang('end_time') ?></label>
            <?= $name_badges['end_time'] ?? '' ?>
        </div>
        <div class="field-control">
            <input type="text" name="end_time" value="<?= $end_time ?>" rel="date-picker" data-include_time="true" autocomplete="off">
        </div>
    </fieldset>

    <fieldset>
        <div class="field-instruct">
            <label><?= lang('status') ?></label>
            <?= $name_badges['status'] ?? '' ?>
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
            <label><?= lang('banner_image') ?></label>
            <?= $name_badges['banner_image'] ?? '' ?>
        </div>
        <div class="field-control">
            <?= $banner_image_field ?>
        </div>
    </fieldset>

    <fieldset>
        <div class="field-instruct">
            <label><?= lang('short_description') ?></label>
            <?= $name_badges['short_description'] ?? '' ?>
        </div>
        <div class="field-control">
            <?= $short_description_field ?>
        </div>
    </fieldset>

    <fieldset>
        <div class="field-instruct">
            <label><?= lang('event_details') ?></label>
            <?= $name_badges['event_details'] ?? '' ?>
            <em><?= lang('event_details_hint') ?></em>
        </div>
        <div class="field-control">
            <?= $event_details_field ?>
        </div>
    </fieldset>

    <fieldset>
        <div class="field-instruct">
            <label><?= lang('event_link') ?></label>
            <?= $name_badges['url'] ?? '' ?>
            <em><?= lang('event_link_hint') ?></em>
        </div>
        <div class="field-control">
            <input type="text" name="url" value="<?= $url ?>" maxlength="512">
        </div>
    </fieldset>

    <fieldset>
        <div class="field-instruct">
            <label><?= lang('categories') ?></label>
            <?= $name_badges['category_ids'] ?? '' ?>
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
        <input type="submit" name="submit" value="<?= lang('save') ?>" class="button button--primary" data-submit-text="<?= lang('save') ?>" data-work-text="<?= lang('btn_saving') ?>" data-shortcut="s">
        <a href="<?= htmlspecialchars(ee('CP/URL')->make('addons/settings/event_calendar')->compile()) ?>" class="button button--default">
            <?= lang('cancel') ?>
        </a>
    </div>
</form>
