<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

require_once __DIR__ . '/mod.form_builder.php';

class Form_builder_mcp
{
    private $base_url;
    private $site_id;
    private $field_types = array(
        'text'                   => 'Text',
        'textarea'               => 'Textarea',
        'email'                  => 'Email',
        'url'                    => 'URL',
        'phone'                  => 'Telephone',
        'number'                 => 'Number',
        'date'                   => 'Date',
        'time'                   => 'Time',
        'select'                 => 'Select Dropdown',
        'radio'                  => 'Radio Buttons',
        'checkbox'               => 'Checkbox',
        'file'                   => 'File Upload',
        'mailchimp_subscription' => 'Mailchimp Subscription',
        'warning'                => 'Warning Text',
    );

    const FIELD_TYPE_GROUPS = array(
        'text_like' => array(
            'label_key' => 'form_builder_group_text_like',
            'types'     => array('text', 'textarea', 'email', 'url', 'phone', 'number', 'date', 'time'),
        ),
        'choice_like' => array(
            'label_key' => 'form_builder_group_choice_like',
            'types'     => array('select', 'radio', 'checkbox'),
        ),
        'binary' => array(
            'label_key' => 'form_builder_group_binary',
            'types'     => array('file'),
        ),
        'mailchimp' => array(
            'label_key' => 'form_builder_group_mailchimp',
            'types'     => array('mailchimp_subscription'),
        ),
        'display' => array(
            'label_key' => 'form_builder_group_display',
            'types'     => array('warning'),
        ),
    );

    private function getFieldTypeGroup($field_type)
    {
        foreach (self::FIELD_TYPE_GROUPS as $key => $group) {
            if (in_array($field_type, $group['types'], true)) {
                return $key;
            }
        }
        return null;
    }

    private function getTypesInGroup($group_key)
    {
        return isset(self::FIELD_TYPE_GROUPS[$group_key]) ? self::FIELD_TYPE_GROUPS[$group_key]['types'] : array();
    }

    private function isFieldTypeInGroup($field_type, $group_key)
    {
        return in_array($field_type, $this->getTypesInGroup($group_key), true);
    }

    private function areTypesInSameGroup($type_a, $type_b)
    {
        $group = $this->getFieldTypeGroup($type_a);
        return $group !== null && $group === $this->getFieldTypeGroup($type_b);
    }

    public function __construct()
    {
        ee()->lang->loadfile('form_builder');
        $this->base_url = ee('CP/URL', 'addons/settings/form_builder');
        $this->site_id = ee()->config->item('site_id');

        // Build sidebar
        $this->buildSidebar();
    }

    private function buildSidebar()
    {
        $sidebar = ee('CP/Sidebar')->make();

        // Forms section
        $forms_header = $sidebar->addHeader(lang('form_builder_forms'));
        $forms_list = $forms_header->addBasicList();
        $forms_list->addItem(lang('form_builder_all_forms'), ee('CP/URL', 'addons/settings/form_builder'));
        $forms_list->addItem(lang('form_builder_create_form'), ee('CP/URL', 'addons/settings/form_builder/edit_form'));

        // Submissions section
        $submissions_header = $sidebar->addHeader(lang('form_builder_submissions'));
        $submissions_list = $submissions_header->addBasicList();
        $submissions_list->addItem(lang('form_builder_all_submissions'), ee('CP/URL', 'addons/settings/form_builder/submissions'));

        // Settings section
        $settings_header = $sidebar->addHeader(lang('form_builder_settings'));
        $settings_list = $settings_header->addBasicList();
        $settings_list->addItem(lang('form_builder_email_settings'), ee('CP/URL', 'addons/settings/form_builder/settings'));
        $settings_list->addItem(lang('form_builder_add_recaptcha'), ee('CP/URL', 'addons/settings/form_builder/add_recaptcha'));
        $settings_list->addItem(lang('form_builder_mailchimp_settings'), ee('CP/URL', 'addons/settings/form_builder/mailchimp'));
    }

    // -------------------------------------------------------------------------
    // FORMS
    // -------------------------------------------------------------------------

    public function index()
    {
        // Get forms with submission counts in a single query
        $forms = ee()->db->select('f.*, COUNT(s.submission_id) as submission_count')
            ->from('form_builder_forms f')
            ->join('form_builder_submissions s', 's.form_id = f.form_id', 'left')
            ->where('f.site_id', $this->site_id)
            ->group_by('f.form_id')
            ->order_by('f.form_name', 'asc')
            ->get()
            ->result_array();

        return array(
            'heading' => lang('form_builder_module_name'),
            'breadcrumb' => array(
                $this->base_url->compile() => lang('form_builder_module_name')
            ),
            'body' => ee('View')->make('form_builder:forms_list')->render(array(
                'forms' => $forms,
                'base_url' => $this->base_url
            ))
        );
    }

    public function edit_form($form_id = 0)
    {
        $is_new = ($form_id == 0);
        $validation_error = false;

        // Load email fields for this form — used for both reply_to validation (POST) and dropdown (GET/re-render)
        $fields = array();
        if (!$is_new) {
            $fields = ee()->db->where('form_id', (int)$form_id)
                ->where('field_type', 'email')
                ->not_like('field_name', '_confirm', 'after')
                ->get('form_builder_fields')
                ->result_array();
        }

        // Default form data (used for re-render on validation failure)
        $form = array(
            'form_name' => '',
            'form_label' => '',
            'recipient_email' => '',
            'reply_to_field' => '',
            'email_subject' => '',
            'success_redirect' => '',
            'send_confirmation' => 'n',
            'confirmation_template' => '',
            'confirmation_subject' => '',
            'confirmation_from_name' => '',
            'confirmation_from_email' => '',
            'is_active' => 'y',
            'mailchimp_success_text' => '',
            'mailchimp_failure_text' => ''
        );

        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = array(
                'site_id' => $this->site_id,
                'form_name' => ee()->input->post('form_name'),
                'form_label' => ee()->input->post('form_label'),
                'recipient_email' => ee()->input->post('recipient_email'),
                'reply_to_field' => ee()->input->post('reply_to_field'),
                'email_subject' => ee()->input->post('email_subject'),
                'success_redirect' => ee()->input->post('success_redirect'),
                'send_confirmation' => ee()->input->post('send_confirmation') ?: 'n',
                'confirmation_template' => ee()->input->post('confirmation_template'),
                'confirmation_subject' => ee()->input->post('confirmation_subject'),
                'confirmation_from_name' => ee()->input->post('confirmation_from_name'),
                'confirmation_from_email' => ee()->input->post('confirmation_from_email'),
                'is_active' => ee()->input->post('is_active') ?: 'y',
                'mailchimp_success_text' => ee()->input->post('mailchimp_success_text'),
                'mailchimp_failure_text' => ee()->input->post('mailchimp_failure_text'),
                'updated_at' => date('Y-m-d H:i:s')
            );

            // Sanitize form_name and form_label
            $data['form_name'] = preg_replace('/[^a-z0-9_-]/', '', strtolower($data['form_name']));
            $data['form_label'] = trim((string) $data['form_label']);

            // Validate reply_to_field — must be empty or a current email field on this form
            $valid_reply_to = array_column($fields, 'field_name');
            if ($data['reply_to_field'] !== '' && !in_array($data['reply_to_field'], $valid_reply_to, true)) {
                $data['reply_to_field'] = '';
            }

            if (empty($data['form_label'])) {
                ee('CP/Alert')->makeInline('shared-form')
                    ->asIssue()
                    ->withTitle('Form label is required.')
                    ->now();
                $validation_error = true;
            } elseif (empty($data['form_name'])) {
                ee('CP/Alert')->makeInline('shared-form')
                    ->asIssue()
                    ->withTitle('Form name is required and may only contain lowercase letters, numbers, hyphens, and underscores.')
                    ->now();
                $validation_error = true;
            }

            if (!$validation_error) {
                // Enforce unique form_name within this site
                $dupe_check = ee()->db->where('site_id', $this->site_id)
                    ->where('form_name', $data['form_name']);
                if (!$is_new) {
                    $dupe_check->where('form_id !=', (int)$form_id);
                }
                if ($dupe_check->count_all_results('form_builder_forms') > 0) {
                    ee('CP/Alert')->makeInline('shared-form')
                        ->asIssue()
                        ->withTitle('A form with that name already exists.')
                        ->now();
                    $validation_error = true;
                }
            }

            if (!$validation_error) {
                // Validate recipient_email format if provided
                $recipient = trim((string) $data['recipient_email']);
                if (!empty($recipient)) {
                    $addresses = array_filter(array_map('trim', explode(',', $recipient)));
                    $invalid = [];
                    foreach ($addresses as $addr) {
                        if (!filter_var($addr, FILTER_VALIDATE_EMAIL)) {
                            $invalid[] = $addr;
                        }
                    }
                    if (!empty($invalid)) {
                        ee('CP/Alert')->makeInline('shared-form')
                            ->asIssue()
                            ->withTitle('One or more recipient email addresses are invalid: ' . implode(', ', $invalid))
                            ->now();
                        $validation_error = true;
                    }
                }
            }

            if (!$validation_error) {
                $confirm_from = trim((string) $data['confirmation_from_email']);
                if (!empty($confirm_from) && !filter_var($confirm_from, FILTER_VALIDATE_EMAIL)) {
                    ee('CP/Alert')->makeInline('shared-form')
                        ->asIssue()
                        ->withTitle('Confirmation from email is not a valid email address.')
                        ->now();
                    $validation_error = true;
                }
            }

            if (!$validation_error) {
                if ($is_new) {
                    $data['created_at'] = date('Y-m-d H:i:s');
                    ee()->db->insert('form_builder_forms', $data);
                    $form_id = ee()->db->insert_id();
                    ee('CP/Alert')->makeInline('shared-form')
                        ->asSuccess()
                        ->withTitle(lang('form_builder_form_created'))
                        ->defer();
                } else {
                    ee()->db->where('form_id', (int)$form_id)
                        ->where('site_id', $this->site_id)
                        ->update('form_builder_forms', $data);
                    ee('CP/Alert')->makeInline('shared-form')
                        ->asSuccess()
                        ->withTitle(lang('form_builder_form_updated'))
                        ->defer();
                }

                ee()->functions->redirect(ee('CP/URL', 'addons/settings/form_builder/edit_fields/' . $form_id));
            }

            if ($validation_error) {
                // Merge only display-relevant fields into $form for re-render
                $display_keys = ['form_name', 'form_label', 'recipient_email', 'reply_to_field',
                                 'email_subject', 'success_redirect', 'send_confirmation',
                                 'confirmation_template', 'confirmation_subject',
                                 'confirmation_from_name', 'confirmation_from_email', 'is_active',
                                 'mailchimp_success_text', 'mailchimp_failure_text'];
                $form = array_merge($form, array_intersect_key($data, array_flip($display_keys)));
            }
        }

        if (!$validation_error && !$is_new) {
            $result = ee()->db->where('form_id', (int)$form_id)
                ->where('site_id', $this->site_id)
                ->get('form_builder_forms')
                ->row_array();
            if ($result) {
                $form = $result;
            }
            // $fields already loaded above
        }

        // Build reply-to options
        $reply_to_options = array('' => lang('form_builder_select_field'));
        foreach ($fields as $field) {
            $reply_to_options[$field['field_name']] = $field['field_label'];
        }

        $vars = array();
        $vars['sections'] = array(
            array(
                array(
                    'title' => lang('form_builder_form_name'),
                    'desc' => lang('form_builder_form_name_desc'),
                    'fields' => array(
                        'form_name' => array(
                            'type' => 'text',
                            'value' => $form['form_name'],
                            'required' => true
                        )
                    )
                ),
                array(
                    'title' => lang('form_builder_form_label'),
                    'desc' => lang('form_builder_form_label_desc'),
                    'fields' => array(
                        'form_label' => array(
                            'type' => 'text',
                            'value' => $form['form_label'],
                            'required' => true
                        )
                    )
                ),
                array(
                    'title' => lang('form_builder_is_active'),
                    'fields' => array(
                        'is_active' => array(
                            'type' => 'yes_no',
                            'value' => $form['is_active']
                        )
                    )
                )
            ),
            lang('form_builder_email_routing') => array(
                array(
                    'title' => lang('form_builder_recipient_email'),
                    'desc' => lang('form_builder_recipient_email_desc'),
                    'fields' => array(
                        'recipient_email' => array(
                            'type' => 'text',
                            'value' => $form['recipient_email']
                        )
                    )
                ),
                array(
                    'title' => lang('form_builder_reply_to_field'),
                    'desc' => lang('form_builder_reply_to_field_desc'),
                    'fields' => array(
                        'reply_to_field' => array(
                            'type' => 'select',
                            'choices' => $reply_to_options,
                            'value' => $form['reply_to_field']
                        )
                    )
                ),
                array(
                    'title' => lang('form_builder_email_subject'),
                    'fields' => array(
                        'email_subject' => array(
                            'type' => 'text',
                            'value' => $form['email_subject']
                        )
                    )
                ),
                array(
                    'title' => lang('form_builder_success_redirect'),
                    'desc' => lang('form_builder_success_redirect_desc'),
                    'fields' => array(
                        'success_redirect' => array(
                            'type' => 'text',
                            'value' => $form['success_redirect']
                        )
                    )
                )
            ),
            lang('form_builder_confirmation_email') => array(
                array(
                    'title' => lang('form_builder_send_confirmation'),
                    'desc' => lang('form_builder_send_confirmation_desc'),
                    'fields' => array(
                        'send_confirmation' => array(
                            'type' => 'yes_no',
                            'value' => $form['send_confirmation']
                        )
                    )
                ),
                array(
                    'title' => lang('form_builder_confirmation_subject'),
                    'fields' => array(
                        'confirmation_subject' => array(
                            'type' => 'text',
                            'value' => $form['confirmation_subject']
                        )
                    )
                ),
                array(
                    'title' => lang('form_builder_confirmation_from_name'),
                    'fields' => array(
                        'confirmation_from_name' => array(
                            'type' => 'text',
                            'value' => $form['confirmation_from_name']
                        )
                    )
                ),
                array(
                    'title' => lang('form_builder_confirmation_from_email'),
                    'fields' => array(
                        'confirmation_from_email' => array(
                            'type' => 'text',
                            'value' => $form['confirmation_from_email']
                        )
                    )
                ),
                array(
                    'title' => lang('form_builder_confirmation_template'),
                    'desc' => lang('form_builder_confirmation_template_desc'),
                    'fields' => array(
                        'confirmation_template' => array(
                            'type' => 'textarea',
                            'value' => $form['confirmation_template']
                        )
                    )
                )
            ),
            lang('form_builder_mailchimp_settings') => array(
                array(
                    'title' => lang('form_builder_mailchimp_success_text'),
                    'desc' => lang('form_builder_mailchimp_success_text_desc'),
                    'fields' => array(
                        'mailchimp_success_text' => array(
                            'type' => 'textarea',
                            'value' => $form['mailchimp_success_text'] ?? ''
                        )
                    )
                ),
                array(
                    'title' => lang('form_builder_mailchimp_failure_text'),
                    'desc' => lang('form_builder_mailchimp_failure_text_desc'),
                    'fields' => array(
                        'mailchimp_failure_text' => array(
                            'type' => 'textarea',
                            'value' => $form['mailchimp_failure_text'] ?? ''
                        )
                    )
                )
            )
        );

        $vars['base_url'] = $is_new
            ? ee('CP/URL', 'addons/settings/form_builder/edit_form')
            : ee('CP/URL', 'addons/settings/form_builder/edit_form/' . $form_id);
        $vars['save_btn_text'] = $is_new ? lang('form_builder_create_form') : lang('form_builder_save_form');
        $vars['save_btn_text_working'] = lang('form_builder_saving');

        if (!$is_new) {
            $edit_fields_url = ee('CP/URL', 'addons/settings/form_builder/edit_fields/' . $form_id)->compile();
            $vars['buttons'] = array(
                array(
                    'href'  => $edit_fields_url,
                    'text'  => 'form_builder_edit_fields',
                    'attrs' => 'style="margin-left:0"',
                ),
                array(
                    'name'    => 'submit',
                    'type'    => 'submit',
                    'value'   => 'save',
                    'text'    => 'form_builder_save_form',
                    'working' => 'form_builder_saving',
                ),
            );
        }
        $vars['cp_page_title'] = $is_new ? lang('form_builder_create_form') : lang('form_builder_edit_form');

        return array(
            'heading' => $vars['cp_page_title'],
            'breadcrumb' => array(
                $this->base_url->compile() => lang('form_builder_module_name')
            ),
            'body' => ee('View')->make('ee:_shared/form')->render($vars)
        );
    }

    public function delete_form($form_id = 0)
    {
        if ($form_id > 0) {
            // Verify ownership before any deletes
            $form_check = ee()->db->select('form_id')
                ->where('form_id', (int)$form_id)
                ->where('site_id', $this->site_id)
                ->get('form_builder_forms')
                ->row_array();

            if ($form_check) {
                // Delete uploaded files from disk before removing submission records
                $submissions = ee()->db->select('submission_data')
                    ->where('form_id', (int)$form_id)
                    ->get('form_builder_submissions')
                    ->result_array();

                foreach ($submissions as $sub) {
                    $data = json_decode($sub['submission_data'], true);
                    if (is_array($data)) {
                        foreach ($data as $field_name => $field_data) {
                            if (
                                isset($field_data['type']) &&
                                $field_data['type'] === 'file' &&
                                !empty($field_data['value'])
                            ) {
                                $files = explode(',', $field_data['value']);
                                foreach ($files as $file) {
                                    $file = trim($file);
                                    if ($file !== '') {
                                        $filepath = FCPATH . 'uploads/form_builder/' . basename($file);
                                        if (file_exists($filepath)) {
                                            @unlink($filepath);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                ee()->db->where('form_id', (int)$form_id)->delete('form_builder_fields');
                ee()->db->where('form_id', (int)$form_id)->delete('form_builder_submissions');
                ee()->db->where('form_id', (int)$form_id)
                    ->where('site_id', $this->site_id)
                    ->delete('form_builder_forms');

                ee('CP/Alert')->makeInline('shared-form')
                    ->asSuccess()
                    ->withTitle(lang('form_builder_form_deleted'))
                    ->defer();
            }
        }

        ee()->functions->redirect($this->base_url);
    }

    // -------------------------------------------------------------------------
    // FIELDS
    // -------------------------------------------------------------------------

    public function edit_fields($form_id = 0)
    {
        if ($form_id == 0) {
            ee()->functions->redirect($this->base_url);
        }

        $form = ee()->db->where('form_id', (int)$form_id)
            ->where('site_id', $this->site_id)
            ->get('form_builder_forms')
            ->row_array();
        if (!$form) {
            ee()->functions->redirect($this->base_url);
        }

        $fields = ee()->db->where('form_id', $form_id)
            ->order_by('field_order', 'asc')
            ->get('form_builder_fields')
            ->result_array();

        // Pre-compute misconfiguration flag for Mailchimp fields
        $api_key_row = ee()->db->select('setting_value')
            ->where('site_id', $this->site_id)
            ->where('setting_key', 'mailchimp_api_key')
            ->get('form_builder_settings')
            ->row('setting_value');
        $has_api_key = !empty($api_key_row);

        foreach ($fields as &$f) {
            if ($f['field_type'] === 'mailchimp_subscription') {
                $config = !empty($f['field_config'])
                    ? (json_decode($f['field_config'], true) ?: array())
                    : array();
                $f['is_misconfigured'] = !$this->isMailchimpFieldConfiguredMcp($f, $config, (int) $form_id, $has_api_key);
            } else {
                $f['is_misconfigured'] = false;
            }
        }
        unset($f);

        return array(
            'heading' => lang('form_builder_edit_fields') . ': ' . $form['form_label'],
            'breadcrumb' => array(
                $this->base_url->compile() => lang('form_builder_module_name'),
                ee('CP/URL', 'addons/settings/form_builder/edit_form/' . $form_id)->compile() => $form['form_label']
            ),
            'body' => ee('View')->make('form_builder:fields_list')->render(array(
                'form'              => $form,
                'fields'            => $fields,
                'field_types'       => $this->field_types,
                'field_type_groups' => self::FIELD_TYPE_GROUPS,
                'base_url'          => $this->base_url
            ))
        );
    }

    public function edit_field($form_id = 0, $field_id = 0, $field_type_segment = '')
    {
        if ($form_id == 0) {
            ee()->functions->redirect($this->base_url);
        }

        $form = ee()->db->where('form_id', (int)$form_id)
            ->where('site_id', $this->site_id)
            ->get('form_builder_forms')
            ->row_array();
        if (!$form) {
            ee()->functions->redirect($this->base_url);
        }

        $is_new = ($field_id == 0);

        // Initialize $field defaults BEFORE the POST block so it is always defined
        $field = array(
            'field_name'                => '',
            'field_header'              => '',
            'field_label'               => '',
            'field_type'                => 'text',
            'field_options'             => '',
            'placeholder'               => '',
            'default_value'             => '',
            'is_required'               => 'n',
            'confirm'                   => 'n',
            'css_class'                 => '',
            'file_types'                => 'pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,gif,webp,zip',
            'max_file_size'             => '',
            'field_config'              => null,
            'mailchimp_list_id'         => '',
            'mailchimp_email_field'     => '',
            'mailchimp_default_checked' => 'n',
            'mailchimp_merge_fields'    => '',
            'mailchimp_tags'            => ''
        );
        // Load existing field data early so the POST block can use it for the type guard
        // and validation-error re-render without a second query
        $existing_field = null;
        if (!$is_new) {
            $result = ee()->db->where('field_id', (int) $field_id)
                ->where('form_id', (int) $form_id)
                ->get('form_builder_fields')
                ->row_array();
            if ($result) {
                $existing_field = $result;
                $field = array_merge($field, $existing_field);
                if ($field['field_type'] === 'mailchimp_subscription' && !empty($field['field_config'])) {
                    $config = json_decode($field['field_config'], true) ?: array();
                    $field = array_merge($field, $config);
                }
            }
        }

        // Accept field_type path segment for new fields (set by the Add Field picker)
        if ($is_new && $field_type_segment && array_key_exists($field_type_segment, $this->field_types)) {
            $field['field_type'] = $field_type_segment;
        }

        // Accept field_type query string for existing fields (set by the JS type-change reload)
        if (!$is_new && $existing_field !== null) {
            $query_type = ee()->input->get('field_type');
            if ($query_type
                && array_key_exists($query_type, $this->field_types)
                && $this->areTypesInSameGroup($query_type, $existing_field['field_type'])) {
                $field['field_type'] = $query_type;
            }
        }

        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = array(
                'form_id' => (int)$form_id,
                'field_name' => ee()->input->post('field_name'),
                'field_header' => ee()->input->post('field_header'),
                'field_label' => trim((string) ee()->input->post('field_label')),
                'field_type' => array_key_exists(ee()->input->post('field_type'), $this->field_types)
                    ? ee()->input->post('field_type')
                    : (array_key_exists($field_type_segment, $this->field_types)
                        ? $field_type_segment
                        : 'text'),
                'field_options' => ee()->input->post('field_options'),
                'placeholder' => ee()->input->post('placeholder'),
                'default_value' => (function() {
                    $val = ee()->input->post('default_value');
                    if (is_array($val)) {
                        return implode(',', array_map('trim', $val));
                    }
                    return (string) $val;
                })(),
                'is_required' => ee()->input->post('is_required') ?: 'n',
                'confirm' => ee()->input->post('confirm') ?: 'n',
                'css_class' => ee()->input->post('css_class'),
                'file_types' => ee()->input->post('file_types'),
                'max_file_size' => ee()->input->post('max_file_size') ?: null
            );

            // Server-side type-change guard: reject cross-group type changes
            if (!$is_new && $existing_field !== null) {
                if (!$this->areTypesInSameGroup($data['field_type'], $existing_field['field_type'])) {
                    $data['field_type'] = $existing_field['field_type'];
                }
            }

            // Sanitize field_name
            $data['field_name'] = preg_replace('/[^a-z0-9_]/', '', strtolower($data['field_name']));

            // Validate required text fields after sanitization
            if (empty($data['field_label'])) {
                ee('CP/Alert')->makeInline('shared-form')
                    ->asIssue()
                    ->withTitle('Field label is required.')
                    ->now();
                $field = array_merge($field, $data);
                // Fall through to re-render with user's values
            } elseif (empty($data['field_name'])) {
                ee('CP/Alert')->makeInline('shared-form')
                    ->asIssue()
                    ->withTitle('Field name is required and may only contain lowercase letters, numbers, and underscores.')
                    ->now();
                $field = array_merge($field, $data);
                // Fall through to re-render with user's values
            } elseif ($data['field_type'] === 'file' && empty(trim((string) $data['file_types']))) {
                ee('CP/Alert')->makeInline('shared-form')
                    ->asIssue()
                    ->withTitle('Allowed File Types is required for file upload fields.')
                    ->now();
                $field = array_merge($field, $data);
                // Fall through to re-render with user's values
            } else {
                // Enforce unique field_name within this form
                $dupe_check = ee()->db->where('form_id', (int)$form_id)
                    ->where('field_name', $data['field_name']);

                if (!$is_new) {
                    $dupe_check->where('field_id !=', (int)$field_id);
                }

                if ($dupe_check->count_all_results('form_builder_fields') > 0) {
                    ee('CP/Alert')->makeInline('shared-form')
                        ->asIssue()
                        ->withTitle('A field with that Field Name already exists in this form.')
                        ->now();
                    $field = array_merge($field, $data);
                    // Fall through to re-render
                } else {
                    // Handle field_config for Mailchimp fields
                    if ($data['field_type'] === 'mailchimp_subscription') {
                        // Build merge pairs from POST arrays
                        $tags_arr   = (array) (ee()->input->post('mailchimp_merge_tag')   ?: array());
                        $fields_arr = (array) (ee()->input->post('mailchimp_merge_field') ?: array());
                        $merge_pairs = array();
                        $count = min(count($tags_arr), count($fields_arr));
                        for ($i = 0; $i < $count; $i++) {
                            $tag = strtoupper(trim((string) $tags_arr[$i]));
                            $fld = trim((string) $fields_arr[$i]);
                            if ($tag !== '' && $fld !== '') {
                                $merge_pairs[] = array('tag' => $tag, 'field' => $fld);
                            }
                        }
                        $sub_tags_raw = (array) (ee()->input->post('mailchimp_tags') ?: array());
                        $sub_tags     = array_values(array_filter(array_map('trim', $sub_tags_raw)));
                        $mc_config = array(
                            'mailchimp_list_id'         => trim((string) ee()->input->post('mailchimp_list_id')),
                            'mailchimp_email_field'     => trim((string) ee()->input->post('mailchimp_email_field')),
                            'mailchimp_default_checked' => ee()->input->post('mailchimp_default_checked') === 'y' ? 'y' : 'n',
                            'mailchimp_merge_fields'    => $merge_pairs,
                            'mailchimp_tags'            => $sub_tags,
                        );
                        $data['field_config'] = json_encode($mc_config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        $data['field_options'] = '';

                        // Validate merge field mapping
                        if (!empty($merge_pairs)) {
                            $merge_errors = $this->validateMergeFieldMapping(
                                $merge_pairs,
                                (int) $form_id,
                                $is_new ? null : (int) $field_id
                            );
                            if (!empty($merge_errors)) {
                                ee('CP/Alert')->makeInline('shared-form')
                                    ->asIssue()
                                    ->withTitle('Merge Field Mapping has errors: ' . implode('; ', $merge_errors))
                                    ->now();
                                $field = array_merge($field, $data, $mc_config);
                                goto field_render;
                            }
                        }
                    } elseif ($data['field_type'] === 'warning') {
                        $warning_color = trim((string)(ee()->input->post('warning_color') ?: ''));
                        if ($warning_color && !preg_match('/^#[0-9a-fA-F]{3,6}$/', $warning_color)) {
                            $warning_color = '';
                        }
                        $data['field_config'] = json_encode(array('warning_color' => $warning_color), JSON_HEX_TAG);
                    } else {
                        $data['field_config'] = null;
                    }

                    // Clear stale sub-field data when type change drops those inputs from the form
                    $submitted_group = $this->getFieldTypeGroup($data['field_type']);
                    if ($submitted_group !== 'choice_like') {
                        $data['field_options'] = '';
                    }
                    if ($submitted_group !== 'text_like') {
                        $data['placeholder'] = '';
                    }
                    if (!in_array($submitted_group, array('text_like', 'choice_like'), true)) {
                        $data['default_value'] = '';
                    }
                    if ($data['field_type'] !== 'file') {
                        $data['file_types']    = '';
                        $data['max_file_size'] = null;
                    }
                    // Required toggle is hidden in the UI for these groups; force is_required to 'n'
                    // regardless of POST. Defends against forged POSTs and makes the intent explicit.
                    if (in_array($submitted_group, array('mailchimp', 'display'), true)) {
                        $data['is_required'] = 'n';
                    }

                    $save_action = ee()->input->post('submit');

                    if ($is_new) {
                        $max_order = ee()->db->select_max('field_order')
                            ->where('form_id', $form_id)
                            ->get('form_builder_fields')
                            ->row('field_order');
                        $data['field_order'] = ($max_order !== null) ? $max_order + 1 : 0;

                        ee()->db->insert('form_builder_fields', $data);
                        $new_field_id = ee()->db->insert_id();
                        ee('CP/Alert')->makeInline('shared-form')
                            ->asSuccess()
                            ->withTitle(lang('form_builder_field_created'))
                            ->defer();

                        if ($save_action === 'save_and_continue') {
                            ee()->functions->redirect(ee('CP/URL', 'addons/settings/form_builder/edit_field/' . $form_id . '/' . $new_field_id));
                        } else {
                            ee()->functions->redirect(ee('CP/URL', 'addons/settings/form_builder/edit_fields/' . $form_id));
                        }
                    } else {
                        ee()->db->where('field_id', (int)$field_id)
                            ->where('form_id', (int)$form_id)
                            ->update('form_builder_fields', $data);
                        ee('CP/Alert')->makeInline('shared-form')
                            ->asSuccess()
                            ->withTitle(lang('form_builder_field_updated'))
                            ->defer();

                        if ($save_action === 'save_and_continue') {
                            ee()->functions->redirect(ee('CP/URL', 'addons/settings/form_builder/edit_field/' . $form_id . '/' . $field_id));
                        } else {
                            ee()->functions->redirect(ee('CP/URL', 'addons/settings/form_builder/edit_fields/' . $form_id));
                        }
                    }
                }
            }
        }

        field_render:

        // Determine current type and group
        $current_type  = $field['field_type'];
        $current_group = $this->getFieldTypeGroup($current_type);

        // Resolve warning_color for the warning field settings UI
        $field['warning_color'] = '';
        if ($current_type === 'warning') {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $field['warning_color'] = trim((string)(ee()->input->post('warning_color') ?: ''));
            } elseif (!empty($field['field_config'])) {
                $cfg = json_decode($field['field_config'], true) ?: array();
                $field['warning_color'] = $cfg['warning_color'] ?? '';
            }
        }

        // Initialize Mailchimp variables to safe defaults
        $mc_list_options      = array();
        $mc_email_options     = array();
        $mc_selected_list     = '';
        $mc_merge_tags        = array();
        $mc_sub_tags          = array();
        $all_fields_for_merge = array();
        $existing_merge_pairs = array();

        // Load Mailchimp data only when the section will render
        if ($current_type === 'mailchimp_subscription') {
            $mc_lists = ee()->db->where('site_id', $this->site_id)
                ->order_by('list_name', 'asc')
                ->get('form_builder_mailchimp_lists')
                ->result_array();

            $mc_list_options = array('' => '-- Select List --');
            foreach ($mc_lists as $list) {
                $mc_list_options[$list['list_id']] = htmlspecialchars($list['list_name'], ENT_QUOTES)
                    . ' (' . (int) $list['member_count'] . ')';
            }
            if (count($mc_list_options) === 1) {
                $mc_list_options = array('' => 'No lists cached. Configure Mailchimp settings and click Refresh Lists.');
            }

            $email_fields_for_mc = ee()->db->where('form_id', (int) $form_id)
                ->where('field_type', 'email')
                ->not_like('field_name', '_confirm', 'after')
                ->get('form_builder_fields')
                ->result_array();

            $mc_email_options = array('' => '-- Select Email Field --');
            foreach ($email_fields_for_mc as $ef) {
                $mc_email_options[$ef['field_name']] = htmlspecialchars($ef['field_label'], ENT_QUOTES);
            }
            if (count($mc_email_options) === 1) {
                $mc_email_options = array('' => 'No email fields available — add an email field to this form first.');
            }

            $mc_selected_list = $field['mailchimp_list_id'] ?? '';

            if (!empty($mc_selected_list)) {
                $mc_merge_tags = ee()->db->select('tag, name')
                    ->where('list_id', $mc_selected_list)
                    ->where('site_id', $this->site_id)
                    ->order_by('name', 'asc')
                    ->get('form_builder_mailchimp_merge_tags')
                    ->result_array();

                $mc_sub_tags = ee()->db->select('name')
                    ->where('list_id', $mc_selected_list)
                    ->where('site_id', $this->site_id)
                    ->order_by('name', 'asc')
                    ->get('form_builder_mailchimp_sub_tags')
                    ->result_array();
            }

            $all_fields_for_merge_q = ee()->db->select('field_name, field_label')
                ->where('form_id', (int) $form_id)
                ->where_not_in('field_type', array('warning', 'mailchimp_subscription'));
            if (!$is_new && $field_id > 0) {
                $all_fields_for_merge_q->where('field_id !=', (int) $field_id);
            }
            $all_fields_for_merge = $all_fields_for_merge_q->order_by('field_order', 'asc')
                ->get('form_builder_fields')
                ->result_array();

            // Normalise existing merge pairs (handle both array and legacy string format)
            $raw_mf = $field['mailchimp_merge_fields'] ?? '';
            if (is_array($raw_mf)) {
                $existing_merge_pairs = $raw_mf;
            } elseif (is_string($raw_mf) && $raw_mf !== '') {
                foreach (preg_split('/\r\n|\r|\n/', $raw_mf) as $line) {
                    $line = trim($line);
                    if ($line !== '' && strpos($line, '=') !== false) {
                        list($t, $f) = array_map('trim', explode('=', $line, 2));
                        if ($t !== '' && $f !== '') {
                            $existing_merge_pairs[] = array('tag' => $t, 'field' => $f);
                        }
                    }
                }
            }
        }

        // Build field_type display: restricted dropdown for multi-member groups, static label otherwise
        $same_group_types = $this->getTypesInGroup($current_group);
        if (!$is_new && count($same_group_types) > 1) {
            $restricted_choices = array();
            foreach ($same_group_types as $t) {
                $restricted_choices[$t] = $this->field_types[$t];
            }
            $type_field_def  = array('field_type' => array(
                'type'    => 'select',
                'choices' => $restricted_choices,
                'value'   => $current_type,
            ));
            $type_field_desc = lang('form_builder_field_type_change_desc');
        } else {
            $type_field_def  = array(
                'field_type_display' => array(
                    'type'    => 'html',
                    'content' => '<strong>' . htmlspecialchars($this->field_types[$current_type] ?? $current_type, ENT_QUOTES) . '</strong>',
                ),
                'field_type' => array(
                    'type'  => 'hidden',
                    'value' => $current_type,
                ),
            );
            $type_field_desc = (!$is_new) ? lang('form_builder_field_type_locked_desc') : '';
        }

        // Build top section (always-visible fields)
        $top_section = array(
            array(
                'title'  => lang('form_builder_field_header'),
                'desc'   => lang('form_builder_field_header_desc'),
                'fields' => array('field_header' => array('type' => 'text', 'value' => $field['field_header'])),
            ),
            array(
                'title'  => lang('form_builder_field_label'),
                'desc'   => lang('form_builder_field_label_desc'),
                'fields' => array('field_label' => array(
                    'type'     => 'textarea',
                    'value'    => $field['field_label'],
                    'required' => true,
                    'attrs'    => 'rows="2" style="min-height:0;"',
                )),
            ),
            array(
                'title'  => lang('form_builder_field_name'),
                'desc'   => lang('form_builder_field_name_desc'),
                'fields' => array('field_name' => array(
                    'type'     => 'text',
                    'value'    => $field['field_name'],
                    'required' => true,
                )),
            ),
            array(
                'title'  => lang('form_builder_field_type'),
                'desc'   => $type_field_desc,
                'fields' => $type_field_def,
            ),
        );

        // Required toggle: hidden for mailchimp and display groups
        if (!in_array($current_group, array('mailchimp', 'display'), true)) {
            $top_section[] = array(
                'title'  => lang('form_builder_is_required'),
                'fields' => array('is_required' => array('type' => 'yes_no', 'value' => $field['is_required'])),
            );
        }

        // Confirm Email toggle: email type only
        if ($current_type === 'email') {
            $top_section[] = array(
                'title'  => lang('form_builder_confirm'),
                'fields' => array('confirm' => array('type' => 'yes_no', 'value' => $field['confirm'] ?? 'n')),
            );
        }

        // Build Field Settings section incrementally
        $field_settings = array();

        if ($current_group === 'text_like') {
            if ($current_type === 'date' || $current_type === 'time') {
                $picker_type = $current_type;
                $lang_attr   = ($current_type === 'time') ? ' lang="en-US"' : '';
                $field_settings[] = array(
                    'title'  => lang('form_builder_placeholder'),
                    'fields' => array('placeholder' => array(
                        'type'    => 'html',
                        'content' => '<input type="' . $picker_type . '"' . $lang_attr . ' name="placeholder" value="' . htmlspecialchars($field['placeholder'], ENT_QUOTES) . '">',
                    )),
                );
                $field_settings[] = array(
                    'title'  => lang('form_builder_default_value'),
                    'fields' => array('default_value' => array(
                        'type'    => 'html',
                        'content' => '<input type="' . $picker_type . '"' . $lang_attr . ' name="default_value" value="' . htmlspecialchars($field['default_value'], ENT_QUOTES) . '">',
                    )),
                );
            } else {
                $field_settings[] = array(
                    'title'  => lang('form_builder_placeholder'),
                    'fields' => array('placeholder' => array('type' => 'text', 'value' => $field['placeholder'])),
                );
            }
        }

        if (in_array($current_group, array('text_like', 'choice_like'), true)) {
            if ($current_group === 'choice_like') {
                $parsed_options = Form_builder::parseOptions($field['field_options'] ?? '');
                if (!empty($parsed_options) && !$is_new) {
                    if ($current_type === 'checkbox') {
                        // Multi-checkbox: allow selecting multiple default values
                        $current_defaults = array_map('trim', explode(',', $field['default_value'] ?? ''));
                        $checkboxes_html = '';
                        foreach ($parsed_options as $opt) {
                            $is_checked = in_array($opt['value'], $current_defaults, true) ? ' checked' : '';
                            $checkboxes_html .= '<label style="display:block;margin-bottom:5px;">'
                                . '<input type="checkbox" name="default_value[]" value="'
                                . htmlspecialchars($opt['value'], ENT_QUOTES) . '"' . $is_checked . '> '
                                . htmlspecialchars($opt['label'], ENT_QUOTES)
                                . '</label>';
                        }
                        $field_settings[] = array(
                            'title'  => lang('form_builder_default_value'),
                            'fields' => array('default_value' => array(
                                'type'    => 'html',
                                'content' => $checkboxes_html,
                            )),
                        );
                    } else {
                        // Select / radio: single default value
                        $options_choices = array('' => lang('form_builder_no_default'));
                        foreach ($parsed_options as $opt) {
                            $options_choices[$opt['value']] = $opt['label'];
                        }
                        $field_settings[] = array(
                            'title'  => lang('form_builder_default_value'),
                            'fields' => array('default_value' => array(
                                'type'    => 'select',
                                'choices' => $options_choices,
                                'value'   => $field['default_value'],
                            )),
                        );
                    }
                } else {
                    $field_settings[] = array(
                        'title'  => lang('form_builder_default_value'),
                        'desc'   => lang('form_builder_default_value_choice_desc'),
                        'fields' => array('default_value' => array('type' => 'text', 'value' => $field['default_value'])),
                    );
                }
            } elseif ($current_type !== 'date' && $current_type !== 'time') {
                $field_settings[] = array(
                    'title'  => lang('form_builder_default_value'),
                    'fields' => array('default_value' => array('type' => 'text', 'value' => $field['default_value'])),
                );
            }
        }

        $field_settings[] = array(
            'title'  => lang('form_builder_css_class'),
            'fields' => array('css_class' => array('type' => 'text', 'value' => $field['css_class'])),
        );

        if ($current_type === 'warning') {
            $color_val = htmlspecialchars($field['warning_color'] ?? '', ENT_QUOTES);
            $field_settings[] = array(
                'title'  => lang('form_builder_warning_color'),
                'desc'   => lang('form_builder_warning_color_desc'),
                'fields' => array('warning_color' => array(
                    'type'    => 'html',
                    'content' => '<div style="display:flex;align-items:center;gap:8px;">'
                        . '<input type="color" name="warning_color_picker" id="warning_color_picker" value="' . ($color_val ?: '#333333') . '">'
                        . '<input type="text" name="warning_color" id="warning_color_text" value="' . $color_val . '" placeholder="e.g. #cc0000" maxlength="20" style="width:120px;">'
                        . '</div>'
                        . '<script>(function(){'
                        . 'var p=document.getElementById("warning_color_picker"),t=document.getElementById("warning_color_text");'
                        . 'if(t.value)p.value=t.value;'
                        . 'p.addEventListener("input",function(){t.value=p.value;});'
                        . 't.addEventListener("input",function(){if(/^#[0-9a-fA-F]{3,6}$/.test(t.value))p.value=t.value;});'
                        . '})()</script>',
                )),
            );
        }

        if ($current_group === 'choice_like') {
            $field_settings[] = array(
                'title'  => lang('form_builder_field_options'),
                'desc'   => lang('form_builder_field_options_desc'),
                'fields' => array('field_options' => array('type' => 'textarea', 'value' => $field['field_options'])),
            );
        }

        $vars = array();
        $vars['sections'] = array($top_section);
        $vars['sections'][lang('form_builder_field_settings')] = $field_settings;

        // File Settings section: file type only
        if ($current_type === 'file') {
            if (empty($field['file_types'])) {
                $field['file_types'] = 'pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,gif,webp,zip';
            }
            $vars['sections'][lang('form_builder_file_settings')] = array(
                array(
                    'title'  => lang('form_builder_file_types'),
                    'desc'   => lang('form_builder_file_types_desc'),
                    'fields' => array('file_types' => array('type' => 'text', 'value' => $field['file_types'], 'required' => true)),
                ),
                array(
                    'title'  => lang('form_builder_max_file_size'),
                    'desc'   => lang('form_builder_max_file_size_desc'),
                    'fields' => array('max_file_size' => array('type' => 'text', 'value' => $field['max_file_size'])),
                ),
            );
        }

        // Mailchimp Settings section: mailchimp_subscription type only
        if ($current_type === 'mailchimp_subscription') {
            $vars['sections'][lang('form_builder_mailchimp_settings')] = array(
                array(
                    'title'  => lang('form_builder_mailchimp_list_id'),
                    'fields' => array('mailchimp_list_id_ui' => array(
                        'type'    => 'html',
                        'content' => $this->buildListSelectHtml($mc_list_options, $field['mailchimp_list_id'] ?? ''),
                    )),
                ),
                array(
                    'title'  => lang('form_builder_mailchimp_email_field'),
                    'desc'   => lang('form_builder_mailchimp_email_field_desc'),
                    'fields' => array('mailchimp_email_field' => array(
                        'type'    => 'select',
                        'choices' => $mc_email_options,
                        'value'   => $field['mailchimp_email_field'] ?? '',
                    )),
                ),
                array(
                    'title'  => lang('form_builder_mailchimp_default_checked'),
                    'desc'   => lang('form_builder_mailchimp_default_checked_desc'),
                    'fields' => array('mailchimp_default_checked' => array(
                        'type'  => 'yes_no',
                        'value' => $field['mailchimp_default_checked'] ?? 'n',
                    )),
                ),
                array(
                    'title'  => lang('form_builder_mailchimp_merge_fields'),
                    'desc'   => lang('form_builder_mailchimp_merge_fields_desc'),
                    'fields' => array('mailchimp_merge_fields_ui' => array(
                        'type'    => 'html',
                        'content' => $this->buildMergeFieldsHtml(
                            $existing_merge_pairs,
                            $mc_merge_tags,
                            $all_fields_for_merge,
                            $mc_selected_list
                        ),
                    )),
                ),
                array(
                    'title'  => lang('form_builder_mailchimp_tags'),
                    'desc'   => lang('form_builder_mailchimp_tags_desc'),
                    'fields' => array('mailchimp_tags_ui' => array(
                        'type'    => 'html',
                        'content' => $this->buildTagsHtml(
                            $field['mailchimp_tags'] ?? '',
                            $mc_sub_tags,
                            $mc_selected_list
                        ),
                    )),
                ),
            );
        }

        $vars['base_url'] = $is_new
            ? ee('CP/URL', 'addons/settings/form_builder/edit_field/' . $form_id . '/0/' . $current_type)
            : ee('CP/URL', 'addons/settings/form_builder/edit_field/' . $form_id . '/' . $field_id);
        $vars['cp_page_title']         = $is_new ? lang('form_builder_add_field') : lang('form_builder_edit_field');
        $vars['save_btn_text']         = $is_new ? lang('form_builder_add_field') : lang('form_builder_save_field');
        $vars['save_btn_text_working'] = lang('form_builder_saving');

        $vars['buttons'] = array(
            array(
                'name'    => 'submit',
                'type'    => 'submit',
                'value'   => 'save_and_continue',
                'text'    => 'form_builder_save_and_continue',
                'working' => 'form_builder_saving',
            ),
            array(
                'name'    => 'submit',
                'type'    => 'submit',
                'value'   => 'save',
                'text'    => $is_new ? 'form_builder_add_field' : 'form_builder_save_field',
                'working' => 'form_builder_saving',
            ),
        );

        $js_path   = PATH_THIRD . 'form_builder/views/edit_field.js';
        $page_body = '<style>input[type=time]{display:block;width:100%;padding:8px 15px;font-size:1rem;line-height:1.6;color:var(--ee-input-color);background-color:var(--ee-input-bg);background-image:none;transition:border-color 200ms ease,box-shadow 200ms ease;border:1px solid var(--ee-input-border);border-radius:5px;box-shadow:0 1px 2px 0 var(--ee-shadow-input);}input[type=time]:focus{border-color:var(--ee-input-border-focus,#6b6eed);outline:0;box-shadow:0 0 0 3px var(--ee-input-focus-shadow,rgba(107,110,237,.15));}</style>' . "\n";
        $page_body .= ee('View')->make('ee:_shared/form')->render($vars);
        if (file_exists($js_path)) {
            $page_body .= "\n<script>\n" . file_get_contents($js_path) . "\n</script>\n";
        }

        return array(
            'heading'    => $vars['cp_page_title'],
            'breadcrumb' => array(
                $this->base_url->compile() => lang('form_builder_module_name'),
                ee('CP/URL', 'addons/settings/form_builder/edit_fields/' . $form_id)->compile() => $form['form_label']
            ),
            'body' => $page_body,
        );
    }

    public function delete_field($form_id = 0, $field_id = 0)
    {
        if ($field_id > 0) {
            // Verify the field belongs to a form owned by this site
            $field_check = ee()->db->select('f.form_id, ff.field_name')
                ->from('form_builder_fields ff')
                ->join('form_builder_forms f', 'f.form_id = ff.form_id')
                ->where('ff.field_id', (int)$field_id)
                ->where('f.site_id', $this->site_id)
                ->get()
                ->row_array();

            if ($field_check) {
                ee()->db->where('field_id', (int)$field_id)->delete('form_builder_fields');

                // Clear reply_to_field on any form that referenced this deleted field
                ee()->db->where('form_id', (int)$field_check['form_id'])
                    ->where('reply_to_field', $field_check['field_name'])
                    ->update('form_builder_forms', array('reply_to_field' => ''));

                ee('CP/Alert')->makeInline('shared-form')
                    ->asSuccess()
                    ->withTitle(lang('form_builder_field_deleted'))
                    ->defer();
            }
        }

        ee()->functions->redirect(ee('CP/URL', 'addons/settings/form_builder/edit_fields/' . $form_id));
    }

    public function reorder_fields()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $fields = ee()->input->post('fields');
            if (is_array($fields)) {
                // Get all field IDs that belong to this site to validate against
                $site_field_ids = ee()->db->select('ff.field_id')
                    ->from('form_builder_fields ff')
                    ->join('form_builder_forms f', 'f.form_id = ff.form_id')
                    ->where('f.site_id', $this->site_id)
                    ->get()
                    ->result_array();

                $valid_ids = array_map('intval', array_column($site_field_ids, 'field_id'));

                foreach ($fields as $order => $field_id) {
                    $field_id = (int) $field_id;
                    if (in_array($field_id, $valid_ids, true)) {
                        ee()->db->where('field_id', $field_id)
                            ->update('form_builder_fields', array('field_order' => $order));
                    }
                }
            }
            echo json_encode(array('success' => true));
            exit;
        }
    }

    // -------------------------------------------------------------------------
    // SUBMISSIONS
    // -------------------------------------------------------------------------

    public function submissions($form_id = 0)
    {
        $form_id = (int)(ee()->input->get('filter_data') ?: $form_id);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ee()->input->post('form_id')) {
            $filter_data = ee()->input->post('form_id');
            $redirect_url = ee('CP/URL', 'addons/settings/form_builder/submissions');
            $qs = ['filter_data' => $filter_data];
            if (!empty($_GET['sort_col'])) $qs['sort_col'] = $_GET['sort_col'];
            if (!empty($_GET['sort_dir'])) $qs['sort_dir'] = $_GET['sort_dir'];
            $redirect_url->addQueryStringVariables($qs);
            ee()->functions->redirect($redirect_url);
            return;
        }

        $forms = ee()->db->where('site_id', $this->site_id)
            ->order_by('form_name', 'asc')
            ->get('form_builder_forms')
            ->result_array();

        $form_options = array('' => lang('form_builder_all_forms'));
        foreach ($forms as $f) {
            $form_options[$f['form_id']] = $f['form_label'];
        }

        // Add pagination to prevent timeout with large datasets
        $per_page = 50;
        $page = (int) ee()->input->get('page') ?: 1;
        $offset = ($page - 1) * $per_page;

        $db_sort_cols = ['submitted_at', 'form_id', 'status', 'email_sent'];

        // Validate that the requested form_id belongs to the current site
        if ($form_id > 0) {
            $form_check = ee()->db->select('form_id')
                ->where('form_id', $form_id)
                ->where('site_id', $this->site_id)
                ->get('form_builder_forms')
                ->row_array();
            if (!$form_check) {
                $form_id = 0; // Reset — treat as "all forms" view, deny structure exposure
            }
        }

        // When a specific form is selected, get its field columns from the form definition
        $form_fields = [];
        if ($form_id > 0) {
            $fields = ee()->db->select('field_name, field_label')
                ->where('form_id', $form_id)
                ->where_not_in('field_type', ['warning'])
                ->order_by('field_order', 'asc')
                ->get('form_builder_fields')
                ->result_array();
            foreach ($fields as $field) {
                $form_fields[$field['field_name']] = $field['field_label'];
            }
        }

        $allowed_sort_cols = array_merge($db_sort_cols, array_keys($form_fields));
        $sort_col = isset($_GET['sort_col']) ? $_GET['sort_col'] : '';
        $sort_col = in_array($sort_col, $allowed_sort_cols) ? $sort_col : 'submitted_at';
        $sort_dir = (isset($_GET['sort_dir']) && $_GET['sort_dir'] === 'asc') ? 'asc' : 'desc';

        // Pre-compute sort URLs and arrows for the view — avoids function definitions in view files
        $all_sort_cols = array_merge($db_sort_cols, array_keys($form_fields));
        $sort_urls     = array();
        $sort_arrows   = array();

        foreach ($all_sort_cols as $col) {
            $is_date = in_array($col, array('submitted_at'));
            if ($sort_col === $col) {
                $next_dir          = ($sort_dir === 'asc') ? 'desc' : 'asc';
                $sort_arrows[$col] = ($sort_dir === 'asc') ? ' &#9650;' : ' &#9660;';
            } else {
                $next_dir          = $is_date ? 'desc' : 'asc';
                $sort_arrows[$col] = '';
            }
            $sort_url = ee('CP/URL', 'addons/settings/form_builder/submissions');
            $sort_params = array('sort_col' => $col, 'sort_dir' => $next_dir);
            if ($form_id) {
                $sort_params['filter_data'] = $form_id;
            }
            $sort_url->addQueryStringVariables($sort_params);
            $sort_urls[$col] = $sort_url->compile();
        }

        // Count query (count_all_results always resets, so build separately)
        ee()->db->where('site_id', $this->site_id);
        if ($form_id > 0) {
            ee()->db->where('form_id', $form_id);
        }
        $total_count = ee()->db->count_all_results('form_builder_submissions');

        // Results query
        $table   = ee()->db->dbprefix . 'form_builder_submissions';
        $dir_sql = ($sort_dir === 'asc') ? 'ASC' : 'DESC';

        if (in_array($sort_col, $db_sort_cols)) {
            $order_sql = "LOWER(`$sort_col`) $dir_sql";
        } else {
            $safe_key = preg_replace('/[^a-zA-Z0-9_]/', '', $sort_col);
            $extracted = "TRIM(LOWER(JSON_UNQUOTE(JSON_EXTRACT(submission_data, '$.$safe_key.value'))))";
            $order_sql = "ISNULL($extracted) $dir_sql, $extracted $dir_sql";
        }

        $where_sql = 'site_id = ' . (int) $this->site_id;
        if ($form_id > 0) {
            $where_sql .= ' AND form_id = ' . (int) $form_id;
        }

        $sql = "SELECT * FROM `$table` WHERE $where_sql ORDER BY $order_sql LIMIT " . (int)$per_page . " OFFSET " . (int)$offset;
        $submissions = ee()->db->query($sql)->result_array();

        // Decode submission data and get form names
        foreach ($submissions as &$sub) {
            $sub['submission_data'] = json_decode($sub['submission_data'], true) ?: array();
            $sub['form_label'] = $form_options[$sub['form_id']] ?? 'Unknown';
        }

        return array(
            'heading' => lang('form_builder_submissions'),
            'breadcrumb' => array(
                $this->base_url->compile() => lang('form_builder_module_name')
            ),
            'body' => ee('View')->make('form_builder:submissions_list')->render(array(
                'submissions' => $submissions,
                'form_options' => $form_options,
                'current_form' => $form_id,
                'form_fields' => $form_fields,
                'base_url' => $this->base_url,
                'sort_col' => $sort_col,
                'sort_dir' => $sort_dir,
                'sort_urls'   => $sort_urls,
                'sort_arrows' => $sort_arrows,
                'pagination' => array(
                    'total' => $total_count,
                    'per_page' => $per_page,
                    'current_page' => $page,
                    'total_pages' => max(1, ceil($total_count / $per_page))
                )
            ))
        );
    }

    public function view_submission($submission_id = 0)
    {
        if ($submission_id == 0) {
            ee()->functions->redirect(ee('CP/URL', 'addons/settings/form_builder/submissions'));
        }

        $submission = ee()->db->where('submission_id', (int)$submission_id)
            ->where('site_id', $this->site_id)
            ->get('form_builder_submissions')
            ->row_array();

        if (!$submission) {
            ee()->functions->redirect(ee('CP/URL', 'addons/settings/form_builder/submissions'));
        }

        $form = ee()->db->where('form_id', $submission['form_id'])
            ->where('site_id', $this->site_id)
            ->get('form_builder_forms')
            ->row_array();

        if (!$form) {
            ee()->functions->redirect(ee('CP/URL', 'addons/settings/form_builder/submissions'));
        }

        $fields = ee()->db->where('form_id', $submission['form_id'])
            ->order_by('field_order', 'asc')
            ->get('form_builder_fields')
            ->result_array();

        $submission['submission_data'] = json_decode($submission['submission_data'], true) ?: array();

        // Mark as read if new
        if ($submission['status'] === 'new') {
            ee()->db->where('submission_id', (int)$submission_id)
                ->update('form_builder_submissions', array('status' => 'read'));
        }

        return array(
            'heading' => lang('form_builder_view_submission'),
            'breadcrumb' => array(
                $this->base_url->compile() => lang('form_builder_module_name'),
                ee('CP/URL', 'addons/settings/form_builder/submissions')->compile() => lang('form_builder_submissions')
            ),
            'body' => ee('View')->make('form_builder:submission_view')->render(array(
                'submission' => $submission,
                'form' => $form,
                'fields' => $fields,
                'base_url' => $this->base_url
            ))
        );
    }

    public function delete_submission($submission_id = 0)
    {
        if ($submission_id > 0) {
            // Fetch submission before deleting so we can clean up uploaded files
            $submission = ee()->db->where('submission_id', (int)$submission_id)
                ->where('site_id', $this->site_id)
                ->get('form_builder_submissions')
                ->row_array();

            if ($submission) {
                // Delete any uploaded files associated with this submission
                $data = json_decode($submission['submission_data'], true);
                if (is_array($data)) {
                    foreach ($data as $field_name => $field_data) {
                        if (
                            isset($field_data['type']) &&
                            $field_data['type'] === 'file' &&
                            !empty($field_data['value'])
                        ) {
                            $files = explode(',', $field_data['value']);
                            foreach ($files as $file) {
                                $file = trim($file);
                                if ($file !== '') {
                                    $filepath = FCPATH . 'uploads/form_builder/' . basename($file);
                                    if (file_exists($filepath)) {
                                        @unlink($filepath);
                                    }
                                }
                            }
                        }
                    }
                }

                ee()->db->where('submission_id', (int)$submission_id)
                    ->where('site_id', $this->site_id)
                    ->delete('form_builder_submissions');

                ee('CP/Alert')->makeInline('shared-form')
                    ->asSuccess()
                    ->withTitle(lang('form_builder_submission_deleted'))
                    ->defer();
            }
        }

        ee()->functions->redirect(ee('CP/URL', 'addons/settings/form_builder/submissions'));
    }

    // -------------------------------------------------------------------------
    // SETTINGS
    // -------------------------------------------------------------------------

    public function settings()
    {
        $save_error      = false;
        $posted_settings = array();

        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $posted_settings = array(
                'smtp_enabled' => ee()->input->post('smtp_enabled') ?: 'n',
                'smtp_host' => ee()->input->post('smtp_host'),
                'smtp_port' => ee()->input->post('smtp_port'),
                'smtp_username' => ee()->input->post('smtp_username'),
                'smtp_password' => ee()->input->post('smtp_password'),
                'smtp_encryption' => in_array(ee()->input->post('smtp_encryption'), ['none', 'tls', 'ssl'], true)
                    ? ee()->input->post('smtp_encryption')
                    : 'tls',
                'from_name' => ee()->input->post('from_name'),
                'from_email' => ee()->input->post('from_email')
            );

            $from_email = trim((string) $posted_settings['from_email']);
            if (!empty($from_email) && !filter_var($from_email, FILTER_VALIDATE_EMAIL)) {
                ee('CP/Alert')->makeInline('shared-form')
                    ->asIssue()
                    ->withTitle('From email is not a valid email address.')
                    ->now();
                $save_error = true;
            }

            if (!$save_error) {
                // Encode SMTP password before storing
                if (!empty($posted_settings['smtp_password'])) {
                    $posted_settings['smtp_password'] = ee('Encrypt')->encode($posted_settings['smtp_password']);
                }

                // Delete existing settings for this site and re-insert — simpler than per-key upsert
                // If the admin left the password blank, exclude it from the delete so the existing value is preserved
                $keys_to_delete = array_keys($posted_settings);
                if (empty($posted_settings['smtp_password'])) {
                    $keys_to_delete = array_diff($keys_to_delete, ['smtp_password']);
                }

                ee()->db->where('site_id', $this->site_id)
                    ->where_in('setting_key', $keys_to_delete)
                    ->delete('form_builder_settings');

                foreach ($posted_settings as $key => $value) {
                    // If the admin left the password blank, do not overwrite the existing stored value
                    if ($key === 'smtp_password' && $value === '') {
                        continue;
                    }
                    ee()->db->insert('form_builder_settings', array(
                        'site_id'       => $this->site_id,
                        'setting_key'   => $key,
                        'setting_value' => $value
                    ));
                }

                ee('CP/Alert')->makeInline('shared-form')
                    ->asSuccess()
                    ->withTitle(lang('form_builder_settings_saved'))
                    ->defer();

                ee()->functions->redirect(ee('CP/URL', 'addons/settings/form_builder/settings'));
            }
            // If $save_error is true, fall through to the GET-path render below
        }

        // Load settings
        $settings = array();
        $results = ee()->db->where('site_id', $this->site_id)
            ->get('form_builder_settings')
            ->result_array();
        foreach ($results as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        // Defaults
        $defaults = array(
            'smtp_enabled' => 'n',
            'smtp_host' => '',
            'smtp_port' => '587',
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_encryption' => 'tls',
            'from_name' => '',
            'from_email' => ''
        );
        $settings = array_merge($defaults, $settings);

        // Snapshot raw DB state for placeholder, then blank password so it is never rendered into the form
        $settings_from_db = $settings;
        $settings['smtp_password'] = '';

        // If we just had a save error, overlay submitted values so the user sees what they typed
        if ($save_error) {
            $merge = $posted_settings;
            unset($merge['smtp_password']); // never merge plaintext password into rendered settings
            $settings = array_merge($settings, $merge);
        }

        $vars = array();
        $vars['sections'] = array(
            lang('form_builder_default_sender') => array(
                array(
                    'title' => lang('form_builder_from_name'),
                    'fields' => array(
                        'from_name' => array(
                            'type' => 'text',
                            'value' => $settings['from_name']
                        )
                    )
                ),
                array(
                    'title' => lang('form_builder_from_email'),
                    'fields' => array(
                        'from_email' => array(
                            'type' => 'text',
                            'value' => $settings['from_email']
                        )
                    )
                )
            ),
            lang('form_builder_smtp_settings') => array(
                array(
                    'title' => lang('form_builder_smtp_enabled'),
                    'desc' => lang('form_builder_smtp_enabled_desc'),
                    'fields' => array(
                        'smtp_enabled' => array(
                            'type' => 'yes_no',
                            'value' => $settings['smtp_enabled']
                        )
                    )
                ),
                array(
                    'title' => lang('form_builder_smtp_host'),
                    'fields' => array(
                        'smtp_host' => array(
                            'type' => 'text',
                            'value' => $settings['smtp_host']
                        )
                    )
                ),
                array(
                    'title' => lang('form_builder_smtp_port'),
                    'fields' => array(
                        'smtp_port' => array(
                            'type' => 'text',
                            'value' => $settings['smtp_port']
                        )
                    )
                ),
                array(
                    'title' => lang('form_builder_smtp_username'),
                    'fields' => array(
                        'smtp_username' => array(
                            'type' => 'text',
                            'value' => $settings['smtp_username']
                        )
                    )
                ),
                array(
                    'title' => lang('form_builder_smtp_password'),
                    'fields' => array(
                        'smtp_password' => array(
                            'type'        => 'password',
                            'value'       => '',
                            'placeholder' => !empty($settings_from_db['smtp_password']) ? '(saved — leave blank to keep current)' : ''
                        )
                    )
                ),
                array(
                    'title' => lang('form_builder_smtp_encryption'),
                    'fields' => array(
                        'smtp_encryption' => array(
                            'type' => 'select',
                            'choices' => array(
                                'none' => lang('form_builder_none'),
                                'tls' => 'TLS',
                                'ssl' => 'SSL'
                            ),
                            'value' => $settings['smtp_encryption']
                        )
                    )
                )
            )
        );

        $vars['base_url'] = ee('CP/URL', 'addons/settings/form_builder/settings');
        $vars['save_btn_text'] = lang('form_builder_save_settings');
        $vars['save_btn_text_working'] = lang('form_builder_saving');
        $vars['cp_page_title'] = lang('form_builder_email_settings');

        return array(
            'heading' => lang('form_builder_email_settings'),
            'breadcrumb' => array(
                $this->base_url->compile() => lang('form_builder_module_name')
            ),
            'body' => ee('View')->make('ee:_shared/form')->render($vars)
        );
    }

    public function add_recaptcha()
    {
        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            $submitted_enabled = ee()->input->post('recaptcha_enabled') === 'y' ? 'y' : 'n';
            $submitted_key     = trim((string) ee()->input->post('recaptcha_site_key'));
            $submitted_secret  = trim((string) ee()->input->post('recaptcha_site_secret'));

            // Check whether a secret is already stored so we know if the field is truly required
            $existing_secret = ee()->db->select('setting_value')
                ->where('site_id', $this->site_id)
                ->where('setting_key', 'recaptcha_site_secret')
                ->get('form_builder_settings')
                ->row('setting_value');

            if ($submitted_enabled === 'y') {
                if (empty($submitted_key)) {
                    ee('CP/Alert')->makeInline('shared-form')
                        ->asIssue()
                        ->withTitle(lang('form_builder_recaptcha_site_key') . ' is required when reCAPTCHA is enabled.')
                        ->now();
                    goto recaptcha_render;
                }
                if (empty($submitted_secret) && empty($existing_secret)) {
                    ee('CP/Alert')->makeInline('shared-form')
                        ->asIssue()
                        ->withTitle(lang('form_builder_recaptcha_site_secret') . ' is required when reCAPTCHA is enabled.')
                        ->now();
                    goto recaptcha_render;
                }
            }

            $settings = array(
                'recaptcha_enabled'  => $submitted_enabled,
                'recaptcha_site_key' => $submitted_key,
            );

            // Only update the secret if a new one was submitted; otherwise preserve the existing stored value
            $keys_to_delete = array_keys($settings);
            if (!empty($submitted_secret)) {
                $settings['recaptcha_site_secret'] = ee('Encrypt')->encode($submitted_secret);
                $keys_to_delete[] = 'recaptcha_site_secret';
            }

            ee()->db->where('site_id', $this->site_id)
                ->where_in('setting_key', $keys_to_delete)
                ->delete('form_builder_settings');

            foreach ($settings as $key => $value) {
                ee()->db->insert('form_builder_settings', array(
                    'site_id'       => $this->site_id,
                    'setting_key'   => $key,
                    'setting_value' => $value
                ));
            }

            ee('CP/Alert')->makeInline('shared-form')
                ->asSuccess()
                ->withTitle(lang('form_builder_recaptcha_settings_saved'))
                ->defer();

            ee()->functions->redirect(
                ee('CP/URL', 'addons/settings/form_builder/add_recaptcha')
            );
        }

        recaptcha_render:
        // Load existing settings
        $settings = array();
        $results = ee()->db->where('site_id', $this->site_id)
            ->get('form_builder_settings')
            ->result_array();

        foreach ($results as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        $defaults = array(
            'recaptcha_enabled'     => 'n',
            'recaptcha_site_key'    => '',
            'recaptcha_site_secret' => ''
        );

        $settings = array_merge($defaults, $settings);

        $secret_is_saved = !empty($settings['recaptcha_site_secret']);
        $decrypted_secret = $secret_is_saved ? (string) ee('Encrypt')->decode($settings['recaptcha_site_secret']) : '';
        $settings['recaptcha_site_secret'] = '';

        $vars = array();
        $vars['sections'] = array(
            array(

                array(
                    'title' => lang('form_builder_recaptcha_enabled'),
                    'desc' => lang('form_builder_recaptcha_enabled_desc'),
                    'fields' => array(
                        'recaptcha_enabled' => array(
                            'type' => 'yes_no',
                            'value' => $settings['recaptcha_enabled'],
                            'choices' => array(
                                'y' => lang('yes'),
                                'n' => lang('no')
                            )
                        )
                    )
                ),

                array(
                    'title' => lang('form_builder_recaptcha_site_key'),
                    'fields' => array(
                        'recaptcha_site_key' => array(
                            'type' => 'text',
                            'value' => $settings['recaptcha_site_key'],
                            'required' => true
                        )
                    )
                ),

                array(
                    'title' => lang('form_builder_recaptcha_site_secret'),
                    'fields' => array(
                        'recaptcha_site_secret' => array(
                            'type'        => 'password',
                            'value'       => '',
                            'placeholder' => $secret_is_saved ? '(saved — leave blank to keep current)' : '',
                            'required'    => !$secret_is_saved,
                            'attrs'       => 'id="recaptcha_site_secret_input"'
                        )
                    )
                )
            )
        );

        $vars['base_url'] = ee('CP/URL', 'addons/settings/form_builder/add_recaptcha');
        $vars['save_btn_text'] = lang('form_builder_save_recaptcha_settings');
        $vars['save_btn_text_working'] = lang('form_builder_saving');
        $vars['cp_page_title'] = lang('form_builder_recaptcha_settings');

        $toggle_script = $secret_is_saved ? '
<script>
document.addEventListener("DOMContentLoaded", function () {
    var input = document.getElementById("recaptcha_site_secret_input");
    if (!input) return;

    var saved = ' . json_encode($decrypted_secret) . ';

    // Swap initial icon to eye-closed (value starts hidden)
    var container = input.parentElement;
    while (container && !container.querySelector("img.js-show-password")) {
        container = container.parentElement;
        if (!container || container.tagName === "FORM") break;
    }
    if (container) {
        var eyeImg = container.querySelector("img.js-show-password");
        if (eyeImg) {
            eyeImg.src = eyeImg.src.replace("eye-open.svg", "eye-closed.svg");
        }
    }

    // Populate/clear value when EE toggles the input type
    var observer = new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
            if (mutation.attributeName === "type") {
                input.value = (input.type === "text") ? saved : "";
            }
        });
    });

    observer.observe(input, { attributes: true });
});
</script>
' : '';

        return array(
            'heading' => lang('form_builder_recaptcha_settings'),
            'breadcrumb' => array(
                $this->base_url->compile() => lang('form_builder_module_name')
            ),
            'body' => ee('View')->make('ee:_shared/form')->render($vars) . $toggle_script
        );
    }

    // -------------------------------------------------------------------------
    // MAILCHIMP SETTINGS
    // -------------------------------------------------------------------------

    public function mailchimp()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $submitted_key    = trim((string) ee()->input->post('mailchimp_api_key'));
            $submitted_alerts = trim((string) ee()->input->post('mailchimp_alerts_email'));

            // Validate alerts email if provided
            if ($submitted_alerts !== '' && !filter_var($submitted_alerts, FILTER_VALIDATE_EMAIL)) {
                ee('CP/Alert')->makeInline('shared-form')
                    ->asIssue()
                    ->withTitle('Alerts email is not a valid email address.')
                    ->now();
                goto mailchimp_render;
            }

            // Load current stored key for comparison
            $existing_encoded = ee()->db->select('setting_value')
                ->where('site_id', $this->site_id)
                ->where('setting_key', 'mailchimp_api_key')
                ->get('form_builder_settings')
                ->row('setting_value');
            $existing_decoded = !empty($existing_encoded)
                ? trim((string) ee('Encrypt')->decode($existing_encoded))
                : '';

            $keys_to_delete = array('mailchimp_alerts_email');
            $settings_to_save = array('mailchimp_alerts_email' => $submitted_alerts);

            if ($submitted_key !== '') {
                $keys_to_delete[] = 'mailchimp_api_key';
                $settings_to_save['mailchimp_api_key'] = ee('Encrypt')->encode($submitted_key);

                // Auto-test if the key changed
                if ($submitted_key !== $existing_decoded) {
                    $test = $this->runMailchimpConnectionTest($submitted_key);
                    if (!$test['ok']) {
                        ee('CP/Alert')->makeInline('shared-form')
                            ->asWarning()
                            ->withTitle(lang('form_builder_mailchimp_connection_fail') . ' — key saved anyway. Detail: ' . htmlspecialchars($test['detail'], ENT_QUOTES))
                            ->now();
                    }
                }
            }

            ee()->db->where('site_id', $this->site_id)
                ->where_in('setting_key', $keys_to_delete)
                ->delete('form_builder_settings');

            foreach ($settings_to_save as $key => $value) {
                ee()->db->insert('form_builder_settings', array(
                    'site_id'       => $this->site_id,
                    'setting_key'   => $key,
                    'setting_value' => $value
                ));
            }

            ee('CP/Alert')->makeInline('shared-form')
                ->asSuccess()
                ->withTitle(lang('form_builder_mailchimp_settings_saved'))
                ->defer();

            ee()->functions->redirect(ee('CP/URL', 'addons/settings/form_builder/mailchimp'));
        }

        mailchimp_render:
        $settings = array();
        $results = ee()->db->where('site_id', $this->site_id)
            ->get('form_builder_settings')
            ->result_array();
        foreach ($results as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        $api_key_is_saved   = !empty($settings['mailchimp_api_key']);
        $settings['mailchimp_api_key'] = '';

        // Compute last refreshed
        $last_refresh = ee()->db->select_max('cached_at')
            ->where('site_id', $this->site_id)
            ->get('form_builder_mailchimp_lists')
            ->row('cached_at');
        $last_refresh_display = $last_refresh ?: lang('form_builder_mailchimp_never_refreshed');

        $vars = array();
        $vars['sections'] = array(
            array(
                array(
                    'title' => lang('form_builder_mailchimp_api_key'),
                    'fields' => array(
                        'mailchimp_api_key' => array(
                            'type'        => 'password',
                            'value'       => '',
                            'placeholder' => $api_key_is_saved ? '(saved — leave blank to keep current)' : ''
                        )
                    )
                ),
                array(
                    'title' => lang('form_builder_mailchimp_alerts_email'),
                    'desc'  => lang('form_builder_mailchimp_alerts_email_desc'),
                    'fields' => array(
                        'mailchimp_alerts_email' => array(
                            'type'  => 'text',
                            'value' => $settings['mailchimp_alerts_email'] ?? ''
                        )
                    )
                ),
                array(
                    'title' => lang('form_builder_mailchimp_last_refresh'),
                    'fields' => array(
                        'last_refresh_display' => array(
                            'type'     => 'html',
                            'content'  => '<p>' . htmlspecialchars((string) $last_refresh_display, ENT_QUOTES) . '</p>'
                        )
                    )
                )
            )
        );

        $vars['base_url']              = ee('CP/URL', 'addons/settings/form_builder/mailchimp');
        $vars['save_btn_text']         = lang('form_builder_save_settings');
        $vars['save_btn_text_working'] = lang('form_builder_saving');
        $vars['cp_page_title']         = lang('form_builder_mailchimp_settings');

        $refresh_note = '<p><strong>' . lang('form_builder_mailchimp_refresh_lists_desc') . '</strong></p>';
        $refresh_form = '<form method="post" action="' . ee('CP/URL', 'addons/settings/form_builder/refresh_mailchimp_lists') . '" style="display:inline;">'
            . '<input type="hidden" name="csrf_token" value="' . CSRF_TOKEN . '">'
            . '<button type="submit" class="btn">' . lang('form_builder_mailchimp_refresh_lists') . '</button>'
            . '</form> '
            . '<form method="post" action="' . ee('CP/URL', 'addons/settings/form_builder/test_mailchimp_connection') . '" style="display:inline;">'
            . '<input type="hidden" name="csrf_token" value="' . CSRF_TOKEN . '">'
            . '<button type="submit" class="btn">' . lang('form_builder_mailchimp_test_connection') . '</button>'
            . '</form>';

        $api_key_instructions = '
<div class="panel" style="margin-top:24px;">
    <div class="panel-heading">
        <div class="title-bar">
            <h3 class="title-bar__title">How to Create and Add a Mailchimp API Key</h3>
        </div>
    </div>
    <div class="panel-body">
        <ol style="margin:0 0 0 20px;padding:0;line-height:2;">
            <li>Log in to your <strong>Mailchimp</strong> account at <a href="https://login.mailchimp.com" target="_blank" rel="noopener">login.mailchimp.com</a>.</li>
            <li>Click your <strong>profile icon</strong> in the top-right corner, then choose <strong>Profile</strong>.</li>
            <li>Select the <strong>Extras</strong> tab, then click <strong>API keys</strong>.</li>
            <li>Scroll to the <em>Your API keys</em> section and click <strong>Create A Key</strong>.</li>
            <li>Give the key a descriptive name (e.g. <em>' . htmlspecialchars((string) ee()->config->item('site_name'), ENT_QUOTES) . ' Website</em>) and click <strong>Generate Key</strong>.</li>
            <li>Copy the key shown — <strong>it is only displayed once</strong>. Store it somewhere safe before closing that page.</li>
            <li>Paste the copied key into the <strong>Mailchimp API Key</strong> field above and click <strong>Save Settings</strong>.</li>
            <li>After saving, click <strong>Refresh Audience List</strong> to pull your Mailchimp audiences into this addon.</li>
        </ol>
        <p style="margin-top:16px;color:#666;">
            <strong>Note:</strong> The API key format looks like <code>xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx-us21</code> — a 32-character string followed by a datacenter suffix. If you see a connection error after saving, double-check that the full key was copied correctly.
        </p>
    </div>
</div>';

        return array(
            'heading' => lang('form_builder_mailchimp_settings'),
            'breadcrumb' => array(
                $this->base_url->compile() => lang('form_builder_module_name')
            ),
            'body' => $refresh_note . $refresh_form . '<br><br>'
                . ee('View')->make('ee:_shared/form')->render($vars)
                . $api_key_instructions
        );
    }

    public function refresh_mailchimp_lists()
    {
        $api_key_encoded = ee()->db->select('setting_value')
            ->where('site_id', $this->site_id)
            ->where('setting_key', 'mailchimp_api_key')
            ->get('form_builder_settings')
            ->row('setting_value');
        $api_key = !empty($api_key_encoded)
            ? trim((string) ee('Encrypt')->decode($api_key_encoded))
            : '';

        if ($api_key === '') {
            ee('CP/Alert')->makeInline('shared-form')
                ->asIssue()
                ->withTitle('API key not configured.')
                ->defer();
            ee()->functions->redirect(ee('CP/URL', 'addons/settings/form_builder/mailchimp'));
            return;
        }

        $api_base = $this->getMailchimpApiBase($api_key);
        if ($api_base === null) {
            ee('CP/Alert')->makeInline('shared-form')
                ->asIssue()
                ->withTitle('Invalid API key format.')
                ->defer();
            ee()->functions->redirect(ee('CP/URL', 'addons/settings/form_builder/mailchimp'));
            return;
        }

        $url      = $api_base . '/lists?count=1000&fields=lists.id,lists.name,lists.stats.member_count';
        $response = $this->mailchimpRequest('GET', $url, $api_key);

        if ($response['http_status'] === 200 && isset($response['body']['lists'])) {
            ee()->db->where('site_id', $this->site_id)
                ->delete('form_builder_mailchimp_lists');

            foreach ($response['body']['lists'] as $list) {
                ee()->db->insert('form_builder_mailchimp_lists', array(
                    'list_id'      => $list['id'],
                    'site_id'      => $this->site_id,
                    'list_name'    => $list['name'],
                    'member_count' => isset($list['stats']['member_count']) ? (int) $list['stats']['member_count'] : 0,
                    'cached_at'    => date('Y-m-d H:i:s')
                ));
            }

            ee('CP/Alert')->makeInline('shared-form')
                ->asSuccess()
                ->withTitle(lang('form_builder_mailchimp_lists_refreshed'))
                ->defer();
        } else {
            $detail = isset($response['body']['detail']) ? ': ' . $response['body']['detail'] : '';
            $msg = $response['http_status'] > 0
                ? 'HTTP ' . $response['http_status'] . $detail
                : ('cURL error: ' . $response['curl_error']);
            ee('CP/Alert')->makeInline('shared-form')
                ->asIssue()
                ->withTitle('Failed to refresh lists. ' . htmlspecialchars($msg, ENT_QUOTES))
                ->defer();
        }

        ee()->functions->redirect(ee('CP/URL', 'addons/settings/form_builder/mailchimp'));
    }

    public function test_mailchimp_connection()
    {
        $api_key_post = trim((string) ee()->input->post('api_key'));
        if ($api_key_post === '') {
            $encoded = ee()->db->select('setting_value')
                ->where('site_id', $this->site_id)
                ->where('setting_key', 'mailchimp_api_key')
                ->get('form_builder_settings')
                ->row('setting_value');
            $api_key_post = !empty($encoded) ? trim((string) ee('Encrypt')->decode($encoded)) : '';
        }

        $result = $this->runMailchimpConnectionTest($api_key_post);

        if ($result['ok']) {
            ee('CP/Alert')->makeInline('shared-form')
                ->asSuccess()
                ->withTitle(lang('form_builder_mailchimp_connection_ok'))
                ->defer();
        } else {
            ee('CP/Alert')->makeInline('shared-form')
                ->asIssue()
                ->withTitle(lang('form_builder_mailchimp_connection_fail') . ' — ' . htmlspecialchars($result['detail'], ENT_QUOTES))
                ->defer();
        }

        ee()->functions->redirect(ee('CP/URL', 'addons/settings/form_builder/mailchimp'));
    }

    private function runMailchimpConnectionTest($api_key)
    {
        $api_key = trim((string) $api_key);
        if ($api_key === '') {
            return array('ok' => false, 'detail' => 'API key not configured');
        }
        $api_base = $this->getMailchimpApiBase($api_key);
        if ($api_base === null) {
            return array('ok' => false, 'detail' => 'Invalid API key format');
        }
        $response = $this->mailchimpRequest('GET', $api_base . '/ping', $api_key);
        if ($response['http_status'] === 200) {
            return array('ok' => true, 'detail' => '');
        }
        $detail = $response['http_status'] > 0
            ? 'HTTP ' . $response['http_status'] . (isset($response['body']['detail']) ? ': ' . $response['body']['detail'] : '')
            : 'cURL error: ' . $response['curl_error'];
        return array('ok' => false, 'detail' => $detail);
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
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
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
            log_message('error', 'Form Builder Mailchimp MCP: cURL error: ' . $curl_error);
            return array('http_status' => 0, 'body' => null, 'curl_error' => $curl_error);
        }

        return array('http_status' => $http_status, 'body' => json_decode($raw_response, true), 'curl_error' => '');
    }

    public function ajax_merge_tags()
    {
        $list_id = trim((string) ee()->input->post('list_id'));

        if (empty($list_id)) {
            echo json_encode(array('ok' => false, 'error' => 'No list ID provided'), JSON_HEX_TAG);
            exit();
        }

        // Verify list is in our cache (site-scoped)
        $list_check = ee()->db->where('list_id', $list_id)
            ->where('site_id', $this->site_id)
            ->count_all_results('form_builder_mailchimp_lists');
        if ($list_check === 0) {
            echo json_encode(array('ok' => false, 'error' => 'List not found. Refresh your Mailchimp lists first.'), JSON_HEX_TAG);
            exit();
        }

        $api_key_encoded = ee()->db->select('setting_value')
            ->where('site_id', $this->site_id)
            ->where('setting_key', 'mailchimp_api_key')
            ->get('form_builder_settings')
            ->row('setting_value');
        $api_key = !empty($api_key_encoded) ? trim((string) ee('Encrypt')->decode($api_key_encoded)) : '';

        if ($api_key === '') {
            echo json_encode(array('ok' => false, 'error' => 'API key not configured.'), JSON_HEX_TAG);
            exit();
        }

        $api_base = $this->getMailchimpApiBase($api_key);
        if ($api_base === null) {
            echo json_encode(array('ok' => false, 'error' => 'Invalid API key format.'), JSON_HEX_TAG);
            exit();
        }

        $url      = $api_base . '/lists/' . $list_id . '/merge-fields?count=100&fields=merge_fields.tag,merge_fields.name';
        $response = $this->mailchimpRequest('GET', $url, $api_key);

        if ($response['http_status'] !== 200 || !isset($response['body']['merge_fields'])) {
            $detail = $response['http_status'] > 0
                ? 'HTTP ' . $response['http_status'] . (isset($response['body']['detail']) ? ': ' . $response['body']['detail'] : '')
                : 'cURL error: ' . $response['curl_error'];
            echo json_encode(array('ok' => false, 'error' => $detail), JSON_HEX_TAG);
            exit();
        }

        // Cache the merge tags (DELETE-WHERE, not truncate)
        ee()->db->where('list_id', $list_id)
            ->where('site_id', $this->site_id)
            ->delete('form_builder_mailchimp_merge_tags');

        $tags_out = array();
        foreach ($response['body']['merge_fields'] as $mf) {
            ee()->db->insert('form_builder_mailchimp_merge_tags', array(
                'list_id'   => $list_id,
                'site_id'   => $this->site_id,
                'tag'       => $mf['tag'],
                'name'      => $mf['name'],
                'cached_at' => date('Y-m-d H:i:s')
            ));
            $tags_out[] = array(
                'value' => $mf['tag'],
                'label' => $mf['name'] . ' (' . $mf['tag'] . ')'
            );
        }

        // Sort by label
        usort($tags_out, function ($a, $b) { return strcmp($a['label'], $b['label']); });

        echo json_encode(array('ok' => true, 'tags' => $tags_out), JSON_HEX_TAG);
        exit();
    }

    public function ajax_subscriber_tags()
    {
        $list_id = trim((string) ee()->input->post('list_id'));

        if (empty($list_id)) {
            echo json_encode(array('ok' => false, 'error' => 'No list ID provided'), JSON_HEX_TAG);
            exit();
        }

        $list_check = ee()->db->where('list_id', $list_id)
            ->where('site_id', $this->site_id)
            ->count_all_results('form_builder_mailchimp_lists');
        if ($list_check === 0) {
            echo json_encode(array('ok' => false, 'error' => 'List not found. Refresh your Mailchimp lists first.'), JSON_HEX_TAG);
            exit();
        }

        $api_key_encoded = ee()->db->select('setting_value')
            ->where('site_id', $this->site_id)
            ->where('setting_key', 'mailchimp_api_key')
            ->get('form_builder_settings')
            ->row('setting_value');
        $api_key = !empty($api_key_encoded) ? trim((string) ee('Encrypt')->decode($api_key_encoded)) : '';

        if ($api_key === '') {
            echo json_encode(array('ok' => false, 'error' => 'API key not configured.'), JSON_HEX_TAG);
            exit();
        }

        $api_base = $this->getMailchimpApiBase($api_key);
        if ($api_base === null) {
            echo json_encode(array('ok' => false, 'error' => 'Invalid API key format.'), JSON_HEX_TAG);
            exit();
        }

        $url      = $api_base . '/lists/' . urlencode($list_id) . '/tag-search?name=';
        $response = $this->mailchimpRequest('GET', $url, $api_key);

        if ($response['http_status'] !== 200 || !isset($response['body']['tags'])) {
            $detail = $response['http_status'] > 0
                ? 'HTTP ' . $response['http_status'] . (isset($response['body']['detail']) ? ': ' . $response['body']['detail'] : '')
                : 'cURL error: ' . $response['curl_error'];
            echo json_encode(array('ok' => false, 'error' => $detail), JSON_HEX_TAG);
            exit();
        }

        ee()->db->where('list_id', $list_id)
            ->where('site_id', $this->site_id)
            ->delete('form_builder_mailchimp_sub_tags');

        $tags_out = array();
        foreach ($response['body']['tags'] as $tag) {
            $name = trim((string) ($tag['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            ee()->db->insert('form_builder_mailchimp_sub_tags', array(
                'list_id'   => $list_id,
                'site_id'   => $this->site_id,
                'name'      => $name,
                'cached_at' => date('Y-m-d H:i:s')
            ));
            $tags_out[] = array('value' => $name, 'label' => $name);
        }

        usort($tags_out, function ($a, $b) { return strcmp($a['label'], $b['label']); });

        echo json_encode(array('ok' => true, 'tags' => $tags_out), JSON_HEX_TAG);
        exit();
    }

    private function buildListSelectHtml(array $mc_list_options, $selected_value)
    {
        $refresh_url = htmlspecialchars(
            ee('CP/URL', 'addons/settings/form_builder/ajax_refresh_lists_inline')->compile(),
            ENT_QUOTES
        );
        $csrf_token = htmlspecialchars(CSRF_TOKEN, ENT_QUOTES);

        $options_html = '';
        foreach ($mc_list_options as $val => $label) {
            $sel = ((string) $val === (string) $selected_value) ? ' selected' : '';
            $options_html .= '<option value="' . htmlspecialchars((string) $val, ENT_QUOTES) . '"' . $sel . '>'
                . htmlspecialchars($label, ENT_QUOTES)
                . '</option>';
        }

        $html = '<div id="mc-list-select-wrap"'
            . ' data-csrf="' . $csrf_token . '"'
            . ' data-refresh-url="' . $refresh_url . '"'
            . ' style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">'
            . '<select name="mailchimp_list_id" class="form-control" style="width:auto;min-width:220px;margin:0;">'
            . $options_html
            . '</select>'
            . '<button type="button" id="mc-refresh-lists-btn" class="btn btn--small" style="margin:0;">Refresh Lists</button>'
            . '<span id="mc-list-status" style="font-size:0.875em;color:#666;"></span>'
            . '</div>';

        $html .= '
<script>
(function(){
var wrap=document.getElementById("mc-list-select-wrap");
if(!wrap)return;
var sel=wrap.querySelector(\'select[name="mailchimp_list_id"]\');
var btn=document.getElementById("mc-refresh-lists-btn");
var st=document.getElementById("mc-list-status");
var csrf=wrap.dataset.csrf;
var refreshUrl=wrap.dataset.refreshUrl;
function escH(s){var d=document.createElement("div");d.appendChild(document.createTextNode(s));return d.innerHTML;}
function escA(s){return s.replace(/&/g,"&amp;").replace(/"/g,"&quot;");}
btn.addEventListener("click",function(){
  st.textContent="Refreshing...";st.style.color="#666";
  var fd=new FormData();fd.append("csrf_token",csrf);
  fetch(refreshUrl,{method:"POST",body:fd})
    .then(function(r){return r.json();})
    .then(function(d){
      if(d.ok){
        var prev=sel.value;
        var opts=\'<option value="">-- Select List --</option>\';
        d.lists.forEach(function(l){opts+=\'<option value="\'+escA(l.value)+\'">\'+escH(l.label)+\'</option>\';});
        sel.innerHTML=opts;
        if(prev&&sel.querySelector(\'option[value="\'+escA(prev)+\'"]\'))sel.value=prev;
        st.textContent=d.lists.length+" list(s) loaded.";st.style.color="#2e7d32";
        if(sel.value){sel.dispatchEvent(new Event("change"));}
      }else{st.textContent="Error: "+d.error;st.style.color="#c0392b";}
    })
    .catch(function(){st.textContent="Request failed.";st.style.color="#c0392b";});
});
})();
</script>';

        return $html;
    }

    public function ajax_refresh_lists_inline()
    {
        $api_key_encoded = ee()->db->select('setting_value')
            ->where('site_id', $this->site_id)
            ->where('setting_key', 'mailchimp_api_key')
            ->get('form_builder_settings')
            ->row('setting_value');
        $api_key = !empty($api_key_encoded)
            ? trim((string) ee('Encrypt')->decode($api_key_encoded))
            : '';

        if ($api_key === '') {
            echo json_encode(array('ok' => false, 'error' => 'API key not configured. Save your Mailchimp API key in Form Builder settings first.'), JSON_HEX_TAG);
            exit();
        }

        $api_base = $this->getMailchimpApiBase($api_key);
        if ($api_base === null) {
            echo json_encode(array('ok' => false, 'error' => 'Invalid API key format.'), JSON_HEX_TAG);
            exit();
        }

        $url      = $api_base . '/lists?count=1000&fields=lists.id,lists.name,lists.stats.member_count';
        $response = $this->mailchimpRequest('GET', $url, $api_key);

        if ($response['http_status'] !== 200 || !isset($response['body']['lists'])) {
            $detail = $response['http_status'] > 0
                ? 'HTTP ' . $response['http_status'] . (isset($response['body']['detail']) ? ': ' . $response['body']['detail'] : '')
                : 'cURL error: ' . $response['curl_error'];
            echo json_encode(array('ok' => false, 'error' => $detail), JSON_HEX_TAG);
            exit();
        }

        ee()->db->where('site_id', $this->site_id)->delete('form_builder_mailchimp_lists');

        $lists_out = array();
        foreach ($response['body']['lists'] as $list) {
            $count = isset($list['stats']['member_count']) ? (int) $list['stats']['member_count'] : 0;
            ee()->db->insert('form_builder_mailchimp_lists', array(
                'list_id'      => $list['id'],
                'site_id'      => $this->site_id,
                'list_name'    => $list['name'],
                'member_count' => $count,
                'cached_at'    => date('Y-m-d H:i:s')
            ));
            $lists_out[] = array(
                'value' => $list['id'],
                'label' => $list['name'] . ' (' . $count . ')'
            );
        }

        usort($lists_out, function ($a, $b) { return strcmp($a['label'], $b['label']); });

        echo json_encode(array('ok' => true, 'lists' => $lists_out), JSON_HEX_TAG);
        exit();
    }

    private function buildMergeFieldsHtml($existing_pairs, $mc_merge_tags, $all_form_fields, $selected_list_id)
    {
        $refresh_url = htmlspecialchars(
            ee('CP/URL', 'addons/settings/form_builder/ajax_merge_tags')->compile(),
            ENT_QUOTES
        );

        // Build tag option elements string
        $tag_opts = '<option value="">-- Mailchimp Field --</option>';
        foreach ($mc_merge_tags as $mt) {
            $tag_opts .= sprintf(
                '<option value="%s">%s (%s)</option>',
                htmlspecialchars($mt['tag'], ENT_QUOTES),
                htmlspecialchars($mt['name'], ENT_QUOTES),
                htmlspecialchars($mt['tag'], ENT_QUOTES)
            );
        }

        // Build form field option elements string
        $field_opts = '<option value="">-- Form Field --</option>';
        foreach ($all_form_fields as $ff) {
            $field_opts .= sprintf(
                '<option value="%s">%s (%s)</option>',
                htmlspecialchars($ff['field_name'], ENT_QUOTES),
                htmlspecialchars($ff['field_label'], ENT_QUOTES),
                htmlspecialchars($ff['field_name'], ENT_QUOTES)
            );
        }

        // Helper closure: inject selected into option string for a specific value
        $inject_selected = function ($opts_html, $value) {
            if ($value === '') return $opts_html;
            $search  = 'value="' . htmlspecialchars($value, ENT_QUOTES) . '">';
            $replace = 'value="' . htmlspecialchars($value, ENT_QUOTES) . '" selected>';
            return str_replace($search, $replace, $opts_html);
        };

        // Render existing rows (or one empty row if no pairs)
        $rows_html = '';
        $pairs_to_render = !empty($existing_pairs) ? $existing_pairs : array(array('tag' => '', 'field' => ''));
        foreach ($pairs_to_render as $pair) {
            $t = $pair['tag']   ?? '';
            $f = $pair['field'] ?? '';
            $rows_html .= '<div class="mc-merge-row" style="display:flex;gap:8px;align-items:center;margin-bottom:6px;flex-wrap:wrap;">'
                . '<select name="mailchimp_merge_tag[]" class="form-control" style="width:auto;min-width:200px;">'
                . $inject_selected($tag_opts, $t)
                . '</select>'
                . '<span style="padding:0 4px;">&rarr;</span>'
                . '<select name="mailchimp_merge_field[]" class="form-control" style="width:auto;min-width:200px;">'
                . $inject_selected($field_opts, $f)
                . '</select>'
                . '<button type="button" class="btn btn--small btn--danger mc-remove-btn">Remove</button>'
                . '</div>';
        }

        // JSON data for JS (used to build new rows without a page reload)
        $js_tag_opts = array();
        foreach ($mc_merge_tags as $mt) {
            $js_tag_opts[] = array('value' => $mt['tag'], 'label' => $mt['name'] . ' (' . $mt['tag'] . ')');
        }
        $js_field_opts = array();
        foreach ($all_form_fields as $ff) {
            $js_field_opts[] = array('value' => $ff['field_name'], 'label' => $ff['field_label'] . ' (' . $ff['field_name'] . ')');
        }
        $js_tag_json   = json_encode($js_tag_opts,   JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        $js_field_json = json_encode($js_field_opts, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        $csrf_token    = htmlspecialchars(CSRF_TOKEN, ENT_QUOTES);

        $no_tags_note = '';
        if (empty($mc_merge_tags)) {
            $msg = empty($selected_list_id)
                ? 'Select a Mailchimp list above, then click <strong>Refresh Mailchimp Fields</strong> to load merge tags.'
                : 'No merge fields cached for this list. Click <strong>Refresh Mailchimp Fields</strong> to load them.';
            $no_tags_note = '<p id="mc-no-tags-note" style="margin:6px 0 0;font-size:0.875em;color:#666;">' . $msg . '</p>';
        }

        $html = '<div id="mc-merge-container"'
            . ' data-csrf="' . $csrf_token . '"'
            . ' data-refresh-url="' . $refresh_url . '">'
            . '<div id="mc-merge-rows">' . $rows_html . '</div>'
            . $no_tags_note
            . '<div style="display:flex;gap:8px;margin-top:8px;align-items:center;flex-wrap:wrap;">'
            . '<button type="button" class="btn btn--small" id="mc-add-row">+ Add Row</button>'
            . '<button type="button" class="btn btn--small" id="mc-refresh-tags">Refresh Mailchimp Fields</button>'
            . '<span id="mc-refresh-status" style="font-size:0.875em;margin-left:4px;"></span>'
            . '</div>'
            . '</div>';

        $html .= '
<script>
var updateTagSelects;
(function(){
var container=document.getElementById("mc-merge-container");
if(!container)return;
var csrf=container.dataset.csrf;
var refreshUrl=container.dataset.refreshUrl;
var tagOpts=' . $js_tag_json . ';
var fieldOpts=' . $js_field_json . ';

function escA(s){return s.replace(/&/g,"&amp;").replace(/"/g,"&quot;");}
function escH(s){return s.replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;");}

function buildFieldOpts(sel){
  var h=\'<option value="">-- Form Field --</option>\';
  fieldOpts.forEach(function(o){
    h+=\'<option value="\'+escA(o.value)+\'"\'+( o.value===sel?\' selected\':\'\')+\'>\'+escH(o.label)+\'</option>\';
  });
  return h;
}

// Rebuild each tag select showing only unselected options (plus its own current value)
function syncTagSelects(){
  var allSels=Array.from(container.querySelectorAll(\'select[name="mailchimp_merge_tag[]"]\'));
  var used={};
  allSels.forEach(function(s){if(s.value!=="")used[s.value]=true;});
  allSels.forEach(function(s){
    var own=s.value;
    var h=\'<option value="">-- Mailchimp Field --</option>\';
    tagOpts.forEach(function(o){
      if(o.value===own||!used[o.value]){
        h+=\'<option value="\'+escA(o.value)+\'"\'+( o.value===own?\' selected\':\'\')+\'>\'+escH(o.label)+\'</option>\';
      }
    });
    s.innerHTML=h;
  });
}

updateTagSelects=function(newOpts){
  tagOpts=newOpts;
  // Reset all tag selects to full list then sync
  container.querySelectorAll(\'select[name="mailchimp_merge_tag[]"]\').forEach(function(s){
    var v=s.value;
    var h=\'<option value="">-- Mailchimp Field --</option>\';
    newOpts.forEach(function(o){h+=\'<option value="\'+escA(o.value)+\'"\'+( o.value===v?\' selected\':\'\')+\'>\'+escH(o.label)+\'</option>\';});
    s.innerHTML=h;
  });
  syncTagSelects();
};

function wireRow(row){
  var tagSel=row.querySelector(\'select[name="mailchimp_merge_tag[]"]\');
  if(tagSel)tagSel.addEventListener("change",syncTagSelects);
  var btn=row.querySelector(".mc-remove-btn");
  if(!btn)return;
  btn.addEventListener("click",function(){
    var rows=document.getElementById("mc-merge-rows");
    if(rows.querySelectorAll(".mc-merge-row").length>1){row.remove();}
    else{row.querySelectorAll("select").forEach(function(s){s.value="";});}
    syncTagSelects();
  });
}
container.querySelectorAll(".mc-merge-row").forEach(wireRow);
syncTagSelects();

document.getElementById("mc-add-row").addEventListener("click",function(){
  var rows=document.getElementById("mc-merge-rows");
  var div=document.createElement("div");
  div.className="mc-merge-row";
  div.style.cssText="display:flex;gap:8px;align-items:center;margin-bottom:6px;flex-wrap:wrap;";
  var ts=document.createElement("select");ts.name="mailchimp_merge_tag[]";ts.className="form-control";ts.style.cssText="width:auto;min-width:200px;";
  var ar=document.createElement("span");ar.innerHTML="&rarr;";ar.style.padding="0 4px";
  var fs=document.createElement("select");fs.name="mailchimp_merge_field[]";fs.className="form-control";fs.style.cssText="width:auto;min-width:200px;";fs.innerHTML=buildFieldOpts("");
  var rb=document.createElement("button");rb.type="button";rb.className="btn btn--small btn--danger mc-remove-btn";rb.textContent="Remove";
  div.appendChild(ts);div.appendChild(ar);div.appendChild(fs);div.appendChild(rb);
  rows.appendChild(div);wireRow(div);
  syncTagSelects();
});

document.getElementById("mc-refresh-tags").addEventListener("click",function(){
  var ls=document.querySelector(\'select[name="mailchimp_list_id"]\');
  var lid=ls?ls.value:"";
  var st=document.getElementById("mc-refresh-status");
  if(!lid){st.textContent="Select a list first.";st.style.color="#c0392b";return;}
  st.textContent="Loading...";st.style.color="#666";
  var fd=new FormData();fd.append("csrf_token",csrf);fd.append("list_id",lid);
  fetch(refreshUrl,{method:"POST",body:fd})
    .then(function(r){return r.json();})
    .then(function(d){
      if(d.ok){
        updateTagSelects(d.tags);
        st.textContent=d.tags.length+" field(s) loaded.";st.style.color="#2e7d32";
        var note=document.getElementById("mc-no-tags-note");if(note)note.remove();
      }else{st.textContent="Error: "+d.error;st.style.color="#c0392b";}
    })
    .catch(function(){st.textContent="Request failed.";st.style.color="#c0392b";});
});
})();
</script>';

        return $html;
    }

    private function buildTagsHtml($existing_tags_raw, $mc_sub_tags, $selected_list_id)
    {
        $refresh_url = htmlspecialchars(
            ee('CP/URL', 'addons/settings/form_builder/ajax_subscriber_tags')->compile(),
            ENT_QUOTES
        );

        // Normalize to array
        if (is_array($existing_tags_raw)) {
            $existing_tags = $existing_tags_raw;
        } elseif (is_string($existing_tags_raw) && $existing_tags_raw !== '') {
            $existing_tags = array_filter(array_map('trim', explode(',', $existing_tags_raw)));
        } else {
            $existing_tags = array();
        }
        $existing_set = array_flip(array_values($existing_tags));

        // Build checkboxes from cached tags
        $checkboxes_html = '';
        foreach ($mc_sub_tags as $t) {
            $name    = $t['name'];
            $checked = isset($existing_set[$name]) ? ' checked' : '';
            $checkboxes_html .= '<label style="display:block;margin-bottom:5px;">'
                . '<input type="checkbox" name="mailchimp_tags[]"'
                . ' value="' . htmlspecialchars($name, ENT_QUOTES) . '"' . $checked . '>'
                . ' ' . htmlspecialchars($name, ENT_QUOTES)
                . '</label>';
        }

        $list_div = '<div id="mc-sub-tags-list" style="max-height:200px;overflow-y:auto;padding:4px 0;">'
            . $checkboxes_html . '</div>';

        $note_html = '';
        if (empty($mc_sub_tags)) {
            $msg = empty($selected_list_id)
                ? 'Select a Mailchimp audience above — merge fields and tags will load automatically.'
                : 'No tags cached for this list. Click <strong>Refresh Tags</strong> to load them.';
            $note_html = '<p id="mc-sub-tags-note" style="margin:0 0 6px;font-size:0.875em;color:#666;">' . $msg . '</p>';
        }

        $csrf_token  = htmlspecialchars(CSRF_TOKEN, ENT_QUOTES);
        $js_existing = json_encode(array_keys($existing_set), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        $merge_refresh_url = htmlspecialchars(
            ee('CP/URL', 'addons/settings/form_builder/ajax_merge_tags')->compile(),
            ENT_QUOTES
        );

        $html = '<div id="mc-sub-tags-container"'
            . ' data-csrf="' . $csrf_token . '"'
            . ' data-refresh-url="' . $refresh_url . '"'
            . ' data-merge-refresh-url="' . $merge_refresh_url . '">'
            . $note_html
            . $list_div
            . '<div style="display:flex;gap:8px;margin-top:8px;align-items:center;">'
            . '<button type="button" class="btn btn--small" id="mc-refresh-sub-tags">Refresh Tags</button>'
            . '<span id="mc-sub-tags-status" style="font-size:0.875em;margin-left:4px;"></span>'
            . '</div>'
            . '</div>';

        $html .= '
<script>
(function(){
var container=document.getElementById("mc-sub-tags-container");
if(!container)return;
var csrf=container.dataset.csrf;
var refreshUrl=container.dataset.refreshUrl;
var mergeRefreshUrl=container.dataset.mergeRefreshUrl;

function getChecked(){
  return Array.from(container.querySelectorAll(\'input[name="mailchimp_tags[]"]:checked\')).map(function(c){return c.value;});
}

function buildCheckboxes(tags,prevChecked){
  var div=document.getElementById("mc-sub-tags-list");
  div.innerHTML="";
  if(tags.length===0){
    div.innerHTML=\'<span style="font-size:0.875em;color:#666;">No tags found for this audience.</span>\';
    return;
  }
  tags.forEach(function(t){
    var lbl=document.createElement("label");
    lbl.style.cssText="display:block;margin-bottom:5px;";
    var cb=document.createElement("input");
    cb.type="checkbox";cb.name="mailchimp_tags[]";cb.value=t.value;
    if(prevChecked.indexOf(t.value)!==-1)cb.checked=true;
    lbl.appendChild(cb);
    lbl.appendChild(document.createTextNode(" "+t.label));
    div.appendChild(lbl);
  });
}

function fetchTags(lid,prevChecked,st){
  if(st){st.textContent="Loading…";st.style.color="#666";}
  var fd=new FormData();fd.append("csrf_token",csrf);fd.append("list_id",lid);
  return fetch(refreshUrl,{method:"POST",body:fd})
    .then(function(r){return r.json();})
    .then(function(d){
      if(d.ok){
        buildCheckboxes(d.tags,prevChecked);
        if(st){st.textContent=d.tags.length+" tag(s) loaded.";st.style.color="#2e7d32";}
        var note=document.getElementById("mc-sub-tags-note");if(note)note.remove();
      }else{if(st){st.textContent="Error: "+d.error;st.style.color="#c0392b";}}
    })
    .catch(function(){if(st){st.textContent="Request failed.";st.style.color="#c0392b";}});
}

function fetchMergeFields(lid){
  var mc=document.getElementById("mc-merge-container");
  if(!mc)return;
  var mst=document.getElementById("mc-refresh-status");
  if(mst){mst.textContent="Loading…";mst.style.color="#666";}
  var fd=new FormData();fd.append("csrf_token",csrf);fd.append("list_id",lid);
  fetch(mergeRefreshUrl,{method:"POST",body:fd})
    .then(function(r){return r.json();})
    .then(function(d){
      if(d.ok){
        if(typeof updateTagSelects==="function")updateTagSelects(d.tags);
        if(mst){mst.textContent=d.tags.length+" field(s) loaded.";mst.style.color="#2e7d32";}
        var note=document.getElementById("mc-no-tags-note");if(note)note.remove();
      }else{if(mst){mst.textContent="Error: "+d.error;mst.style.color="#c0392b";}}
    })
    .catch(function(){if(mst){mst.textContent="Request failed.";mst.style.color="#c0392b";}});
}

document.getElementById("mc-refresh-sub-tags").addEventListener("click",function(){
  var ls=document.querySelector(\'select[name="mailchimp_list_id"]\');
  var lid=ls?ls.value:"";
  var st=document.getElementById("mc-sub-tags-status");
  if(!lid){st.textContent="Select a list first.";st.style.color="#c0392b";return;}
  fetchTags(lid,getChecked(),st);
});

// Auto-refresh both widgets when the audience changes
var listSel=document.querySelector(\'select[name="mailchimp_list_id"]\');
if(listSel){
  listSel.addEventListener("change",function(){
    var lid=this.value;
    if(!lid)return;
    fetchMergeFields(lid);
    fetchTags(lid,[],document.getElementById("mc-sub-tags-status"));
  });
}
})();
</script>';

        return $html;
    }

    private function validateMergeFieldMapping(array $pairs, $form_id, $editing_field_id)
    {
        $errors = array();

        $rhs_query = ee()->db->select('field_name')->where('form_id', $form_id);
        if ($editing_field_id !== null) {
            $rhs_query->where('field_id !=', $editing_field_id);
        }
        $form_field_names = array_column(
            $rhs_query->get('form_builder_fields')->result_array(),
            'field_name'
        );

        foreach ($pairs as $idx => $pair) {
            $tag = $pair['tag']   ?? '';
            $fld = $pair['field'] ?? '';
            if (!preg_match('/^[A-Z0-9_]+$/', $tag)) {
                $errors[] = 'Row ' . ($idx + 1) . ': invalid merge tag format';
                continue;
            }
            if (!in_array($fld, $form_field_names, true)) {
                $errors[] = 'Row ' . ($idx + 1) . ': unknown form field "' . $fld . '"';
            }
        }
        return $errors;
    }

    private function isMailchimpFieldConfiguredMcp($field, $config, $form_id, $has_api_key)
    {
        if (!$has_api_key) {
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

        $email_field_exists = ee()->db->where('form_id', $form_id)
            ->where('field_name', $config['mailchimp_email_field'])
            ->where('field_type', 'email')
            ->count_all_results('form_builder_fields');
        if ($email_field_exists === 0) {
            return false;
        }

        if (!empty($config['mailchimp_merge_fields'])) {
            $form_field_names = array_column(
                ee()->db->select('field_name')->where('form_id', $form_id)
                    ->get('form_builder_fields')->result_array(),
                'field_name'
            );
            $pairs = is_array($config['mailchimp_merge_fields'])
                ? $config['mailchimp_merge_fields']
                : $this->parseLegacyMergeString((string) $config['mailchimp_merge_fields']);
            foreach ($pairs as $pair) {
                $rhs = $pair['field'] ?? '';
                if ($rhs !== '' && !in_array($rhs, $form_field_names, true)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function parseLegacyMergeString($text)
    {
        $pairs = array();
        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '=') === false) continue;
            list($t, $f) = array_map('trim', explode('=', $line, 2));
            if ($t !== '' && $f !== '') $pairs[] = array('tag' => $t, 'field' => $f);
        }
        return $pairs;
    }

    // Download CSV
    public function download_csv($form_id = 0)
    {
        $form_id = (int) $form_id;
        if (!$form_id) {
            ee()->functions->redirect(ee()->functions->fetch_site_index());
            return;
        }

        // Verify the form belongs to the current site
        $form_check = ee()->db->select('form_id')
            ->where('form_id', $form_id)
            ->where('site_id', $this->site_id)
            ->get('form_builder_forms')
            ->row_array();

        if (!$form_check) {
            ee()->functions->redirect(ee()->functions->fetch_site_index());
            return;
        }

        // Check whether any submissions exist before starting CSV output
        $first = ee()->db->select('submission_id')
            ->where('form_id', $form_id)
            ->where('site_id', $this->site_id)
            ->limit(1)
            ->get('form_builder_submissions')
            ->row_array();

        if (empty($first)) {
            ee()->functions->redirect(ee()->functions->fetch_site_index());
            return;
        }

        // Set headers before any output
        $filename = 'form_' . $form_id . '_submissions_' . date('Y-m-d_H-i-s') . '.csv';

        // Disable error display to prevent warnings from appearing in CSV
        ini_set('display_errors', '0');
        error_reporting(0);

        // Clear any previous output
        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // Use current field definitions for column structure so added/removed fields are reflected correctly
        $field_defs = ee()->db->select('field_name, field_label')
            ->where('form_id', $form_id)
            ->where_not_in('field_type', ['warning'])
            ->order_by('field_order', 'asc')
            ->get('form_builder_fields')
            ->result_array();

        // Check if form has any Mailchimp field to conditionally include mailchimp_status column
        $has_mailchimp_field = ee()->db->where('form_id', $form_id)
            ->where('field_type', 'mailchimp_subscription')
            ->count_all_results('form_builder_fields') > 0;

        $columns = [];
        $header_row = ['Submitted Date'];
        foreach ($field_defs as $f) {
            $columns[] = $f['field_name'];
            $header_row[] = $f['field_label'];
        }
        if ($has_mailchimp_field) {
            $header_row[] = lang('form_builder_mailchimp_status');
        }
        fputcsv($output, $header_row);

        // Process submissions in batches to avoid memory issues
        $batch_size = 100;
        $offset = 0;

        while (true) {
            $submissions = ee()->db->select('submission_data, submitted_at, mailchimp_status')
                ->where('form_id', $form_id)
                ->where('site_id', $this->site_id)
                ->order_by('submitted_at', 'asc')
                ->limit($batch_size, $offset)
                ->get('form_builder_submissions')
                ->result_array();

            if (empty($submissions)) {
                break;
            }

            // Add submission rows
            foreach ($submissions as $sub) {
                $data = json_decode($sub['submission_data'], true);
                if (!is_array($data)) {
                    continue;
                }
                $row = [$sub['submitted_at']];
                foreach ($columns as $col) {
                    $row[] = isset($data[$col]['value']) ? $data[$col]['value'] : '';
                }
                if ($has_mailchimp_field) {
                    $mc_status = $sub['mailchimp_status'] ?? 'none';
                    $lang_key  = 'form_builder_mailchimp_status_' . $mc_status;
                    $row[] = lang($lang_key) ?: $mc_status;
                }
                fputcsv($output, $row);
            }

            $offset += $batch_size;

            // Free memory
            unset($submissions);
        }

        fclose($output);
        exit;
    }
}
