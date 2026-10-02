<style>
.form-builder-row-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 5px; }
.form-builder-row-actions .btn { margin: 0; }
</style>
<div class="panel">
    <div class="panel-heading">
        <div class="title-bar">
            <h3 class="title-bar__title"><?= lang('form_builder_all_forms') ?></h3>
            <div class="title-bar__extra-tools">
                <a href="<?= ee('CP/URL', 'addons/settings/form_builder/templates') ?>" class="btn"><?= lang('form_builder_new_from_template') ?></a>
                <a href="<?= ee('CP/URL', 'addons/settings/form_builder/edit_form') ?>" class="btn action"><?= lang('form_builder_create_form') ?></a>
            </div>
        </div>
    </div>
    <div class="panel-body">
        <?php if (empty($forms)): ?>
            <p class="no-results"><?= lang('form_builder_no_forms') ?></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table--loose">
                    <thead>
                        <tr>
                            <th><?= lang('form_builder_name') ?></th>
                            <th><?= lang('form_builder_label') ?></th>
                            <th><?= lang('form_builder_submissions_count') ?></th>
                            <th><?= lang('form_builder_status') ?></th>
                            <th class="text-right"><?= lang('form_builder_actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($forms as $form): ?>
                            <tr>
                                <td>
                                    <a href="<?= ee('CP/URL', 'addons/settings/form_builder/edit_fields/' . $form['form_id']) ?>">
                                        <code><?= htmlspecialchars($form['form_name']) ?></code>
                                    </a>
                                </td>
                                <td><?= htmlspecialchars($form['form_label']) ?></td>
                                <td>
                                    <a href="<?= ee('CP/URL', 'addons/settings/form_builder/submissions/' . $form['form_id']) ?>">
                                        <?= $form['submission_count'] ?>
                                    </a>
                                </td>
                                <td>
                                    <?php if ($form['is_active'] === 'y'): ?>
                                        <span class="st-open"><?= lang('open') ?></span>
                                    <?php else: ?>
                                        <span class="st-closed"><?= lang('closed') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right">
                                    <div class="form-builder-row-actions">
                                        <a href="<?= ee('CP/URL', 'addons/settings/form_builder/edit_fields/' . $form['form_id']) ?>" class="btn btn--small" title="<?= lang('form_builder_fields') ?>"><?= lang('form_builder_fields') ?></a>
                                        <a href="<?= ee('CP/URL', 'addons/settings/form_builder/edit_form/' . $form['form_id']) ?>" class="btn btn--small" title="<?= lang('form_builder_edit') ?>"><?= lang('form_builder_edit') ?></a>
                                        <form method="post" action="<?= ee('CP/URL', 'addons/settings/form_builder/save_as_template/' . $form['form_id']) ?>" style="display:contents;" onsubmit="var n=prompt('Template name:', <?= htmlspecialchars(json_encode($form['form_label'] . ' (template)'), ENT_QUOTES) ?>); if(n===null){return false;} this.template_name.value=n; return true;">
                                            <input type="hidden" name="csrf_token" value="<?= CSRF_TOKEN ?>">
                                            <input type="hidden" name="template_name" value="">
                                            <button type="submit" class="btn btn--small" title="<?= lang('form_builder_save_as_template') ?>"><?= lang('form_builder_save_as_template') ?></button>
                                        </form>
                                        <form method="post" action="<?= ee('CP/URL', 'addons/settings/form_builder/delete_form/' . $form['form_id']) ?>" style="display:contents;" onsubmit="return confirm('<?= lang('form_builder_confirm_delete_form') ?>')">
                                            <input type="hidden" name="csrf_token" value="<?= CSRF_TOKEN ?>">
                                            <button type="submit" class="btn btn--small btn--danger" title="<?= lang('form_builder_delete') ?>"><?= lang('form_builder_delete') ?></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="panel" style="margin-top: 20px;">
    <div class="panel-heading">
        <h3 class="title-bar__title">Template Tags</h3>
    </div>
    <div class="panel-body">
        <p>Use the following template tag to display a form:</p>
        <p><em>Note: the <strong>form_name</strong> value in the opening tag should be the same value as the 'Form Name' field in the form builder</em></p>
        <pre style="background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto;">{exp:form_builder:form name="form_name" class="" id="form_name"}
&lt;div class="container px-0"&gt;
  &lt;div class="row g-5"&gt;
  {fields}
    {if field_header}
    &lt;div class="col-12 mt-5 form-field"&gt;
      &lt;div class="form-label fw-bold mb-0"&gt;
        {field_header}
      &lt;/div&gt;
    &lt;/div&gt;
    {/if}
    {field_html}
  {/fields}
    &lt;div class="col-md-12 my-4 form-field"&gt;
      &lt;button class="cta" type="submit"&gt;Submit&lt;/button&gt;
    &lt;/div&gt;
  &lt;/div&gt;
&lt;/div&gt;
{/exp:form_builder:form}</pre>
        <p><em>Error handling works automatically with no setup — a submission or validation error (missing/invalid fields, reCAPTCHA rejection, rate limiting, a failed save, etc.) is shown in a message box the addon inserts at the top of the form on its own. Nothing above needs to change to get this. If a form's template already has its own element with a <strong>form-error</strong> class (from before <strong>{error_box}</strong> existed) or already uses <strong>{error_box}</strong> itself, the addon detects that and skips auto-inserting a second one — so upgrading an existing site's addon version is safe and won't duplicate the box.</em></p>

        <p>If you'd rather control exactly where that message box appears (e.g. right before the submit button instead of the top of the form), place the <strong>{error_box}</strong> variable yourself anywhere inside <strong>{exp:form_builder:form}</strong> — when present, the addon uses your placement instead of auto-inserting one:</p>
        <pre style="background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto;">{fields}
  {field_html}
{/fields}

&lt;div class="col-12"&gt;{error_box}&lt;/div&gt;

&lt;button type="submit"&gt;Submit&lt;/button&gt;</pre>

        <p>Use the following tag to show a message after a <em>successful</em> submission (place it inside <strong>{exp:form_builder:form}</strong>, wherever you want it — there's no client-side equivalent for success, so unlike errors it's always opt-in):</p>
        <pre style="background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto;">{exp:form_builder:success name="form_name"}
&lt;div class="col-12"&gt;
  &lt;div class="alert alert-success"&gt;Thank you for your submission!&lt;/div&gt;
&lt;/div&gt;
{/exp:form_builder:success}</pre>
    </div>
</div>
