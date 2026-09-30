<?php
/** @var array  $errors */
/** @var string $calendar_page_url */
/** @var string $url_style  'clean' or 'index' */
/** @var \ExpressionEngine\Library\CP\URL $form_url */
?>
<style>
.field-instruct label { font-weight: 600; }
fieldset { margin-bottom: 24px; }
.url-style-options { display: flex; gap: 48px; }
</style>
<?php if (!empty($errors)): ?>
<div class="app-notice-wrap">
    <?php foreach ($errors as $error): ?>
    <div class="app-notice app-notice---danger">
        <div class="app-notice__tag"><b class="app-notice__icon"></b></div>
        <div class="app-notice__content"><p><?= htmlspecialchars($error) ?></p></div>
    </div>
    <?php endforeach ?>
</div>
<?php endif ?>

<form method="post" action="<?= $form_url->compile() ?>">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(CSRF_TOKEN) ?>">

    <fieldset>
        <div class="field-instruct">
            <label><?= lang('calendar_page_url_label') ?></label>
            <em><?= lang('calendar_page_url_desc') ?></em>
        </div>
        <div class="field-control">
            <input type="text" name="calendar_page_url"
                   value="<?= $calendar_page_url ?>"
                   placeholder="https://example.com/calendar">
        </div>
    </fieldset>

    <fieldset>
        <div class="field-instruct">
            <label><?= lang('url_style_label') ?></label>
        </div>
        <div class="field-control url-style-options">
            <label class="radio-label">
                <input type="radio" name="url_style" value="clean"<?= $url_style === 'clean' ? ' checked' : '' ?>>
                <div class="radio-label__text">
                    <?= lang('url_style_clean') ?><br>
                    <em><?= lang('url_style_clean_desc') ?></em>
                </div>
            </label>
            <label class="radio-label">
                <input type="radio" name="url_style" value="index"<?= $url_style === 'index' ? ' checked' : '' ?>>
                <div class="radio-label__text">
                    <?= lang('url_style_index') ?><br>
                    <em><?= lang('url_style_index_desc') ?></em>
                </div>
            </label>
        </div>
    </fieldset>

    <div class="form-btns">
        <input type="submit" name="submit" value="<?= lang('save') ?>"
               class="button button--primary"
               data-submit-text="<?= lang('save') ?>"
               data-work-text="<?= lang('btn_saving') ?>"
               data-shortcut="s">
    </div>
</form>
