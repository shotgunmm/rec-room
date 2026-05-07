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
                'field_html' => $this->renderFieldHtml($field),
                'field_options' => $this->parseOptions($field['field_options'])
            );
        }

        // Check for errors in flash data
        $flash_errors = ee()->session->flashdata('form_builder_errors_' . $form['form_id']);
        $flash_old    = ee()->session->flashdata('form_builder_old_'    . $form['form_id']);

        $has_errors = !empty($flash_errors);
        $error_list = $flash_errors ?: array();

        // Parse template variables
        $vars = array(
            'form_id' => $form['form_id'],
            'form_name' => $form['form_name'],
            'form_label' => $form['form_label'],
            'action_url' => $action_url,
            'has_errors' => $has_errors,
            'errors' => $error_list,
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

        $attr_string = '';
        foreach ($form_attrs as $key => $val) {
            $attr_string .= ' ' . $key . '="' . htmlspecialchars($val, ENT_QUOTES) . '"';
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

        // Client-side validation for required checkbox/radio groups (data-required="true")
        $validation_script = '
<script>
document.addEventListener("DOMContentLoaded", function () {
    var form = document.querySelector("form[action=\"' . addslashes($action_url) . '\"]");
    if (!form) return;

    function showFormError(msg) {
        var errorDiv = form.querySelector(".form-error");
        if (errorDiv) {
            errorDiv.textContent = msg;
            var wrapper = errorDiv.parentElement;
            if (wrapper) wrapper.style.display = "";
            errorDiv.scrollIntoView({ behavior: "smooth", block: "center" });
        }
    }

    function clearFormError() {
        var errorDiv = form.querySelector(".form-error");
        if (errorDiv) {
            errorDiv.textContent = "";
            var wrapper = errorDiv.parentElement;
            if (wrapper) wrapper.style.display = "none";
        }
    }

    function validateGroups() {
        var groups = form.querySelectorAll("[data-required=\"true\"]");
        for (var i = 0; i < groups.length; i++) {
            var group = groups[i];
            var inputs = group.querySelectorAll("input[type=\"checkbox\"], input[type=\"radio\"]");
            if (inputs.length === 0) continue;
            var checked = false;
            for (var j = 0; j < inputs.length; j++) {
                if (inputs[j].checked) { checked = true; break; }
            }
            if (!checked) {
                var label = group.getAttribute("data-label") || "This field";
                showFormError(label + " is required.");
                group.scrollIntoView({ behavior: "smooth", block: "center" });
                return false;
            }
        }
        return true;
    }

    form.addEventListener("submit", function (e) {
        if (!validateGroups()) {
            e.preventDefault();
            e.stopImmediatePropagation();
            return false;
        }
        clearFormError();
    }, true);
});
</script>
';

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

        return '<form' . $attr_string . '>' . $hidden . $output . '</form>' . $validation_script . $recaptcha_script;
    }

    /**
     * Render HTML for a single field
     */
    private function renderFieldHtml($field)
    {
        $name = htmlspecialchars($field['field_name'], ENT_QUOTES);
        $required = ($field['is_required'] === 'y') ? ' required' : '';
        $label_text = nl2br(htmlspecialchars($field['field_label'], ENT_QUOTES)) . ($field['is_required'] === 'y' ? ' <span class="red">*</span>' : '');
        $confirm = $field['confirm'];
        $placeholder = htmlspecialchars($field['placeholder'] ?? '', ENT_QUOTES);
        $raw_default = $field['default_value'] ?? '';
        $default = htmlspecialchars($field['default_value'] ?? '', ENT_QUOTES);
        $css_class = htmlspecialchars($field['css_class'] ?? '', ENT_QUOTES);

        switch ($field['field_type']) {
            case 'text':
                $html = '<div class="' . $css_class . ' form-field">
                    <label class="form-label" for="' . $name . '">' . $label_text . '</label>
                    <input class="form-control" data-label="' . htmlspecialchars($field['field_label'], ENT_QUOTES) . '" id="' . $name . '" type="text" name="' . $name . '" ' . $required . ' />
                    <div class="form-text">' . $placeholder . '</div>
                </div>';
                return $html;

            case 'number':
                $html = '<div class="' . $css_class . ' form-field">
                    <label class="form-label" for="' . $name . '">' . $label_text . '</label>
                    <input class="form-control" data-label="' . htmlspecialchars($field['field_label'], ENT_QUOTES) . '" id="' . $name . '" type="number" name="' . $name . '" ' . $required . ' />
                    <div class="form-text">' . $placeholder . '</div>
                </div>';
                return $html;

            case 'email':
                $html = '<div class="' . $css_class . ' form-field">
                    <label class="form-label" for="' . $name . '">' . $label_text . '</label>
                    <input class="form-control" data-label="' . htmlspecialchars($field['field_label'], ENT_QUOTES) . '" id="' . $name . '" type="email" name="' . $name . '" ' . $required . ' />
                    <div class="form-text">' . $placeholder . '</div>
                </div>';
                if ($confirm === 'y') {
                    $confirm_label = lang('form_builder_confirm_email_label');
                    $confirm_name  = $name . '_confirm';
                    $html .= '<div class="' . $css_class . ' form-field">
                    <label class="form-label" for="' . $confirm_name . '">' . htmlspecialchars($confirm_label, ENT_QUOTES) . '</label>
                    <input class="form-control" id="' . $confirm_name . '" data-label="' . htmlspecialchars($confirm_label, ENT_QUOTES) . '" type="email" name="' . $confirm_name . '" ' . $required . ' data-confirm-email="true" />
                    <div class="form-text">' . htmlspecialchars($confirm_label, ENT_QUOTES) . '</div>
                </div>';
                }
                return $html;

            case 'url':
                $html = '<div class="' . $css_class . ' form-field">
                    <label class="form-label" for="' . $name . '">' . $label_text . '</label>
                    <input class="form-control" data-label="' . htmlspecialchars($field['field_label'], ENT_QUOTES) . '" id="' . $name . '" type="url" name="' . $name . '" ' . $required . ' />
                    <div class="form-text">' . $placeholder . '</div>
                </div>';
                return $html;

            case 'phone':
                $html = '<div class="' . $css_class . ' form-field">
                    <label class="form-label" for="' . $name . '">' . $label_text . '</label>
                    <input class="form-control" data-label="' . htmlspecialchars($field['field_label'], ENT_QUOTES) . '" id="' . $name . '" type="tel" name="' . $name . '" ' . $required . ' />
                     <div class="form-text">' . $placeholder . '</div>
                </div>';
                return $html;

            case 'textarea':
                $html = '<div class="' . $css_class . ' form-field"><label class="form-label" for="' . $name . '">' . $label_text . '</label>
                    <textarea class="form-control" data-label="' . htmlspecialchars($field['field_label'], ENT_QUOTES) . '" id="' . $name . '" name="' . $name . '" ' . $required . ' ></textarea>
                </div>';
                return $html;

            case 'select':
                $options = $this->parseOptions($field['field_options']);
                $placeholder_option = $placeholder != ''
                    ? '<option value="" disabled selected>' . htmlspecialchars($placeholder, ENT_QUOTES) . '</option>'
                    : '<option value="" selected></option>';
                $html = '<div class="' . $css_class . ' form-field">
                    <label class="form-label" for="' . $name . '">' . $label_text . '</label>
                    <select name="' . $name . '" id="' . $name . '" data-label="' . htmlspecialchars($field['field_label'], ENT_QUOTES) . '" class="form-control"' . $required . '>
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
                </div>';
                return $html;

            case 'radio':
                $options = $this->parseOptions($field['field_options']);
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
                if ($placeholder != '') {
                    $html .= '<div class="form-text">' . htmlspecialchars($placeholder, ENT_QUOTES) . '</div>';
                }
                $html .= '</div>';
                return $html;

            case 'checkbox':
                $options = $this->parseOptions($field['field_options']);
                if (empty($options)) {
                    // Single checkbox
                    $checked = ($default === 'y' || $default === '1') ? ' checked' : '';
                    $data_required = ($field['is_required'] === 'y') ? ' data-required="true"' : '';
                    return sprintf(
                        '<div class="' . $css_class . ' form-field"' . $data_required . '  data-label="' . htmlspecialchars($field['field_label'], ENT_QUOTES) . '"><div class="form-check d-flex mt-md-3">
                <input class="form-check-input" type="checkbox" name="%s" id="%s" value="%s"%s />
                <label class="form-check-label p" for="%s">%s</label>
              </div></div>',
                        $name,
                        $name,
                        $name,
                        $checked,
                        $name,
                        nl2br(htmlspecialchars($field['field_label'], ENT_QUOTES)) . ($field['is_required'] === 'y' ? ' <span class="red">*</span>' : '')
                    );
                }
                // Multiple checkboxes
                $data_required = ($field['is_required'] === 'y') ? ' data-required="true"' : '';
                $html = '<div class="' . $css_class . ' form-field checkbox-group"' . $data_required . ' data-label="' . htmlspecialchars($field['field_label'], ENT_QUOTES) . '">
                    <label class="form-label">' . $label_text . '</label>
                    <div class="form-list d-flex flex-column">';
                foreach ($options as $i => $opt) {
                    $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($opt['value']));
                    $option_id = $name . '_' . $i . '_' . $slug;
                    $html .= sprintf(
                        '<label style="width: fit-content"><input type="checkbox" name="%s[]" value="%s"> %s</label>',
                        $name,
                        htmlspecialchars($opt['value'], ENT_QUOTES),
                        htmlspecialchars($opt['label'], ENT_QUOTES)
                    );
                }
                return $html . '</div></div>';

            case 'date':
                $html = '<div class="' . $css_class . ' form-field">
                    <label class="form-label" for="' . $name . '">' . $label_text . '</label>
                    <input class="form-control" data-label="' . htmlspecialchars($field['field_label'], ENT_QUOTES) . '" id="' . $name . '" type="date" name="' . $name . '" ' . $required . ' />
                    <div class="form-text">' . $placeholder . '</div>
                </div>';
                return $html;

            case 'time':
                $html = '<div class="' . $css_class . ' form-field">
                    <label class="form-label" for="' . $name . '">' . $label_text . '</label>
                    <input class="form-control" data-label="' . htmlspecialchars($field['field_label'], ENT_QUOTES) . '" id="' . $name . '" type="time" name="' . $name . '" ' . $required . ' />
                    <div class="form-text">' . $placeholder . '</div>
                </div>';
                return $html;

            case 'file':
                $accept = '';

                if (!empty($field['file_types'])) {
                    $types = array_map('trim', explode(',', $field['file_types']));
                    $accept = ' accept=".' . implode(',.', $types) . '"';
                }
                $html = '<div class="' . $css_class . ' form-field">
                    <div class="upload-file">
                    <div class="form-text">' . $placeholder . '</div>
                    <div class="input-group mt-4">
                        <input class="form-control" data-label="' . htmlspecialchars($field['field_label'], ENT_QUOTES) . '" id="' . $name . '" name="' . $name . '[]" type="file" multiple ' . $accept . ' />
                        <div id="feedback_' . $name . '" class=""></div>
                        <div class="file-list" id="fileList_' . $name . '"></div>
                        <label class="input-group-text" for="' . $name . '">Upload</label>
                    </div>
                    </div>
                </div>';
                return $html;

            case 'warning':
                $html = '<div class="' . $css_class . ' form-field"><div class="form-label fw-bold form-control ps-0 mb-0">' . htmlspecialchars($field['field_label'], ENT_QUOTES) . '</div><div class="form-text">' . $placeholder . '</div>
                </div>';
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
    private function parseOptions($options_string)
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

        // Rate limit: max 5 submissions per IP per form per 10 minutes
        $ip       = ee()->input->ip_address();
        $rate_key = 'form_builder_rate_' . md5($ip . '_' . $form_id);
        $attempts = ee()->cache->get($rate_key, Cache::LOCAL_SCOPE);
        $attempts = ($attempts !== false) ? (int) $attempts : 0;

        if ($attempts >= 5) {
            $this->handleError('Too many submissions. Please wait a few minutes and try again.', $form_id);
            return;
        }

        // Verify reCAPTCHA if enabled (skip on localhost for local dev)
        $is_localhost = in_array(
            ee()->input->server('SERVER_NAME'),
            ['localhost', '127.0.0.1', '::1']
        );
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
            if (in_array($field['field_type'], ['warning', 'header'])) {
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
            }else {
                $value = ee()->input->post($field_name);
            }

            // Validate required fields
            if ($field['is_required'] === 'y' && $field['field_type'] !== 'file' && $field['field_type'] !== 'checkbox') {
                if ($value === null || $value === '' || $value === false) {
                    $errors[$field_name] = $field['field_label'] . ' is required';
                }
            }

            // Validate email format
            if ($field['field_type'] === 'email' && !empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$field_name] = $field['field_label'] . ' must be a valid email address';
            }

            // Validate URL format
            if ($field['field_type'] === 'url' && !empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                $errors[$field_name] = $field['field_label'] . ' must be a valid URL';
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
            ee()->session->set_flashdata('form_builder_errors_' . $form_id, $errors);
            $old_data = ee()->input->post();
            unset($old_data['csrf_token'], $old_data['form_id'], $old_data['return']);
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

        ee()->db->insert('form_builder_submissions', $submission);
        $submission_id = ee()->db->insert_id();

        // Increment rate limit counter only on a successful save
        ee()->cache->save($rate_key, $attempts + 1, 600, Cache::LOCAL_SCOPE);

        // Send notification email
        $email_sent = $this->sendNotificationEmail($form, $submission_data, $reply_to_email);
        if ($email_sent) {
            ee()->db->where('submission_id', $submission_id)
                ->update('form_builder_submissions', array('email_sent' => 'y'));
        }

        // Send confirmation email
        if ($form['send_confirmation'] === 'y' && $reply_to_email) {
            $confirmation_sent = $this->sendConfirmationEmail($form, $submission_data, $reply_to_email);
            if ($confirmation_sent) {
                ee()->db->where('submission_id', $submission_id)
                    ->update('form_builder_submissions', array('confirmation_sent' => 'y'));
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
    private function sendNotificationEmail($form, $submission_data, $reply_to = null)
    {
        if (empty($form['recipient_email'])) {
            return false;
        }

        $body = "New submission from: " . $form['form_label'] . "\n\n";
        $attachments = [];

        foreach ($submission_data as $field_name => $field_data) {
            if (!empty($field_data['value']) && $field_data['type'] === 'file') {
                $body .= $field_data['label'] . ": [Attached File]\n";
                // Collect file paths for attachment
                $files = explode(',', $field_data['value']);
                foreach ($files as $file) {
                    $file = trim($file);
                    $filepath = FCPATH . 'uploads/form_builder/' . $file;
                    if (file_exists($filepath)) {
                        $attachments[] = $filepath;
                    }
                }
            } else {
                $body .= $field_data['label'] . ": " . $field_data['value'] . "\n";
            }
        }

        $body .= "\n---\nSubmitted at: " . date('Y-m-d H:i:s');

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
    private function sendConfirmationEmail($form, $submission_data, $to_email)
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

            if (!empty($this->settings['smtp_encryption']) && $this->settings['smtp_encryption'] !== 'none') {
                $config['smtp_crypto'] = $this->settings['smtp_encryption'];
            }

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
        $referrer = ee()->input->server('HTTP_REFERER');
        if ($referrer && $this->isSafeRedirect($referrer)) {
            ee()->functions->redirect($referrer);
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
