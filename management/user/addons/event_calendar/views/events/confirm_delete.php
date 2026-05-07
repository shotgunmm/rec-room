<?php
/**
 * Calendar - Confirm Delete
 *
 * @var string $title      Event title (already htmlspecialchars'd)
 * @var object $action_url CP/URL object for the POST action
 * @var string $cancel_url URL to return to without deleting
 * @var string $csrf_token CSRF token
 */
?>
<div class="box">
    <div class="box-header">
        <h3><?= lang('confirm_delete_title') ?></h3>
    </div>
    <div class="box-content">
        <p><?= sprintf(lang('confirm_delete_body'), $title) ?></p>
        <form method="post" action="<?= htmlspecialchars($action_url->compile()) ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <a href="<?= htmlspecialchars($cancel_url) ?>" class="button button--default"><?= lang('cancel') ?></a>
            <button type="submit" class="button button--danger"><?= lang('delete') ?></button>
        </form>
    </div>
</div>
