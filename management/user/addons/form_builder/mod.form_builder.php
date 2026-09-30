<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Form_builder
{
    public $return_data = '';

    private $site_id;
    private $action_id = null;
    private $settings = array();

    const MAILCHIMP_FAILURE_STATUSES = array(
        'failed',
        'failed_rate_limit',
        'failed_invalid_email',
        'failed_permanently_deleted',
        'skipped_misconfigured'
    );

    const MAILCHIMP_SUCCESS_STATUSES = array(
        'subscribed',
        'updated',
        'reactivated'
    );

    public function __construct()
    {
        $this->site_id = ee()->config->item('site_id');
        ee()->lang->loadfile('form_builder');
        $this->loadSettings();
    }

    private function loadSettings()
    {
        $results = ee()->db->where('site_id', $this->site_id)
            ->get('form_builder_settings')
            ->result_array();

        foreach ($results as $row) {
            $this->settings[$row['setting_key']] = $row['setting_value'];
        }
    }

    /**
     * Display a form
     *
     * {exp:form_builder:form name="contact"}
     *   {fields}
     *     <div class="form-group {field_class}">
     *       <label for="{field_name}">{field_label}{if is_required} *{/if}</label>
     *       {field_html}
     *     </div>
     *   {/fields}
     *   <button type="submit">Submit</button>
     * {/exp:form_builder:form}
     */
    public function form()
    {
        $form_name = ee()->TMPL->fetch_param('name');
        $form_id = ee()->TMPL->fetch_param('form_id');
        $class = ee()->TMPL->fetch_param('class', '');
        $id = ee()->TMPL->fetch_param('id', '');
        $return = ee()->TMPL->fetch_param('return', '');

        // Get form
        $query = ee()->db->where('site_id', $this->site_id);
        if ($form_id) {
            $query->where('form_id', $form_id);
        } else {
            $query->where('form_name', $form_name);
        }
        $form = $query->get('form_builder_forms')->row_array();

        if (!$form) {
            return '<!-- Form Builder: Form not found -->';
        }

        if ($form['is_active'] !== 'y') {
            return '<!-- Form Builder: Form is inactive -->';
        }

        // Get fields
        $fields = ee()->db->where('form_id', $form['form_id'])
            ->order_by('field_order', 'asc')
            ->get('form_builder_fields')
            ->result_array();

        // Get action URL (cached to avoid repeated DB query per request)
        if ($this->action_id === null) {
            $this->action_id = ee()->db->where('class', 'Form_builder')
                ->where('method', 'submit')
                ->get('actions')
                ->row('action_id');
        }

        $action_url = ee()->functions->fetch_site_index() . QUERY_MARKER . 'ACT=' . $this->action_id;

        // Fetch flash data before building fields so old values can repopulate inputs
        $flash_errors = ee()->session->flashdata('form_builder_errors_' . $form['form_id']);
        $flash_old    = ee()->session->flashdata('form_builder_old_'    . $form['form_id']);
        file_put_contents('/tmp/fb_debug.txt', date('H:i:s') . ' FORM LOAD form_id=' . $form['form_id'] . ' flash_errors=' . ($flash_errors ? json_encode(array_keys($flash_errors)) : 'none') . "\n", FILE_APPEND);

        // Build field variables
        $field_vars = array();
        $has_file = false;
        foreach ($fields as $field) {
            if ($field['field_type'] === 'file') {
                $has_file = true;
            }
            $field_vars[] = array(
                'field_id' => $field['field_id'],
                'field_name' => $field['field_name'],
                'field_header' => $field['field_header'],
                'field_label' => $field['field_label'],
                'field_type' => $field['field_type'],
                'is_required' => ($field['is_required'] === 'y'),
                'confirm' => $field['confirm'],
                'required' => ($field['is_required'] === 'y') ? 'required' : '',
                'placeholder' => $field['placeholder'],
                'default_value' => $field['default_value'],
                'css_class' => $field['css_class'],
                'field_class' => $field['css_class'],
                'field_html' => $this->renderFieldHtml($field, $flash_old ?: array(), $flash_errors ?: array()),
                'field_options' => self::parseOptions($field['field_options'])
            );
        }

        $has_errors = !empty($flash_errors);
        $error_list = $flash_errors ?: array();

        $errors_html = '';
        if ($has_errors && !empty($error_list)) {
            $messages = array();
            foreach ($error_list as $msg) {
                $messages[] = '<li>' . htmlspecialchars($msg, ENT_QUOTES) . '</li>';
            }
            $errors_html = '<div class="alert alert-danger" role="alert">'
                . '<ul style="margin:0;padding-left:1.25em;">' . implode('', $messages) . '</ul>'
                . '</div>';
        }

        // Parse template variables
        $vars = array(
            'form_id' => $form['form_id'],
            'form_name' => $form['form_name'],
            'form_label' => $form['form_label'],
            'action_url' => $action_url,
            'has_errors' => $has_errors,
            'errors_html' => $errors_html,
            'fields' => $field_vars,
            'old' => $flash_old ?: array()
        );

        $form_attrs = array(
            'method' => 'post',
            'action' => $action_url,
        );
        if ($class) {
            $form_attrs['class'] = $class;
        }
        if ($id) {
            $form_attrs['id'] = $id;
        }
        if ($has_file) {
            $form_attrs['enctype'] = 'multipart/form-data';
        }

        $form_attrs['novalidate'] = '';

        $attr_string = '';
        foreach ($form_attrs as $key => $val) {
            $attr_string .= ($val === '') ? ' ' . $key : ' ' . $key . '="' . htmlspecialchars($val, ENT_QUOTES) . '"';
        }

        // Build hidden fields
        $hidden = '<input type="hidden" name="form_id" value="' . $form['form_id'] . '">';
        $hidden .= '<input type="hidden" name="csrf_token" value="' . CSRF_TOKEN . '">';
        // Honeypot — visually hidden from humans, filled in by bots
        $hidden .= '<div style="position:absolute;left:-9999px;top:-9999px;"><input type="text" name="website_url" value="" autocomplete="off" tabindex="-1" aria-hidden="true"></div>';
        // Allow return override
        if ($return) {
            $hidden .= '<input type="hidden" name="return" value="' . htmlspecialchars($return, ENT_QUOTES) . '">';
        }

        // Parse the tag content
        $tagdata = ee()->TMPL->tagdata;
        $output = ee()->TMPL->parse_variables($tagdata, array($vars));

        // Load reCAPTCHA if enabled
        $recaptcha_script = '';
        if (
            isset($this->settings['recaptcha_enabled']) &&
            $this->settings['recaptcha_enabled'] === 'y' &&
            !empty($this->settings['recaptcha_site_key'])
        ) {
            $site_key = htmlspecialchars($this->settings['recaptcha_site_key'], ENT_QUOTES);

            $recaptcha_script = '
<script src="https://www.google.com/recaptcha/api.js?render=' . $site_key . '"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    var form = document.querySelector("form[action=\"' . addslashes($action_url) . '\"]");
    if (!form) return;

    form.addEventListener("submit", function(e) {
        var confirmFields = form.querySelectorAll("input[data-confirm-email=\'true\']");
        for (var i = 0; i < confirmFields.length; i++) {
            var confirmInput = confirmFields[i];
            var mainName = confirmInput.name.replace(/_confirm$/, \'\');
            var mainInput = form.querySelector("input[name=\'" + mainName + "\']");
            if (mainInput && mainInput.value !== confirmInput.value) {
                e.preventDefault();
                alert("Email addresses must match.");
                confirmInput.focus();
                return false;
            }
        }

        e.preventDefault();

        grecaptcha.ready(function () {
            grecaptcha.execute("' . $site_key . '", {action: "submit"}).then(function (token) {
                var input = document.createElement("input");
                input.type = "hidden";
                input.name = "g-recaptcha-response";
                input.value = token;
                form.appendChild(input);
                form.submit();
            });
        });
    });
});
</script>
';
            $recaptcha_script .= '<noscript><p class="form-recaptcha-notice" style="color:#c0392b;margin-top:0.5em;">JavaScript is required to submit this form. Please enable JavaScript and try again.</p></noscript>';
        }

        $form_selector = $id ? 'document.getElementById("' . addslashes($id) . '")' : 'document.querySelector("form[action=\"' . addslashes($action_url) . '\"]")';

        $validation_script = '
<script>
document.addEventListener("DOMContentLoaded", function () {
    var form = ' . $form_selector . ';
    if (!form) return;

    function isVisible(el) {
        return el.offsetParent !== null;
    }

    function addInlineError(container, message) {
        var errorEl = document.createElement("div");
        errorEl.className = "field-error-msg";
        errorEl.style.cssText = "color:#dc3545;font-size:0.875em;margin-top:0.25rem;font-weight:bold;";
        errorEl.textContent = message;
        container.appendChild(errorEl);
    }

    function clearFieldError(inputEl, container) {
        inputEl.style.border = "";
        inputEl.style.outline = "";
        inputEl.removeAttribute("data-field-invalid");
        if (container) {
            container.querySelectorAll(".field-error-msg").forEach(function(el) { el.remove(); });
        }
    }

    // Validates a single field, clears and re-sets its error state. Returns true if valid.
    function validateField(field) {
        var radioControl, container, empty;

        if (field.type === "radio") {
            radioControl = field.closest(".form-control");
            if (!radioControl) return true;
            container = radioControl.parentNode;
            clearFieldError(radioControl, container);
            if (!form.querySelector("input[name=\'" + field.name + "\']:checked")) {
                radioControl.style.border = "1px solid #dc3545";
                radioControl.setAttribute("data-field-invalid", "1");
                addInlineError(container, "This field is required.");
                return false;
            }
            return true;
        } else if (field.type === "checkbox") {
            container = field.parentNode;
            clearFieldError(field, container);
            if (!field.checked) {
                field.style.outline = "2px solid #dc3545";
                field.setAttribute("data-field-invalid", "1");
                addInlineError(container, "This field is required.");
                return false;
            }
            return true;
        } else {
            container = field.parentNode;
            clearFieldError(field, container);
            empty = field.value === "" || (field.type === "number" && Number(field.value) === 0);
            if (empty) {
                if (!field.hasAttribute("required")) return true;
                field.style.border = "1px solid #dc3545";
                field.setAttribute("data-field-invalid", "1");
                addInlineError(container, "This field is required.");
                return false;
            }
            if (field.type === "email" && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(field.value)) {
                field.style.border = "1px solid #dc3545";
                field.setAttribute("data-field-invalid", "1");
                addInlineError(container, "Please enter a valid email address.");
                return false;
            }
            if (field.type === "url") {
                try { new URL(field.value); } catch (e) {
                    field.style.border = "1px solid #dc3545";
                    field.setAttribute("data-field-invalid", "1");
                    addInlineError(container, "Please enter a valid URL (e.g. https://example.com).");
                    return false;
                }
            }
            return true;
        }
    }

    // Blur / change listeners for real-time per-field validation
    var seenBlurGroups = {};
    form.querySelectorAll("[required], input[type=\"email\"], input[type=\"url\"]").forEach(function(field) {
        if (field.type === "radio") {
            if (seenBlurGroups[field.name]) return;
            seenBlurGroups[field.name] = true;
            form.querySelectorAll("input[name=\'" + field.name + "\']").forEach(function(radio) {
                radio.addEventListener("change", function() { validateField(field); });
            });
        } else if (field.type === "file") {
            field.addEventListener("change", function() { validateField(field); });
        } else {
            field.addEventListener("blur", function() { validateField(field); });
        }
    });

    form.addEventListener("submit", function(e) {
        var errorBox = form.querySelector(".form-error");
        var hasError = false;
        var firstErrorEl = null;
        var seenSubmitGroups = {};

        form.querySelectorAll("[required], input[type=\"email\"], input[type=\"url\"]").forEach(function(field) {
            if (!isVisible(field)) return;
            if (field.type === "radio") {
                if (seenSubmitGroups[field.name]) return;
                seenSubmitGroups[field.name] = true;
            }
            if (!validateField(field)) {
                hasError = true;
                if (!firstErrorEl) firstErrorEl = field;
            }
        });

        form.querySelectorAll(".checkbox-group[data-required=\'true\']").forEach(function(group) {
            if (!isVisible(group)) return;
            var checked = Array.from(group.querySelectorAll("input[type=\'checkbox\']")).some(function(cb) { return cb.checked; });
            if (!checked) {
                hasError = true;
                if (!firstErrorEl) firstErrorEl = group.querySelector("input");
                addInlineError(group, "Please select at least one option.");
            }
        });

        if (hasError) {
            e.preventDefault();
            e.stopImmediatePropagation();
            if (errorBox) {
                if (errorBox.parentElement) errorBox.parentElement.style.display = "block";
                errorBox.style.display = "block";
                errorBox.textContent = "Please review all required fields before submitting.";
            }
            if (errorBox) errorBox.scrollIntoView({ behavior: "smooth", block: "nearest" });
        } else {
            if (errorBox) {
                errorBox.style.display = "none";
                errorBox.textContent = "";
            }
        }
    });
});
</script>
';

        $anchor_id = 'form-builder-' . $form['form_id'];

        $scroll_script = $has_errors
            ? '<script>document.addEventListener("DOMContentLoaded",function(){var el=document.getElementById("' . $anchor_id . '");if(el){window.scrollTo({top:el.getBoundingClientRect().top+window.pageYOffset-80,behavior:"smooth"});}});</script>'
            : '';

        $file_style = $has_file ? '<style>
input[type="file"]::file-selector-button{display:none}
input[type="file"].form-control{line-height:38px;padding-top:0;padding-bottom:0}
</style>' : '';

        return $file_style . '<div id="' . $anchor_id . '">'
            . '<form' . $attr_string . '>' . $hidden . $output . '</form>'
            . '</div>'
            . $validation_script . $recaptcha_script . $scroll_script;
    }

    /**
     * Render HTML for a single field
     */
    private function renderFieldHtml($field, $old = array(), $errors = array())
    {
        $name = htmlspecialchars($field['field_name'], ENT_QUOTES);
        $required = ($field['is_required'] === 'y') ? ' required' : '';
        $label_text = nl2br(htmlspecialchars($field['field_label'], ENT_QUOTES)) . ($field['is_required'] === 'y' ? ' <span class="red">*</span>' : '');
        $confirm = $field['confirm'];
        $placeholder = htmlspecialchars((string) ($field['placeholder'] ?? ''), ENT_QUOTES);
        $raw_old = isset($old[$field['field_name']]) ? $old[$field['field_name']] : null;
        $raw_default = $raw_old !== null ? $raw_old : ($field['default_value'] ?? '');
        $default = htmlspecialchars((string) $raw_default, ENT_QUOTES);
        $field_error = isset($errors[$field['field_name']]) ? $errors[$field['field_name']] : null;
        $error_html = $field_error
            ? '<div class="field-error-msg" style="color:#dc3545;font-size:0.875em;margin-top:0.25rem;font-weight:bold;">' . htmlspecialchars($field_error, ENT_QUOTES) . '</div>'
            : '';
        $error_border = $field_error ? ' style="border-color:#dc3545;"' : '';
        $css_class = htmlspecialchars((string) ($field['css_class'] ?? ''), ENT_QUOTES);

        switch ($field['field_type']) {
            case 'text':
                $html = '<div class="' . $css_class . ' form-field">
                    <label class="form-label" for="' . $name . '">' . $label_text . '</label>
                    <input class="form-control" data-label="' . htmlspecialchars($field['field_label'], ENT_QUOTES) . '" id="' . $name . '" type="text" name="' . $name . '" value="' . $default . '"' . ($placeholder !== '' ? ' placeholder="' . $placeholder . '"' : '') . ' ' . $required . $error_border . ' />
                    ' . $error_html . '
                </div>';
                return $html;

            case 'number':
                $html = '<div class="' . $css_class . ' form-field">
                    <label class="form-label" for="' . $name . '">' . $label_text . '</label>
                    <input class="form-control" data-label="' . htmlspecialchars($field['field_label'], ENT_QUOTES) . '" id="' . $name . '" type="number" name="' . $name . '" value="' . $default . '"' . ($placeholder !== '' ? ' placeholder="' . $placeholder . '"' : '') . ' ' . $required . $error_border . ' />
                    ' . $error_html . '
                </div>';
                return $html;

            case 'email':
                $confirm_error_html = isset($errors[$field['field_name'] . '_confirm'])
                    ? '<div class="field-error-msg" style="color:#dc3545;font-size:0.875em;margin-top:0.25rem;font-weight:bold;">' . htmlspecialchars($errors[$field['field_name'] . '_confirm'], ENT_QUOTES) . '</div>'
                    : '';
                $confirm_error_border = isset($errors[$field['field_name'] . '_confirm']) ? ' style="border-color:#dc3545;"' : '';
                $html = '<div class="' . $css_class . ' form-field">
                    <label class="form-label" for="' . $name . '">' . $label_text . '</label>
                    <input class="form-control" data-label="' . htmlspecialchars($field['field_label'], ENT_QUOTES) . '" id="' . $name . '" type="email" name="' . $name . '" value="' . $default . '"' . ($placeholder !== '' ? ' placeholder="' . $placeholder . '"' : '') . ' ' . $required . $error_border . ' />
                    ' . $error_html . '
                </div>';
                if ($confirm === 'y') {
                    $confirm_label = lang('form_builder_confirm_email_label');
                    $confirm_name  = $name . '_confirm';
                    $confirm_old   = htmlspecialchars(isset($old[$field['field_name'] . '_confirm']) ? $old[$field['field_name'] . '_confirm'] : '', ENT_QUOTES);
                    $html .= '<div class="' . $css_class . ' form-field">
                    <label class="form-label" for="' . $confirm_name . '">' . htmlspecialchars($confirm_label, ENT_QUOTES) . '</label>
                    <input class="form-control" id="' . $confirm_name . '" data-label="' . htmlspecialchars($confirm_label, ENT_QUOTES) . '" type="email" name="' . $confirm_name . '" value="' . $confirm_old . '" ' . $required . $confirm_error_border . ' data-confirm-email="true" />
                    ' . $confirm_error_html . '
                    <div class="form-text">' . htmlspecialchars($confirm_label, ENT_QUOTES) . '</div>
                </div>';
                }
                return $html;

            case 'url':
                $html = '<div class="' . $css_class . ' form-field">
                    <label class="form-label" for="' . $name . '">' . $label_text . '</label>
                    <input class="form-control" data-label="' . htmlspecialchars($field['field_label'], ENT_QUOTES) . '" id="' . $name . '" type="url" name="' . $name . '" value="' . $default . '"' . ($placeholder !== '' ? ' placeholder="' . $placeholder . '"' : '') . ' ' . $required . $error_border . ' />
                    ' . $error_html . '
                </div>';
                return $html;

            case 'phone':
                $html = '<div class="' . $css_class . ' form-field">
                    <label class="form-label" for="' . $name . '">' . $label_text . '</label>
                    <input class="form-control" data-label="' . htmlspecialchars($field['field_label'], ENT_QUOTES) . '" id="' . $name . '" type="tel" name="' . $name . '" value="' . $default . '"' . ($placeholder !== '' ? ' placeholder="' . $placeholder . '"' : '') . ' ' . $required . $error_border . ' />
                    ' . $error_html . '
                </div>';
                return $html;

            case 'textarea':
                $html = '<div class="' . $css_class . ' form-field"><label class="form-label" for="' . $name . '">' . $label_text . '</label>
                    <textarea class="form-control" data-label="' . htmlspecialchars($field['field_label'], ENT_QUOTES) . '" id="' . $name . '" name="' . $name . '"' . ($placeholder !== '' ? ' placeholder="' . $placeholder . '"' : '') . ' ' . $required . $error_border . ' >' . $default . '</textarea>
                    ' . $error_html . '
                </div>';
                return $html;

            case 'select':
                $options = self::parseOptions($field['field_options']);
                $placeholder_option = $placeholder != ''
                    ? '<option value="" disabled selected>' . htmlspecialchars($placeholder, ENT_QUOTES) . '</option>'
                    : '<option value="" selected></option>';
                $html = '<div class="' . $css_class . ' form-field">
                    <label class="form-label" for="' . $name . '">' . $label_text . '</label>
                    <select name="' . $name . '" id="' . $name . '" data-label="' . htmlspecialchars($field['field_label'], ENT_QUOTES) . '" class="form-control"' . $required . ($field_error ? ' style="border-color:#dc3545;"' : '') . '>
                    ' . $placeholder_option;
                foreach ($options as $opt) {
                    $selected = ($opt['value'] === $raw_default) ? ' selected' : '';
                    $html .= sprintf(
                        '<option value="%s"%s>%s</option>',
                        htmlspecialchars($opt['value'], ENT_QUOTES),
                        $selected,
                        htmlspecialchars($opt['label'], ENT_QUOTES)
                    );
                }
                $html .= '</select>
                    ' . $error_html . '
                </div>';
                return $html;

            case 'radio':
                $options = self::parseOptions($field['field_options']);
                $data_required = ($field['is_required'] === 'y') ? ' data-required="true"' : '';
                $html = '<div class="' . $css_class . ' form-field radio-group"' . $data_required . ' data-label="' . htmlspecialchars($field['field_label'], ENT_QUOTES) . '">';
                $html .= '<label class="form-label">' . $label_text . '</label>';
                $html .= '<div class="form-list d-flex flex-column">';
                foreach ($options as $i => $opt) {
                    $checked = ($opt['value'] === $raw_default) ? ' checked' : '';
                    $option_id = $name . '_' . $i . '_' . preg_replace('/[^a-z0-9]+/', '-', strtolower($opt['value']));
                    $html .= '<label style="width: fit-content"><input type="radio" name="' . $name . '" id="' . $option_id . '" value="' . htmlspecialchars($opt['value'], ENT_QUOTES) . '"' . $checked . ' ' . $required . '> ' . htmlspecialchars($opt['label'], ENT_QUOTES) . '</label>';
                }
                $html .= '</div>';
                $html .= $error_html;
                if ($placeholder != '') {
                    $html .= '<div class="form-text">' . htmlspecialchars($placeholder, ENT_QUOTES) . '</div>';
                }
                $html .= '</div>';
                return $html;

            case 'checkbox':
                $options = self::parseOptions($field['field_options']);
                if (empty($options)) {
                    // Single checkbox
                    $checked = ($raw_old !== null) ? ($raw_old ? ' checked' : '') : (($default === 'y' || $default === '1') ? ' checked' : '');
                    $data_required = ($field['is_required'] === 'y') ? ' data-required="true"' : '';
                    return '<div class="' . $css_class . ' form-field"' . $data_required . ' data-label="' . htmlspecialchars($field['field_label'], ENT_QUOTES) . '"><div class="form-check mb-3 mt-3 align-items-center d-flex">'
                        . sprintf(
                            '<input class="form-check-input me-2 mt-0" type="checkbox" name="%s" id="%s" value="%s"%s />'
                            . '<label class="form-check-label" for="%s">%s</label>',
                            $name,
                            $name,
                            $name,
                            $checked,
                            $name,
                            nl2br(htmlspecialchars($field['field_label'], ENT_QUOTES)) . ($field['is_required'] === 'y' ? ' <span class="red">*</span>' : '')
                        )
                        . '</div>' . $error_html . '</div>';
                }
                // Multiple checkboxes — old value and default are stored as comma-separated strings
                $checked_source = $raw_old !== null ? $raw_old : ($field['default_value'] ?? '');
                $old_checked = !empty($checked_source)
                    ? array_map('trim', explode(',', $checked_source))
                    : array();
                $data_required = ($field['is_required'] === 'y') ? ' data-required="true"' : '';
                $html = '<div class="' . $css_class . ' form-field checkbox-group"' . $data_required . ' data-label="' . htmlspecialchars($field['field_label'], ENT_QUOTES) . '">
                    <label class="form-label">' . $label_text . '</label>
                    <div class="form-list d-flex flex-column">';
                foreach ($options as $i => $opt) {
                    $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($opt['value']));
                    $option_id = $name . '_' . $i . '_' . $slug;
                    $checked = (!empty($old_checked) && in_array($opt['value'], $old_checked)) ? ' checked' : '';
                    $html .= sprintf(
                        '<label style="width: fit-content"><input type="checkbox" name="%s[]" value="%s"%s> %s</label>',
                        $name,
                        htmlspecialchars($opt['value'], ENT_QUOTES),
                        $checked,
                        htmlspecialchars($opt['label'], ENT_QUOTES)
                    );
                }
                return $html . '</div>' . $error_html . '</div>';

            case 'mailchimp_subscription':
                $mc_config = !empty($field['field_config'])
                    ? (json_decode($field['field_config'], true) ?: array())
                    : array();

                if (!$this->isMailchimpFieldConfigured($field, $mc_config)) {
                    return '';
                }

                $mc_default_checked = ($mc_config['mailchimp_default_checked'] ?? 'n') === 'y';
                $mc_checked_attr = ($raw_old !== null)
                    ? ($raw_old ? ' checked' : '')
                    : ($mc_default_checked ? ' checked' : '');
                $mc_data_required = ($field['is_required'] === 'y') ? ' data-required="true"' : '';

                $mc_header_html = '';
                if (!empty($field['field_header'])) {
                    $mc_header_html = '<div class="form-field-header">'
                        . nl2br(htmlspecialchars($field['field_header'], ENT_QUOTES))
                        . '</div>';
                }

                return $mc_header_html . sprintf(
                    '<div class="' . $css_class . ' form-field"' . $mc_data_required
                    . ' data-label="' . htmlspecialchars($field['field_label'], ENT_QUOTES)
                    . '"><div class="form-check mb-3 mt-3 align-items-center d-flex">'
                    . '<input class="form-check-input me-2 mt-0" type="checkbox" name="%s" id="%s" value="%s"%s />'
                    . '<label class="form-check-label" for="%s">%s</label>'
                    . '</div></div>',
                    $name,
                    $name,
                    $name,
                    $mc_checked_attr,
                    $name,
                    nl2br(htmlspecialchars($field['field_label'], ENT_QUOTES))
                        . ($field['is_required'] === 'y' ? ' <span class="red">*</span>' : '')
                );

            case 'date':
                $html = '<div class="' . $css_class . ' form-field">
                    <label class="form-label" for="' . $name . '">' . $label_text . '</label>
                    <input class="form-control" data-label="' . htmlspecialchars($field['field_label'], ENT_QUOTES) . '" id="' . $name . '" type="date" name="' . $name . '" value="' . $default . '" ' . $required . $error_border . ' />
                    ' . $error_html . '
                    <div class="form-text">' . $placeholder . '</div>
                </div>';
                return $html;

            case 'time':
                $time_placeholder = $placeholder;
                if (!empty($field['placeholder']) && preg_match('/^\d{2}:\d{2}/', $field['placeholder'])) {
                    $dt = \DateTime::createFromFormat('H:i', substr($field['placeholder'], 0, 5));
                    if ($dt) {
                        $time_placeholder = htmlspecialchars($dt->format('g:i A'), ENT_QUOTES);
                    }
                }
                $html = '<div class="' . $css_class . ' form-field">
                    <label class="form-label" for="' . $name . '">' . $label_text . '</label>
                    <input class="form-control" lang="en-US" data-label="' . htmlspecialchars($field['field_label'], ENT_QUOTES) . '" id="' . $name . '" type="time" name="' . $name . '" value="' . $default . '" ' . $required . $error_border . ' />
                    ' . $error_html . '
                    <div class="form-text">' . $time_placeholder . '</div>
                </div>';
                return $html;

            case 'file':
                $accept = '';

                if (!empty($field['file_types'])) {
                    $types = array_map('trim', explode(',', $field['file_types']));
                    $accept = ' accept=".' . implode(',.', $types) . '"';
                }
                $html = '<div class="' . $css_class . ' form-field">
                    <label class="form-label">' . $label_text . '</label>
                    <div class="upload-file">
                    <div class="input-group">
                        <input class="form-control" data-label="' . htmlspecialchars($field['field_label'], ENT_QUOTES) . '" id="' . $name . '" name="' . $name . '[]" type="file" multiple' . $accept . $required . ' />
                        <div id="feedback_' . $name . '" class=""></div>
                        <div class="file-list" id="fileList_' . $name . '"></div>
                        <label class="input-group-text" for="' . $name . '">Upload</label>
                    </div>
                    <div class="form-text">' . $placeholder . '</div>
                    </div>
                </div>';
                return $html;

            case 'warning':
                $warning_color_style = '';
                if (!empty($field['field_config'])) {
                    $warning_cfg = json_decode($field['field_config'], true) ?: array();
                    $warning_color = $warning_cfg['warning_color'] ?? '';
                    if ($warning_color && preg_match('/^#[0-9a-fA-F]{3,6}$/', $warning_color)) {
                        $warning_color_style = ' style="color:' . htmlspecialchars($warning_color, ENT_QUOTES) . '"';
                    }
                }
                $html = '<div class="' . $css_class . ' form-field">'
                    . '<p class="fw-bold mb-1"' . $warning_color_style . '>' . htmlspecialchars($field['field_label'], ENT_QUOTES) . '</p>'
                    . ($placeholder ? '<div class="form-text">' . $placeholder . '</div>' : '')
                    . '</div>';
                return $html;

            default:
                return sprintf(
                    '<input type="text" name="%s" id="%s" value="%s" placeholder="%s" class="form-field %s"%s>',
                    $name,
                    $name,
                    $default,
                    $placeholder,
                    $css_class,
                    $required
                );
        }
    }

    /**
     * Parse field options (one per line, optionally value|label format)
     */
    public static function parseOptions($options_string)
    {
        if (empty($options_string)) {
            return array();
        }

        $lines = explode("\n", $options_string);
        $options = array();

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            if (strpos($line, '|') !== false) {
                list($value, $label) = explode('|', $line, 2);
                $options[] = array(
                    'value' => trim($value),
                    'label' => trim($label)
                );
            } else {
                $options[] = array(
                    'value' => $line,
                    'label' => $line
                );
            }
        }

        return $options;
    }

    /**
     * Handle form submission (ACT method)
     */
    public function submit()
    {
        // EE validates CSRF for all front-end POST requests before this method runs.
        // A duplicate check here is not possible — EE removes csrf_token from $_POST
        // after its own validation, so any manual check would always fail on live servers.

        // Honeypot check — bots fill in hidden fields, humans leave them blank.
        // Silently appear to succeed so bots do not retry with the field empty.
        if (ee()->input->post('website_url') !== false && ee()->input->post('website_url') !== '') {
            $form_id_raw = (int) ee()->input->post('form_id');
            ee()->session->set_flashdata('form_builder_success_' . $form_id_raw, true);
            $referrer = ee()->input->server('HTTP_REFERER');
            if ($referrer && $this->isSafeRedirect($referrer)) {
                ee()->functions->redirect($referrer);
            } else {
                ee()->functions->redirect(ee()->functions->fetch_site_index());
            }
            return;
        }

        $form_id = (int) ee()->input->post('form_id');

        if (!$form_id) {
            $this->handleError('Invalid form submission');
            return;
        }

        // Get form
        $form = ee()->db->where('form_id', $form_id)
            ->where('site_id', $this->site_id)
            ->get('form_builder_forms')
            ->row_array();

        if (!$form || $form['is_active'] !== 'y') {
            $this->handleError('Form not found or inactive', $form_id);
            return;
        }

        // Rate limit: max 5 submissions per IP per form per 10 minutes (skipped on localhost)
        $is_localhost = in_array(
            ee()->input->server('SERVER_NAME'),
            ['localhost', '127.0.0.1', '::1']
        );

        $ip       = ee()->input->ip_address();
        $rate_key = 'form_builder_rate_' . md5($ip . '_' . $form_id);
        $attempts = ee()->cache->get($rate_key, Cache::LOCAL_SCOPE);
        $attempts = ($attempts !== false) ? (int) $attempts : 0;

        if (!$is_localhost && $attempts >= 5) {
            $this->handleError('Too many submissions. Please wait a few minutes and try again.', $form_id);
            return;
        }

        // Verify reCAPTCHA if enabled (skip on localhost for local dev)
        if (
            !$is_localhost &&
            isset($this->settings['recaptcha_enabled']) &&
            $this->settings['recaptcha_enabled'] === 'y'
        ) {
            $token = ee()->input->post('g-recaptcha-response');

            if (empty($token)) {
                $this->handleError('reCAPTCHA verification failed.', $form_id);
                return;
            }

            $secret = !empty($this->settings['recaptcha_site_secret'])
                ? ee('Encrypt')->decode($this->settings['recaptcha_site_secret'])
                : '';

            $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => http_build_query([
                    'secret'   => $secret,
                    'response' => $token,
                ]),
                CURLOPT_TIMEOUT        => 5,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);
            $response = curl_exec($ch);
            $curl_error = curl_error($ch);
            curl_close($ch);

            if ($response === false) {
                log_message('error', 'Form Builder: reCAPTCHA curl request failed for form ' . $form_id . ': ' . $curl_error);
                $this->handleError('reCAPTCHA verification failed.', $form_id);
                return;
            }

            $result = json_decode($response, true);

            if (
                !$result ||
                !$result['success'] ||
                $result['score'] < 0.3 ||
                (isset($result['action']) && $result['action'] !== 'submit')
            ) {
                $this->handleError('reCAPTCHA verification failed.', $form_id);
                return;
            }
        }

        // Get fields
        $fields = ee()->db->where('form_id', $form_id)
            ->order_by('field_order', 'asc')
            ->get('form_builder_fields')
            ->result_array();

        // Collect and validate data
        $submission_data = array();
        $errors = array();
        $reply_to_email = null;

        foreach ($fields as $field) {
            if ($field['field_type'] === 'warning') {
                continue;
            }
            $field_name = $field['field_name'];
            $value = null;

            if ($field['field_type'] === 'file') {
                $uploaded_files = [];
                if (!empty($_FILES[$field_name]['name'][0])) {
                    foreach ($_FILES[$field_name]['name'] as $i => $name) {
                        $file_data = [
                            'name'     => $_FILES[$field_name]['name'][$i],
                            'type'     => $_FILES[$field_name]['type'][$i],
                            'tmp_name' => $_FILES[$field_name]['tmp_name'][$i],
                            'error'    => $_FILES[$field_name]['error'][$i],
                            'size'     => $_FILES[$field_name]['size'][$i]
                        ];
                        $file_result = $this->handleFileUpload($field_name, $field, $file_data);
                        if ($file_result['error']) {
                            $errors[$field_name] = $file_result['error'];
                        } else {
                            $uploaded_files[] = $file_result['filename'];
                        }
                    }

                    // If any file in the batch failed, delete the ones that already moved to disk
                    if (isset($errors[$field_name]) && !empty($uploaded_files)) {
                        foreach ($uploaded_files as $orphan) {
                            $orphan_path = FCPATH . 'uploads/form_builder/' . $orphan;
                            if (file_exists($orphan_path)) {
                                @unlink($orphan_path);
                            }
                        }
                        $uploaded_files = [];
                    }

                    $value = implode(',', $uploaded_files);
                }

                if ($field['is_required'] === 'y' && empty($uploaded_files)) {
                    $errors[$field_name] = $field['field_label'] . ' is required';
                }
            } elseif ($field['field_type'] === 'checkbox') {
                $value = ee()->input->post($field_name);
                if ($field['is_required'] === 'y' && empty($value)) {
                    $errors[$field_name] = $field['field_label'] . ' is required';
                }
                if (is_array($value)) {
                    $value = implode(', ', $value);
                }
            } elseif ($field['field_type'] === 'mailchimp_subscription') {
                $value = ee()->input->post($field_name) ? 'y' : 'n';
            } else {
                $value = ee()->input->post($field_name);
            }

            // Validate required fields
            if ($field['is_required'] === 'y'
                && $field['field_type'] !== 'file'
                && $field['field_type'] !== 'checkbox'
                && $field['field_type'] !== 'mailchimp_subscription') {
                if ($value === null || $value === '' || $value === false) {
                    $errors[$field_name] = $field['field_label'] . ' is required';
                }
            }

            // Validate email format
            if ($field['field_type'] === 'email' && !empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$field_name] = $field['field_label'] . ' must be a valid email address';
            }

            // Validate URL format and reject dangerous schemes (javascript:, data:, file:, etc.)
            if ($field['field_type'] === 'url' && !empty($value)) {
                $url_scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
                if (!filter_var($value, FILTER_VALIDATE_URL) || !in_array($url_scheme, array('http', 'https'), true)) {
                    $errors[$field_name] = $field['field_label'] . ' must be a valid URL (http or https only)';
                }
            }

            $submission_data[$field_name] = array(
                'label' => $field['field_label'],
                'value' => $value,
                'type' => $field['field_type']
            );

            // Track reply-to email
            if (
                $field['field_name'] === $form['reply_to_field']
                && $field['field_type'] === 'email'
            ) {
                $reply_to_email = $value;
            }

        }

        // Email confirmation validation — check each confirm-pair independently
        foreach ($fields as $field) {
            if ($field['field_type'] === 'email' && $field['confirm'] === 'y') {
                $main_name     = $field['field_name'];
                $confirm_name  = $main_name . '_confirm';
                $main_value    = trim((string) ee()->input->post($main_name));
                $confirm_value = trim((string) ee()->input->post($confirm_name));
                if ($main_value !== $confirm_value) {
                    $errors[$confirm_name] = 'Email addresses do not match';
                }
            }
        }

        // If errors, redirect back with flash data
        if (!empty($errors)) {
            file_put_contents('/tmp/fb_debug.txt', date('H:i:s') . ' ERRORS SET form_id=' . $form_id . ' keys=' . implode(',', array_keys($errors)) . "\n", FILE_APPEND);
            ee()->session->set_flashdata('form_builder_errors_' . $form_id, $errors);
            $old_data = $_POST;
            unset($old_data['csrf_token'], $old_data['form_id'], $old_data['return']);
            // Normalize checkbox arrays to comma-separated strings; drop file fields (can't repopulate)
            foreach ($fields as $_f) {
                if ($_f['field_type'] === 'file') {
                    unset($old_data[$_f['field_name']]);
                } elseif (isset($old_data[$_f['field_name']]) && is_array($old_data[$_f['field_name']])) {
                    $old_data[$_f['field_name']] = implode(',', $old_data[$_f['field_name']]);
                }
            }
            ee()->session->set_flashdata('form_builder_old_' . $form_id, $old_data);

            $return = ee()->input->post('return');
            if (!$return || !$this->isSafeRedirect($return)) {
                $return = null;
            }
            if (!$return) {
                $referrer = ee()->input->server('HTTP_REFERER');
                if ($referrer && $this->isSafeRedirect($referrer)) {
                    $return = $referrer;
                }
            }
            if (!$return) {
                $return = ee()->functions->fetch_site_index();
            }

            ee()->functions->redirect($return);
            return;

        }

        // Save submission
        $submission = array(
            'form_id' => $form_id,
            'site_id' => $this->site_id,
            'submission_data' => json_encode($submission_data),
            'ip_address' => ee()->input->ip_address(),
            'user_agent' => ee()->input->user_agent(),
            'status' => 'new',
            'is_spam' => 'n',
            'email_sent' => 'n',
            'confirmation_sent' => 'n',
            'submitted_at' => date('Y-m-d H:i:s')
        );

        try {
            ee()->db->insert('form_builder_submissions', $submission);
            $submission_id = ee()->db->insert_id();
        } catch (\Exception $e) {
            log_message('error', 'Form Builder: failed to save submission for form ' . $form_id . ': ' . $e->getMessage());
            $submission_id = 0;
        }

        if (!$submission_id) {
            log_message('error', 'Form Builder: failed to save submission for form ' . $form_id);
            $this->handleError('Your submission was unsuccessful. Please try again.', $form_id);
            return;
        }

        // Increment rate limit counter only on a successful save
        ee()->cache->save($rate_key, $attempts + 1, 600, Cache::LOCAL_SCOPE);

        // Mailchimp subscription processing
        $mailchimp_result = $this->processMailchimpSubscription($form, $fields, $submission_id, $submission_data);
        if ($mailchimp_result['status'] !== 'none') {
            ee()->db->where('submission_id', $submission_id)
                ->update('form_builder_submissions', array(
                    'mailchimp_status' => $mailchimp_result['status'],
                    'mailchimp_error'  => !empty($mailchimp_result['error_detail']) ? $mailchimp_result['error_detail'] : null
                ));
        }

        // Send notification email
        $email_sent = $this->sendNotificationEmail($form, $submission_data, $reply_to_email, $mailchimp_result);
        if ($email_sent) {
            ee()->db->where('submission_id', $submission_id)
                ->update('form_builder_submissions', array('email_sent' => 'y'));
        }

        // Send confirmation email
        if ($form['send_confirmation'] === 'y' && $reply_to_email) {
            $confirmation_sent = $this->sendConfirmationEmail($form, $submission_data, $reply_to_email, $mailchimp_result);
            if ($confirmation_sent) {
                ee()->db->where('submission_id', $submission_id)
                    ->update('form_builder_submissions', array('confirmation_sent' => 'y'));
            }
        }

        // Send separate Mailchimp alert email if applicable
        $mc_is_failure = in_array($mailchimp_result['status'], self::MAILCHIMP_FAILURE_STATUSES, true);
        $mc_alerts_email = !empty($this->settings['mailchimp_alerts_email'])
            ? trim((string) $this->settings['mailchimp_alerts_email'])
            : '';
        if ($mc_is_failure && $mc_alerts_email !== '') {
            // Parse recipient_email (may be comma-separated) and check if alerts_email is already covered
            $recipient_addresses = array_filter(array_map('trim', explode(',', (string) $form['recipient_email'])));
            $alert_already_covered = false;
            foreach ($recipient_addresses as $addr) {
                if (strcasecmp($mc_alerts_email, $addr) === 0) {
                    $alert_already_covered = true;
                    break;
                }
            }
            if (!$alert_already_covered) {
                $this->sendMailchimpAlertEmail($mc_alerts_email, $form, $submission_data, $submission_id, $mailchimp_result);
            }
        }

        // Set success flashdata before redirect so {exp:form_builder:success} tag works
        ee()->session->set_flashdata('form_builder_success_' . $form_id, true);

        // Redirect
        $return = ee()->input->post('return');
        if ($return && $this->isSafeRedirect($return)) {
            ee()->functions->redirect($return);
        } elseif (!empty($form['success_redirect']) && $this->isSafeRedirect($form['success_redirect'])) {
            ee()->functions->redirect($form['success_redirect']);
        } else {
            ee()->functions->redirect(ee()->functions->fetch_site_index());
        }
    }

    /**
     * Handle file upload
     */
    private function handleFileUpload($field_name, $field, $file_data = null)
    {
        $file = $file_data ?: $_FILES[$field_name];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return array('error' => 'File upload failed', 'filename' => null);
        }

        // Hard-coded deny list — never allow executable extensions regardless of admin config
        $always_blocked = ['php', 'php3', 'php4', 'php5', 'php7', 'phtml', 'phar',
                           'pl', 'py', 'rb', 'cgi', 'sh', 'asp', 'aspx', 'exe', 'js', 'jsx', 'ts',
                           'svg', 'html', 'htm'];

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (in_array($ext, $always_blocked)) {
            return array('error' => 'File type not allowed', 'filename' => null);
        }

        // If admin has configured allowed types, enforce that allowlist
        if (!empty($field['file_types'])) {
            $allowed = array_map('trim', explode(',', strtolower($field['file_types'])));
            if (!empty($allowed) && !in_array($ext, $allowed)) {
                return array('error' => 'File type not allowed', 'filename' => null);
            }
        } else {
            // No allowlist configured — deny everything as safe default
            return array('error' => 'No allowed file types configured for this field', 'filename' => null);
        }

        // Verify actual MIME type using finfo as a second layer of defence
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            // Known-safe MIME types per extension — extensions not in this map are passed through
            $safe_mimes = array(
                'pdf'  => array('application/pdf'),
                'doc'  => array('application/msword'),
                'docx' => array('application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                'xls'  => array('application/vnd.ms-excel'),
                'xlsx' => array('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
                'jpg'  => array('image/jpeg'),
                'jpeg' => array('image/jpeg'),
                'png'  => array('image/png'),
                'gif'  => array('image/gif'),
                'webp' => array('image/webp'),
                'txt'  => array('text/plain'),
                'csv'  => array('text/csv', 'text/plain'),
                'zip'  => array('application/zip', 'application/x-zip-compressed'),
            );

            if (isset($safe_mimes[$ext]) && !in_array($mime, $safe_mimes[$ext], true)) {
                return array('error' => 'File type not allowed', 'filename' => null);
            }
        }

        // Validate file size
        if (!empty($field['max_file_size'])) {
            $max_bytes = $field['max_file_size'] * 1024; // KB to bytes
            if ($file['size'] > $max_bytes) {
                return array('error' => 'File too large', 'filename' => null);
            }
        }

        // Create upload directory
        $upload_dir = FCPATH . 'uploads/form_builder/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // Ensure protection files exist — check once per request using static flag
        static $upload_dir_secured = false;
        if (!$upload_dir_secured) {
            $htaccess = $upload_dir . '.htaccess';
            if (!file_exists($htaccess)) {
                file_put_contents($htaccess, "Options -Indexes\n<FilesMatch \"\\.(?i:php|php3|php4|php5|phtml|phar|pl|py|rb|cgi|sh|asp|aspx|exe)$\">\n    Deny from all\n</FilesMatch>\n");
            }
            $index = $upload_dir . 'index.php';
            if (!file_exists($index)) {
                file_put_contents($index, "<?php exit('No direct access.'); ?>\n");
            }
            // Warn on nginx — .htaccess has no effect; a server-level location block is required
            if (isset($_SERVER['SERVER_SOFTWARE']) && stripos($_SERVER['SERVER_SOFTWARE'], 'nginx') !== false) {
                log_message('error',
                    'Form Builder: upload directory is not protected on nginx. Add a location block to your nginx config to deny direct access to ' . $upload_dir
                );
            }

            $upload_dir_secured = true;
        }

        // Generate unique filename
        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        $filepath = $upload_dir . $filename;

        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            return [
                'error' => null,
                'filename' => $filename
            ];
        }

        log_message('error', 'Form Builder: upload failed. TMP: ' . $file['tmp_name'] . ' | DEST: ' . $filepath);

        return [
            'error' => 'Failed to move uploaded file',
            'filename' => null
        ];

    }

    /**
     * Send notification email to recipient
     */
    private function sendNotificationEmail($form, $submission_data, $reply_to = null, $mailchimp_result = null)
    {
        if (empty($form['recipient_email'])) {
            return false;
        }

        $body = "New submission from: " . $form['form_label'] . "\n\n";
        $attachments = [];

        $mc_fail = $mailchimp_result !== null && in_array($mailchimp_result['status'], self::MAILCHIMP_FAILURE_STATUSES, true);

        foreach ($submission_data as $field_name => $field_data) {
            if (!empty($field_data['value']) && $field_data['type'] === 'file') {
                $body .= strtoupper($field_data['label']) . ":\n[Attached File]\n\n";
                $files = explode(',', $field_data['value']);
                foreach ($files as $file) {
                    $file = trim($file);
                    $filepath = FCPATH . 'uploads/form_builder/' . $file;
                    if (file_exists($filepath)) {
                        $attachments[] = $filepath;
                    }
                }
            } elseif ($field_data['type'] === 'mailchimp_subscription') {
                $mc_success = $mailchimp_result !== null && in_array($mailchimp_result['status'], self::MAILCHIMP_SUCCESS_STATUSES, true);
                if ($field_data['value'] === 'y' && $mc_fail) {
                    $display = 'Yes - subscription failed, see Mailchimp subscription failure alert below';
                } elseif ($field_data['value'] === 'y' && $mc_success) {
                    $display = 'Yes - successfully ' . $mailchimp_result['status'];
                } elseif ($field_data['value'] === 'y') {
                    $display = 'Yes';
                } else {
                    $display = 'No';
                }
                $body .= strtoupper($field_data['label']) . ":\n" . $display . "\n\n";
            } else {
                $body .= strtoupper($field_data['label']) . ":\n" . $field_data['value'] . "\n\n";
            }
        }

        $body .= "\n---\nSubmitted at: " . date('Y-m-d H:i:s');

        // Append Mailchimp alert section if subscription failed
        if ($mc_fail) {
            $body .= "\n\n---\nMAILCHIMP SUBSCRIPTION ALERT\n";
            $body .= "Status: " . $mailchimp_result['status'] . "\n";
            if (!empty($mailchimp_result['error_detail'])) {
                $body .= "Detail: " . $mailchimp_result['error_detail'] . "\n";
            }
            if ($mailchimp_result['status'] === 'failed_permanently_deleted') {
                $body .= "Why: This contact was permanently deleted from Mailchimp and cannot be re-imported via the API.\n";
                $body .= "Fix: The contact must re-subscribe through a Mailchimp signup form, or be manually added through the Mailchimp dashboard (Audience → Add a contact).\n";
            }
            $body .= "Note: submission was saved and the user was served the success page. The Mailchimp subscription did NOT complete; add manually if needed.\n";
        }

        $subject = !empty($form['email_subject'])
            ? $form['email_subject']
            : 'New Form Submission: ' . $form['form_label'];

        $from_email = !empty($this->settings['from_email'])
            ? $this->settings['from_email']
            : ee()->config->item('webmaster_email');

        $from_name = !empty($this->settings['from_name'])
            ? $this->settings['from_name']
            : ee()->config->item('site_name');

        return $this->sendEmail(
            $form['recipient_email'],
            $subject,
            $body,
            $reply_to,
            $from_name,
            $from_email,
            $attachments
        );
    }


    /**
     * Send confirmation email to submitter
     */
    private function sendConfirmationEmail($form, $submission_data, $to_email, $mailchimp_result = null)
    {
        if (empty($form['confirmation_template'])) {
            return false;
        }

        // Parse template with submission data
        $body = $form['confirmation_template'];
        $search  = [];
        $replace = [];
        foreach ($submission_data as $field_name => $field_data) {
            $search[]  = '{' . $field_name . '}';
            $replace[] = (string) $field_data['value'];
        }
        $body = str_replace($search, $replace, $body);

        // Resolve {mailchimp_status} placeholder
        $mailchimp_line = '';
        if ($mailchimp_result !== null) {
            switch ($mailchimp_result['status']) {
                case 'subscribed':
                case 'reactivated':
                case 'updated':
                    $mailchimp_line = !empty($form['mailchimp_success_text'])
                        ? $form['mailchimp_success_text']
                        : lang('form_builder_mailchimp_default_success');
                    break;
                case 'failed':
                case 'failed_rate_limit':
                case 'failed_invalid_email':
                case 'skipped_misconfigured':
                    $mailchimp_line = !empty($form['mailchimp_failure_text'])
                        ? $form['mailchimp_failure_text']
                        : lang('form_builder_mailchimp_default_failure');
                    break;
            }
        }
        $body = str_replace('{mailchimp_status}', $mailchimp_line, $body);

        $subject = !empty($form['confirmation_subject'])
            ? $form['confirmation_subject']
            : 'Thank you for your submission';

        $from_name = !empty($form['confirmation_from_name'])
            ? $form['confirmation_from_name']
            : ($this->settings['from_name'] ?? '');

        $from_email = !empty($form['confirmation_from_email'])
            ? $form['confirmation_from_email']
            : ($this->settings['from_email'] ?? '');

        return $this->sendEmail($to_email, $subject, $body, null, $from_name, $from_email);
    }

    /**
     * Send email (using EE's email class or SMTP)
     */
    private function sendEmail($to, $subject, $body, $reply_to = null, $from_name = null, $from_email = null, $attachments = [])
    {
        ee()->load->library('email');

        // Configure SMTP if enabled
        if (isset($this->settings['smtp_enabled']) && $this->settings['smtp_enabled'] === 'y') {
            $config = array(
                'protocol' => 'smtp',
                'smtp_host' => $this->settings['smtp_host'] ?? '',
                'smtp_port' => $this->settings['smtp_port'] ?? 587,
                'smtp_user' => $this->settings['smtp_username'] ?? '',
                'smtp_pass' => !empty($this->settings['smtp_password'])
                    ? ee('Encrypt')->decode($this->settings['smtp_password'])
                    : '',
                'mailtype' => 'text',
                'charset' => 'utf-8'
            );

            $config['smtp_crypto'] = (!empty($this->settings['smtp_encryption']) && $this->settings['smtp_encryption'] !== 'none')
                ? $this->settings['smtp_encryption']
                : '';

            ee()->email->initialize($config);
        }

        // Set from
        $from_name = $from_name ?: ($this->settings['from_name'] ?? ee()->config->item('webmaster_name'));
        $from_email = $from_email ?: ($this->settings['from_email'] ?? ee()->config->item('webmaster_email'));

        ee()->email->from($from_email, $from_name);
        ee()->email->to($to);
        ee()->email->subject($subject);
        ee()->email->message($body);

        if ($reply_to) {
            ee()->email->reply_to($reply_to);
        }

        // Attach files if provided
        foreach ($attachments as $filepath) {
            ee()->email->attach($filepath);
        }

        $result = ee()->email->send();

        if (!$result) {
            log_message('error', 'Form Builder: email send failed. To: ' . $to . ' | Subject: ' . $subject);
        }

        ee()->email->clear();

        return $result;
    }

    // -------------------------------------------------------------------------
    // MAILCHIMP
    // -------------------------------------------------------------------------

    private function isMailchimpFieldConfigured($field, $config)
    {
        if (empty($this->settings['mailchimp_api_key'])) {
            return false;
        }
        if (empty($config['mailchimp_list_id'])) {
            return false;
        }
        if (empty($config['mailchimp_email_field'])) {
            return false;
        }

        $list_exists = ee()->db->where('list_id', $config['mailchimp_list_id'])
            ->where('site_id', $this->site_id)
            ->count_all_results('form_builder_mailchimp_lists');
        if ($list_exists === 0) {
            return false;
        }

        $email_field_exists = ee()->db->where('form_id', $field['form_id'])
            ->where('field_name', $config['mailchimp_email_field'])
            ->where('field_type', 'email')
            ->count_all_results('form_builder_fields');
        if ($email_field_exists === 0) {
            return false;
        }

        if (!empty($config['mailchimp_merge_fields'])) {
            $form_field_names = array_column(
                ee()->db->select('field_name')->where('form_id', $field['form_id'])
                    ->get('form_builder_fields')->result_array(),
                'field_name'
            );
            $mf = $config['mailchimp_merge_fields'];
            if (is_string($mf)) {
                $pairs = array();
                foreach (preg_split('/\r\n|\r|\n/', $mf) as $line) {
                    $line = trim($line);
                    if ($line === '' || strpos($line, '=') === false) continue;
                    list($t, $f) = array_map('trim', explode('=', $line, 2));
                    $pairs[] = array('tag' => $t, 'field' => $f);
                }
            } else {
                $pairs = (array) $mf;
            }
            foreach ($pairs as $pair) {
                $rhs = $pair['field'] ?? '';
                if ($rhs !== '' && !in_array($rhs, $form_field_names, true)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function processMailchimpSubscription($form, $fields, $submission_id, $submission_data)
    {
        $result = array('status' => 'none', 'error_detail' => '', 'http_status' => 0);

        $mc_field = null;
        foreach ($fields as $f) {
            if ($f['field_type'] === 'mailchimp_subscription') {
                $mc_field = $f;
                break;
            }
        }
        if (!$mc_field) {
            return $result;
        }

        $config = !empty($mc_field['field_config'])
            ? (json_decode($mc_field['field_config'], true) ?: array())
            : array();

        if (!$this->isMailchimpFieldConfigured($mc_field, $config)) {
            $result['status'] = 'skipped_misconfigured';
            return $result;
        }

        $checked = isset($submission_data[$mc_field['field_name']])
            && $submission_data[$mc_field['field_name']]['value'] === 'y';
        if (!$checked) {
            $result['status'] = 'skipped_unchecked';
            return $result;
        }

        $email = isset($submission_data[$config['mailchimp_email_field']])
            ? trim((string) $submission_data[$config['mailchimp_email_field']]['value'])
            : '';
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $result['status'] = 'failed_invalid_email';
            return $result;
        }

        $merge_fields = $this->parseMergeFields($config['mailchimp_merge_fields'] ?? '', $submission_data);

        $tags = array();
        if (!empty($config['mailchimp_tags'])) {
            if (is_array($config['mailchimp_tags'])) {
                $tags = array_values(array_filter(array_map('trim', $config['mailchimp_tags'])));
            } else {
                $tags = array_filter(array_map('trim', explode(',', (string) $config['mailchimp_tags'])));
            }
        }

        return $this->callMailchimpSubscribe($config['mailchimp_list_id'], $email, $merge_fields, $tags);
    }

    private function parseMergeFields($mapping, $submission_data)
    {
        $result = array();

        // Normalize to array of {tag, field} pairs
        if (is_array($mapping)) {
            $pairs = $mapping;
        } elseif (is_string($mapping) && $mapping !== '') {
            $pairs = array();
            foreach (preg_split('/\r\n|\r|\n/', $mapping) as $line) {
                $line = trim($line);
                if ($line === '' || strpos($line, '=') === false) continue;
                list($t, $f) = array_map('trim', explode('=', $line, 2));
                if ($t !== '' && $f !== '') $pairs[] = array('tag' => $t, 'field' => $f);
            }
        } else {
            return $result;
        }

        foreach ($pairs as $pair) {
            $tag = $pair['tag']   ?? '';
            $fld = $pair['field'] ?? '';
            if (!preg_match('/^[A-Z0-9_]+$/', $tag)) continue;
            if (isset($submission_data[$fld]['value'])) {
                $result[$tag] = (string) $submission_data[$fld]['value'];
            }
        }
        return $result;
    }

    private function callMailchimpSubscribe($list_id, $email, $merge_fields, $tags)
    {
        $result = array('status' => 'failed', 'error_detail' => '', 'http_status' => 0);

        $api_key = !empty($this->settings['mailchimp_api_key'])
            ? trim((string) ee('Encrypt')->decode($this->settings['mailchimp_api_key']))
            : '';
        if ($api_key === '') {
            $result['error_detail'] = 'API key not configured';
            return $result;
        }

        $api_base = $this->getMailchimpApiBase($api_key);
        if ($api_base === null) {
            $result['error_detail'] = 'Invalid API key format';
            return $result;
        }

        $url     = "{$api_base}/lists/{$list_id}/members";
        $payload = array(
            'email_address' => $email,
            'status'        => 'subscribed',
            'merge_fields'  => (object) $merge_fields,
        );
        if (!empty($tags)) {
            $payload['tags'] = array_values($tags);
        }

        $response = $this->mailchimpRequest('POST', $url, $api_key, $payload);

        if ($response['http_status'] === 200) {
            $result['status'] = 'subscribed';
            return $result;
        }

        // Member exists — PUT to update/reactivate
        if ($response['http_status'] === 400
            && isset($response['body']['title'])
            && $response['body']['title'] === 'Member Exists') {

            $subscriber_hash = md5(strtolower($email));
            $put_url = "{$api_base}/lists/{$list_id}/members/{$subscriber_hash}";

            $get_response    = $this->mailchimpRequest('GET', $put_url, $api_key);
            $previous_status = ($get_response['http_status'] === 200)
                ? ($get_response['body']['status'] ?? '')
                : '';

            $put_payload = array(
                'email_address' => $email,
                'status'        => 'subscribed',
                'merge_fields'  => (object) $merge_fields,
            );
            if (!empty($tags)) {
                $put_payload['tags'] = array_values($tags);
            }
            $put_response = $this->mailchimpRequest('PUT', $put_url, $api_key, $put_payload);

            if ($put_response['http_status'] === 200) {
                $result['status'] = ($previous_status === 'unsubscribed') ? 'reactivated' : 'updated';
                return $result;
            }
            $result['error_detail'] = 'PUT failed: HTTP ' . $put_response['http_status']
                . (isset($put_response['body']['detail']) ? ': ' . $put_response['body']['detail'] : '');
            $result['http_status'] = $put_response['http_status'];
            return $result;
        }

        if ($response['http_status'] === 429) {
            $result['status']       = 'failed_rate_limit';
            $result['error_detail'] = 'Rate limited by Mailchimp';
            $result['http_status']  = 429;
            return $result;
        }

        $detail = isset($response['body']['detail']) ? $response['body']['detail'] : '';
        if (stripos($detail, 'permanently deleted') !== false) {
            $result['status']       = 'failed_permanently_deleted';
            $result['error_detail'] = $detail;
            $result['http_status']  = $response['http_status'];
            return $result;
        }

        $result['error_detail'] = 'HTTP ' . $response['http_status'] . ($detail !== '' ? ': ' . $detail : '');
        $result['http_status']  = $response['http_status'];
        return $result;
    }

    private function getMailchimpApiBase($api_key)
    {
        $api_key  = trim((string) $api_key);
        $dash_pos = strrpos($api_key, '-');
        if ($dash_pos === false || $dash_pos === strlen($api_key) - 1) {
            return null;
        }
        $dc = substr($api_key, $dash_pos + 1);
        if (!preg_match('/^[a-z]{2}\d+$/', $dc)) {
            return null;
        }
        return "https://{$dc}.api.mailchimp.com/3.0";
    }

    private function mailchimpRequest($method, $url, $api_key, $payload = null)
    {
        $ch   = curl_init($url);
        $opts = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 3,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => array(
                'Content-Type: application/json',
                'Authorization: Basic ' . base64_encode('anystring:' . $api_key),
            ),
            CURLOPT_CUSTOMREQUEST  => $method,
        );
        if ($payload !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        curl_setopt_array($ch, $opts);

        $raw_response = curl_exec($ch);
        $http_status  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error   = curl_error($ch);
        curl_close($ch);

        if ($raw_response === false) {
            log_message('error', 'Form Builder Mailchimp: cURL error: ' . $curl_error);
            return array('http_status' => 0, 'body' => null, 'curl_error' => $curl_error);
        }

        return array('http_status' => $http_status, 'body' => json_decode($raw_response, true), 'curl_error' => '');
    }

    private function sendMailchimpAlertEmail($to_email, $form, $submission_data, $submission_id, $mailchimp_result)
    {
        $subject = '[Mailchimp Alert] Subscription failure on form: ' . $form['form_label'];

        $body  = "MAILCHIMP SUBSCRIPTION FAILURE ALERT\n";
        $body .= "=====================================\n\n";
        $body .= "Form: " . $form['form_label'] . " (ID " . $form['form_id'] . ")\n";
        $body .= "Submission ID: " . $submission_id . "\n";
        $body .= "Submitted at: " . date('Y-m-d H:i:s') . "\n";
        $body .= "Status: " . $mailchimp_result['status'] . "\n";
        if (!empty($mailchimp_result['error_detail'])) {
            $body .= "Detail: " . $mailchimp_result['error_detail'] . "\n";
        }
        if (!empty($mailchimp_result['http_status'])) {
            $body .= "HTTP Status: " . $mailchimp_result['http_status'] . "\n";
        }

        $body .= "\n---\nSUBMISSION DATA\n---\n\n";
        foreach ($submission_data as $field_data) {
            if ($field_data['type'] === 'file') {
                $body .= strtoupper($field_data['label']) . ":\n[file]\n\n";
            } else {
                $body .= strtoupper($field_data['label']) . ":\n" . $field_data['value'] . "\n\n";
            }
        }

        $body .= "\nNote: the user's form submission was saved and they were served the success page. The Mailchimp subscription did NOT complete; add manually if needed.\n";

        $from_name  = $this->settings['from_name']  ?? ee()->config->item('site_name');
        $from_email = $this->settings['from_email'] ?? ee()->config->item('webmaster_email');

        return $this->sendEmail($to_email, $subject, $body, null, $from_name, $from_email);
    }

    /**
     * Validate that a redirect URL stays within this site
     */
    private function isSafeRedirect($url)
    {
        if (empty($url)) {
            return false;
        }
        static $parsed_site = null;
        if ($parsed_site === null) {
            $parsed_site = parse_url(ee()->functions->fetch_site_index());
        }
        $parsed_url = parse_url($url);

        // Allow relative URLs — but reject protocol-relative and non-HTTP scheme URLs
        if (!isset($parsed_url['host'])) {
            // Reject protocol-relative URLs like //evil.com
            if (strpos($url, '//') === 0) {
                return false;
            }
            // Reject javascript:, data:, vbscript:, and any other scheme
            if (isset($parsed_url['scheme'])) {
                return false;
            }
            return true;
        }

        // Require same host
        return isset($parsed_site['host']) && $parsed_url['host'] === $parsed_site['host'];
    }

    /**
     * Handle error redirect
     */
    private function handleError($message, $form_id = 0)
    {
        $key = $form_id ? 'form_builder_errors_' . $form_id : 'form_builder_errors';
        ee()->session->set_flashdata($key, array('general' => $message));
        if ($form_id) {
            $old_data = $_POST;
            unset($old_data['csrf_token'], $old_data['form_id'], $old_data['return']);
            foreach ($old_data as $_k => $_v) {
                if (is_array($_v)) {
                    $old_data[$_k] = implode(',', $_v);
                }
            }
            ee()->session->set_flashdata('form_builder_old_' . $form_id, $old_data);
        }
        $anchor = $form_id ? '#form-builder-' . $form_id : '';
        $referrer = ee()->input->server('HTTP_REFERER');
        if ($referrer && $this->isSafeRedirect($referrer)) {
            ee()->functions->redirect($referrer . $anchor);
        } else {
            ee()->functions->redirect(ee()->functions->fetch_site_index());
        }
    }

    /**
     * Display success message (template tag)
     *
     * {exp:form_builder:success}
     *   <p>Thank you for your submission!</p>
     * {/exp:form_builder:success}
     */
    public function success()
    {
        $form_id = (int) ee()->TMPL->fetch_param('form_id', 0);
        if (!$form_id) {
            // Fall back to looking up by name
            $form_name = ee()->TMPL->fetch_param('name', '');
            if ($form_name) {
                $row = ee()->db->select('form_id')
                    ->where('site_id', ee()->config->item('site_id'))
                    ->where('form_name', $form_name)
                    ->get('form_builder_forms')
                    ->row_array();
                $form_id = $row['form_id'] ?? 0;
            }
        }
        if (!$form_id) {
            return '';
        }
        $success = ee()->session->flashdata('form_builder_success_' . $form_id);
        if ($success) {
            return ee()->TMPL->tagdata;
        }
        return '';
    }

    /**
     * Display error messages (template tag)
     *
     * {exp:form_builder:errors}
     *   {error}
     * {/exp:form_builder:errors}
     */
    public function errors()
    {
        $form_id = (int) ee()->TMPL->fetch_param('form_id', 0);
        if (!$form_id) {
            $form_name = ee()->TMPL->fetch_param('name', '');
            if ($form_name) {
                $row = ee()->db->select('form_id')
                    ->where('site_id', ee()->config->item('site_id'))
                    ->where('form_name', $form_name)
                    ->get('form_builder_forms')
                    ->row_array();
                $form_id = $row['form_id'] ?? 0;
            }
        }
        if (!$form_id) {
            return '';
        }

        $errors = ee()->session->flashdata('form_builder_errors_' . $form_id);
        if (!$errors || !is_array($errors)) {
            return '';
        }

        $vars = array();
        foreach ($errors as $field => $message) {
            $vars[] = array(
                'field' => $field,
                'error' => $message
            );
        }

        return ee()->TMPL->parse_variables(ee()->TMPL->tagdata, $vars);
    }
}
