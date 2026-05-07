<?php
/**
 * Calendar - Setup Category Group
 *
 * @var array  $errors           Validation error strings
 * @var array  $groups           Available EE category groups [{group_id, group_name}]
 * @var int    $current_group_id Currently configured group ID (0 if none)
 * @var string $create_group_url URL to EE's category group creation page
 * @var object $form_url         CP/URL object
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

<p><?= lang('setup_category_group_intro') ?></p>

<form method="post" action="<?= htmlspecialchars($form_url->compile()) ?>">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(CSRF_TOKEN) ?>">

    <fieldset class="fieldset-required">
        <div class="field-instruct">
            <label><?= lang('category_group') ?></label>
        </div>
        <div class="field-control">
            <?php if (empty($groups)): ?>
                <p><?= lang('no_category_groups') ?></p>
            <?php else: ?>
                <select name="cat_group_id">
                    <option value=""><?= lang('category_group') ?></option>
                    <?php foreach ($groups as $group): ?>
                        <option value="<?= (int) $group['group_id'] ?>"<?= $current_group_id === (int) $group['group_id'] ? ' selected' : '' ?>>
                            <?= htmlspecialchars($group['group_name']) ?>
                        </option>
                    <?php endforeach ?>
                </select>
            <?php endif ?>
        </div>
    </fieldset>

    <div class="form-btns">
        <?php if (!empty($groups)): ?>
            <input type="submit" name="submit" value="<?= lang('save') ?>" class="button button--primary" data-submit-text="<?= lang('save') ?>" data-work-text="<?= lang('btn_saving') ?>">
        <?php endif ?>
        <a href="<?= htmlspecialchars($create_group_url) ?>" class="button button--default">
            <?= lang('setup_create_new_group') ?>
        </a>
    </div>
</form>
