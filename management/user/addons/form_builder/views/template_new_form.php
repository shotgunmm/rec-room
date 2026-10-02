<?php if (!empty($errors)): ?>
    <div class="alert alert-danger" style="margin-bottom:20px;">
        <ul style="margin:0;padding-left:1.25em;">
            <?php foreach ($errors as $err): ?><li><?= htmlspecialchars($err) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="panel">
    <div class="panel-heading">
        <div class="title-bar">
            <h3 class="title-bar__title"><?= lang('form_builder_new_from_template') ?>: <?= htmlspecialchars($template['name']) ?></h3>
            <div class="title-bar__extra-tools">
                <a href="<?= $back_url ?>" class="btn">← <?= lang('form_builder_all_templates') ?></a>
            </div>
        </div>
    </div>
    <div class="panel-body">
        <form method="post" action="<?= $save_url ?>">
            <input type="hidden" name="csrf_token" value="<?= CSRF_TOKEN ?>">

            <div style="margin-bottom:20px;">
                <label style="font-weight:bold;display:block;margin-bottom:5px;" for="form_label">Form Label <span style="color:#c0392b;">*</span></label>
                <input type="text" id="form_label" name="form_label" class="form-control" style="max-width:500px;"
                       value="<?= htmlspecialchars($form_label) ?>" required>
                <p style="color:#888;font-size:12px;margin-top:4px;">Shown in the control panel and used as the email subject fallback.</p>
            </div>

            <div style="margin-bottom:20px;">
                <label style="font-weight:bold;display:block;margin-bottom:5px;" for="form_name">Form Name <span style="color:#c0392b;">*</span></label>
                <input type="text" id="form_name" name="form_name" class="form-control" style="max-width:500px;"
                       value="<?= htmlspecialchars($form_name) ?>" placeholder="e.g. job-application-2026" required
                       pattern="[a-z0-9_-]+">
                <p style="color:#888;font-size:12px;margin-top:4px;">Lowercase letters, numbers, hyphens, underscores. Used in the template tag: <code>{exp:form_builder:form name="…"}</code></p>
            </div>

            <div style="margin-bottom:20px;">
                <p style="font-weight:bold;margin-bottom:6px;">Fields this template creates (<?= count($field_rows) ?>)</p>
                <?php if (empty($field_rows)): ?>
                    <p style="color:#888;">None — you'll add fields after the form is created.</p>
                <?php else: ?>
                    <ol style="margin:0;padding-left:1.5em;color:#444;">
                        <?php foreach ($field_rows as $fr): ?>
                            <li><?= htmlspecialchars($fr['field_label']) ?> <span style="color:#888;">— <?= htmlspecialchars($fr['field_type']) ?><?= $fr['is_required'] === 'y' ? ', required' : '' ?></span></li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn action">Create Form</button>
            <a href="<?= $back_url ?>" class="btn" style="margin-left:10px;">Cancel</a>
        </form>
    </div>
</div>
