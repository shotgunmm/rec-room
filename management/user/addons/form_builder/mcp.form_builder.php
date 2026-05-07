<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Form_builder_mcp
{
    private $base_url;
    private $site_id;
    private $field_types = array(
        'text' => 'Text',
        'textarea' => 'Textarea',
        'email' => 'Email',
        'url' => 'URL',
        'phone' => 'Telephone',
        'number' => 'Number',
        'select' => 'Select Dropdown',
        'radio' => 'Radio Buttons',
        'checkbox' => 'Checkbox',
        'date' => 'Date',
        'time' => 'Time',
        'file' => 'File Upload',
        'warning' => 'Warning Text'
    );

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
            'is_active' => 'y'
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
                                 'confirmation_from_name', 'confirmation_from_email', 'is_active'];
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
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            ee()->functions->redirect($this->base_url);
            return;
        }

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

        return array(
            'heading' => lang('form_builder_edit_fields') . ': ' . $form['form_label'],
            'breadcrumb' => array(
                $this->base_url->compile() => lang('form_builder_module_name'),
                ee('CP/URL', 'addons/settings/form_builder/edit_form/' . $form_id)->compile() => $form['form_label']
            ),
            'body' => ee('View')->make('form_builder:fields_list')->render(array(
                'form' => $form,
                'fields' => $fields,
                'field_types' => $this->field_types,
                'base_url' => $this->base_url
            ))
        );
    }

    public function edit_field($form_id = 0, $field_id = 0)
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
            'field_name'       => '',
            'field_header'     => '',
            'field_label'      => '',
            'field_type'       => 'text',
            'field_options'    => '',
            'placeholder'      => '',
            'default_value'    => '',
            'is_required'      => 'n',
            'confirm'          => 'n',
            'css_class'        => '',
            'file_types'       => '',
            'max_file_size'    => ''
        );
        $skip_db_reload = false;

        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = array(
                'form_id' => (int)$form_id,
                'field_name' => ee()->input->post('field_name'),
                'field_header' => ee()->input->post('field_header'),
                'field_label' => trim((string) ee()->input->post('field_label')),
                'field_type' => array_key_exists(ee()->input->post('field_type'), $this->field_types)
                    ? ee()->input->post('field_type')
                    : 'text',
                'field_options' => ee()->input->post('field_options'),
                'placeholder' => ee()->input->post('placeholder'),
                'default_value' => ee()->input->post('default_value'),
                'is_required' => ee()->input->post('is_required') ?: 'n',
                'confirm' => ee()->input->post('confirm') ?: 'n',
                'css_class' => ee()->input->post('css_class'),
                'file_types' => ee()->input->post('file_types'),
                'max_file_size' => ee()->input->post('max_file_size') ?: null
            );

            // Sanitize field_name
            $data['field_name'] = preg_replace('/[^a-z0-9_]/', '', strtolower($data['field_name']));

            // Validate required text fields after sanitization
            if (empty($data['field_label'])) {
                ee('CP/Alert')->makeInline('shared-form')
                    ->asIssue()
                    ->withTitle('Field label is required.')
                    ->now();
                $field = array_merge($field, $data);
                $skip_db_reload = true;
                // Fall through to re-render with user's values
            } elseif (empty($data['field_name'])) {
                ee('CP/Alert')->makeInline('shared-form')
                    ->asIssue()
                    ->withTitle('Field name is required and may only contain lowercase letters, numbers, and underscores.')
                    ->now();
                $field = array_merge($field, $data);
                $skip_db_reload = true;
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
                    $skip_db_reload = true;
                    // Fall through to re-render
                } else {
                    if ($is_new) {
                        $max_order = ee()->db->select_max('field_order')
                            ->where('form_id', $form_id)
                            ->get('form_builder_fields')
                            ->row('field_order');
                        $data['field_order'] = ($max_order !== null) ? $max_order + 1 : 0;

                        ee()->db->insert('form_builder_fields', $data);
                        ee('CP/Alert')->makeInline('shared-form')
                            ->asSuccess()
                            ->withTitle(lang('form_builder_field_created'))
                            ->defer();
                    } else {
                        ee()->db->where('field_id', (int)$field_id)
                            ->where('form_id', (int)$form_id)
                            ->update('form_builder_fields', $data);
                        ee('CP/Alert')->makeInline('shared-form')
                            ->asSuccess()
                            ->withTitle(lang('form_builder_field_updated'))
                            ->defer();
                    }

                    ee()->functions->redirect(ee('CP/URL', 'addons/settings/form_builder/edit_fields/' . $form_id));
                }
            }
        }

        // Load from DB only when not repopulating from POST dupe-check
        if (!$is_new && !$skip_db_reload) {
            $result = ee()->db->where('field_id', (int)$field_id)
                ->where('form_id', (int)$form_id)
                ->get('form_builder_fields')
                ->row_array();
            if ($result) {
                $field = $result;
            }
        }

        $vars = array();
        $vars['sections'] = array(
            array(
                array(
                    'title' => lang('form_builder_field_header'),
                    'desc' => lang('form_builder_field_header_desc'),
                    'fields' => array(
                        'field_header' => array(
                            'type' => 'text',
                            'value' => $field['field_header'],
                        )
                    )
                ),
                array(
                    'title' => lang('form_builder_field_label'),
                    'desc' => lang('form_builder_field_label_desc'),
                    'fields' => array(
                        'field_label' => array(
                            'type' => 'textarea',
                            'value' => $field['field_label'],
                            'required' => true,
                            'attrs' => 'rows="2" style="min-height:0;"'
                        )
                    )
                ),
                array(
                    'title' => lang('form_builder_field_name'),
                    'desc' => lang('form_builder_field_name_desc'),
                    'fields' => array(
                        'field_name' => array(
                            'type' => 'text',
                            'value' => $field['field_name'],
                            'required' => true
                        )
                    )
                ),
                array(
                    'title' => lang('form_builder_field_type'),
                    'fields' => array(
                        'field_type' => array(
                            'type' => 'select',
                            'choices' => $this->field_types,
                            'value' => $field['field_type']
                        )
                    )
                ),
                array(
                    'title' => lang('form_builder_is_required'),
                    'fields' => array(
                        'is_required' => array(
                            'type' => 'yes_no',
                            'value' => $field['is_required']
                        )
                    )
                ),
                 array(
                    'title' => lang('form_builder_confirm'),
                    'fields' => array(
                        'confirm' => array(
                            'type' => 'yes_no',
                            'value' => $field['confirm'] ?? 'n'
                        )
                    )
                )
            ),
            lang('form_builder_field_settings') => array(
                array(
                    'title' => lang('form_builder_placeholder'),
                    'fields' => array(
                        'placeholder' => array(
                            'type' => 'text',
                            'value' => $field['placeholder']
                        )
                    )
                ),
                array(
                    'title' => lang('form_builder_default_value'),
                    'fields' => array(
                        'default_value' => array(
                            'type' => 'text',
                            'value' => $field['default_value']
                        )
                    )
                ),
                array(
                    'title' => lang('form_builder_css_class'),
                    'fields' => array(
                        'css_class' => array(
                            'type' => 'text',
                            'value' => $field['css_class']
                        )
                    )
                ),
                array(
                    'title' => lang('form_builder_field_options'),
                    'desc' => lang('form_builder_field_options_desc'),
                    'fields' => array(
                        'field_options' => array(
                            'type' => 'textarea',
                            'value' => $field['field_options']
                        )
                    )
                )
            ),
            lang('form_builder_file_settings') => array(
                array(
                    'title' => lang('form_builder_file_types'),
                    'desc' => lang('form_builder_file_types_desc'),
                    'fields' => array(
                        'file_types' => array(
                            'type' => 'text',
                            'value' => $field['file_types']
                        )
                    )
                ),
                array(
                    'title' => lang('form_builder_max_file_size'),
                    'desc' => lang('form_builder_max_file_size_desc'),
                    'fields' => array(
                        'max_file_size' => array(
                            'type' => 'text',
                            'value' => $field['max_file_size']
                        )
                    )
                )
            )
        );

        $vars['base_url'] = $is_new
            ? ee('CP/URL', 'addons/settings/form_builder/edit_field/' . $form_id)
            : ee('CP/URL', 'addons/settings/form_builder/edit_field/' . $form_id . '/' . $field_id);
        $vars['save_btn_text'] = $is_new ? lang('form_builder_add_field') : lang('form_builder_save_field');
        $vars['save_btn_text_working'] = lang('form_builder_saving');
        $vars['cp_page_title'] = $is_new ? lang('form_builder_add_field') : lang('form_builder_edit_field');

        return array(
            'heading' => $vars['cp_page_title'],
            'breadcrumb' => array(
                $this->base_url->compile() => lang('form_builder_module_name'),
                ee('CP/URL', 'addons/settings/form_builder/edit_fields/' . $form_id)->compile() => $form['form_label']
            ),
            'body' => ee('View')->make('ee:_shared/form')->render($vars)
        );
    }

    public function delete_field($form_id = 0, $field_id = 0)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            ee()->functions->redirect(ee('CP/URL', 'addons/settings/form_builder/edit_fields/' . $form_id));
            return;
        }

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
            $csrf_token = ee()->input->post('csrf_token');
            if (empty($csrf_token) || $csrf_token !== CSRF_TOKEN) {
                ee()->functions->redirect(ee('CP/URL', 'addons/settings/form_builder/submissions'));
                return;
            }
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
                ->where_not_in('field_type', ['warning', 'header'])
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
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            ee()->functions->redirect(ee('CP/URL', 'addons/settings/form_builder/submissions'));
            return;
        }

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
            ->where_not_in('field_type', ['warning', 'header'])
            ->order_by('field_order', 'asc')
            ->get('form_builder_fields')
            ->result_array();

        $columns = [];
        $header_row = ['Submitted Date'];
        foreach ($field_defs as $f) {
            $columns[] = $f['field_name'];
            $header_row[] = $f['field_label'];
        }
        fputcsv($output, $header_row);

        // Process submissions in batches to avoid memory issues
        $batch_size = 100;
        $offset = 0;

        while (true) {
            $submissions = ee()->db->select('submission_data, submitted_at')
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
