<?php

$lang = array(
    // Module name
    'form_builder_module_name'        => 'Form Builder',
    'form_builder_module_description' => 'Build and manage contact forms',

    // Sidebar
    'form_builder_forms'              => 'Forms',
    'form_builder_all_forms'          => 'All Forms',
    'form_builder_create_form'        => 'Create Form',
    'form_builder_submissions'        => 'Submissions',
    'form_builder_all_submissions'    => 'All Submissions',
    'form_builder_settings'           => 'Settings',
    'form_builder_email_settings'     => 'Email Settings',
    'form_builder_add_recaptcha'      => 'reCAPTCHA Settings',

    // Form fields
    'form_builder_form_name'          => 'Form Name',
    'form_builder_form_name_desc'     => 'Short name used in template tags (lowercase, no spaces)',
    'form_builder_form_label'         => 'Form Label',
    'form_builder_form_label_desc'    => 'Display name for the form',
    'form_builder_is_active'          => 'Active',
    'form_builder_edit_form'          => 'Edit Form',
    'form_builder_save_form'          => 'Save Form',
    'form_builder_saving'             => 'Saving...',
    'form_builder_download_csv'       => 'Download CSV',

    // Email routing
    'form_builder_email_routing'      => 'Email Routing',
    'form_builder_recipient_email'    => 'Recipient Email',
    'form_builder_recipient_email_desc' => 'Email address(es) to receive form submissions. Separate multiple with commas.',
    'form_builder_reply_to_field'     => 'Reply-To Field',
    'form_builder_reply_to_field_desc' => 'Select an email field to use as Reply-To address',
    'form_builder_select_field'       => '-- Select Field --',
    'form_builder_email_subject'      => 'Email Subject',
    'form_builder_success_redirect'   => 'Success Redirect URL',
    'form_builder_success_redirect_desc' => 'URL to redirect to after successful submission',

    // Confirmation email
    'form_builder_confirmation_email'        => 'Confirmation Email',
    'form_builder_send_confirmation'         => 'Send Confirmation Email',
    'form_builder_send_confirmation_desc'    => 'Send an auto-reply to the person who submitted the form. Requires a Reply-To Field to be set in the Email Routing section above — the confirmation is sent to that address.',
    'form_builder_confirmation_subject'      => 'Confirmation Subject',
    'form_builder_confirmation_from_name'    => 'From Name',
    'form_builder_confirmation_from_email'   => 'From Email',
    'form_builder_confirmation_template'     => 'Confirmation Message',
    'form_builder_confirmation_template_desc' => 'Use {field_name} to include submitted values',

    // Fields
    'form_builder_fields'             => 'Fields',
    'form_builder_edit_fields'        => 'Edit Fields',
    'form_builder_add_field'          => 'Add Field',
    'form_builder_edit_field'         => 'Edit Field',
    'form_builder_save_field'         => 'Save Field',
    'form_builder_field_label'        => 'Field Label',
    'form_builder_field_header'        => 'Field Header',
    'form_builder_field_header_desc'   => 'Display header shown to users',
    'form_builder_field_label_desc'   => 'Display label shown to users',
    'form_builder_field_name'         => 'Field Name',
    'form_builder_field_name_desc'    => 'Internal name (lowercase, no spaces)',
    'form_builder_field_type'         => 'Field Type',
    'form_builder_is_required'        => 'Required',
    'form_builder_confirm'            =>  'Confirm Email?',
    'form_builder_confirm_email_label' => 'Confirm Email',
    'form_builder_field_settings'     => 'Field Settings',
    'form_builder_placeholder'        => 'Placeholder',
    'form_builder_default_value'      => 'Default Value',
    'form_builder_css_class'          => 'CSS Class',
    'form_builder_field_options'      => 'Options',
    'form_builder_field_options_desc' => 'One option per line. Use value|label format for separate values.',
    'form_builder_file_settings'      => 'File Upload Settings',
    'form_builder_file_types'         => 'Allowed File Types',
    'form_builder_file_types_desc'    => 'Comma-separated extensions (e.g., pdf,doc,jpg)',
    'form_builder_max_file_size'      => 'Max File Size (KB)',
    'form_builder_max_file_size_desc' => 'Maximum file size in kilobytes',

    // Submissions
    'form_builder_status_new'         => 'New',
    'form_builder_status_read'        => 'Read',
    'form_builder_view_submission'    => 'View Submission',
    'form_builder_submission_data'    => 'Submission Data',
    'form_builder_submission_info'    => 'Submission Info',
    'form_builder_submitted_at'       => 'Submitted At',
    'form_builder_ip_address'         => 'IP Address',
    'form_builder_status'             => 'Status',
    'form_builder_email_sent'         => 'Email Sent',
    'form_builder_confirmation_sent'  => 'Confirmation Sent',

    // Settings
    'form_builder_default_sender'     => 'Default Sender',
    'form_builder_from_name'          => 'From Name',
    'form_builder_from_email'         => 'From Email',
    'form_builder_smtp_settings'      => 'SMTP Settings',
    'form_builder_smtp_enabled'       => 'Use SMTP',
    'form_builder_smtp_enabled_desc'  => 'Enable to send emails via SMTP instead of PHP mail()',
    'form_builder_smtp_host'          => 'SMTP Host',
    'form_builder_smtp_port'          => 'SMTP Port',
    'form_builder_smtp_username'      => 'SMTP Username',
    'form_builder_smtp_password'      => 'SMTP Password',
    'form_builder_smtp_encryption'    => 'Encryption',
    'form_builder_none'               => 'None',
    'form_builder_save_settings'      => 'Save Settings',

    // Messages
    'form_builder_form_created'       => 'Form created successfully',
    'form_builder_form_updated'       => 'Form updated successfully',
    'form_builder_form_deleted'       => 'Form deleted successfully',
    'form_builder_field_created'      => 'Field created successfully',
    'form_builder_field_updated'      => 'Field updated successfully',
    'form_builder_field_deleted'      => 'Field deleted successfully',
    'form_builder_submission_deleted' => 'Submission deleted successfully',
    'form_builder_settings_saved'     => 'Settings saved successfully',

    // reCAPTCHA settings
    'form_builder_recaptcha_settings'       => 'reCAPTCHA Settings',
    'form_builder_recaptcha_enabled'        => 'Enable reCAPTCHA',
    'form_builder_recaptcha_enabled_desc'   => 'Turn reCAPTCHA validation on or off.',
    'form_builder_recaptcha_site_key'       => 'reCAPTCHA Site Key',
    'form_builder_recaptcha_site_secret'    => 'reCAPTCHA Site Secret',
    'form_builder_recaptcha_score_threshold'      => 'Minimum Score',
    'form_builder_recaptcha_score_threshold_desc' => 'reCAPTCHA v3 scores each submission from 0.0 (likely a bot) to 1.0 (likely human). Submissions scoring below this are rejected. Leave blank to use the default (0.3).',
    'form_builder_save_recaptcha_settings'  => 'Save reCAPTCHA Settings',
    'form_builder_recaptcha_settings_saved' => 'reCAPTCHA Settings Saved',

    // Table headers
    'form_builder_name'               => 'Name',
    'form_builder_label'              => 'Label',
    'form_builder_type'               => 'Type',
    'form_builder_submissions_count'  => 'Submissions',
    'form_builder_actions'            => 'Actions',
    'form_builder_order'              => 'Order',
    'form_builder_form'               => 'Form',
    'form_builder_date'               => 'Date',

    // Buttons
    'form_builder_delete'             => 'Delete',
    'form_builder_edit'               => 'Edit',
    'form_builder_view'               => 'View',
    'form_builder_back'               => 'Back',

    // Confirmations
    'form_builder_confirm_delete_form'  => 'Are you sure you want to delete this form? All fields and submissions will also be deleted.',
    'form_builder_confirm_delete_field' => 'Are you sure you want to delete this field?',
    'form_builder_confirm_delete_submission' => 'Are you sure you want to delete this submission?',

    // Empty states
    'form_builder_no_forms'           => 'No forms have been created yet.',
    'form_builder_no_fields'          => 'No fields have been added to this form yet.',
    'form_builder_no_submissions'     => 'No submissions have been received yet.',

    // Mailchimp settings page
    'form_builder_mailchimp_settings'           => 'Mailchimp Settings',
    'form_builder_mailchimp_api_key'            => 'Mailchimp API Key',
    'form_builder_mailchimp_alerts_email'       => 'Alerts Email',
    'form_builder_mailchimp_alerts_email_desc'  => 'Optional email address to receive failure alerts (in addition to the form\'s recipient email).',
    'form_builder_mailchimp_refresh_lists'      => 'Refresh Lists',
    'form_builder_mailchimp_refresh_lists_desc' => 'Lists do not update automatically. Click Refresh Lists after creating or renaming audiences in Mailchimp.',
    'form_builder_mailchimp_test_connection'    => 'Test Connection',
    'form_builder_mailchimp_settings_saved'     => 'Mailchimp settings saved successfully',
    'form_builder_mailchimp_lists_refreshed'    => 'Mailchimp lists refreshed successfully',
    'form_builder_mailchimp_last_refresh'       => 'Last refreshed',
    'form_builder_mailchimp_never_refreshed'    => 'Never refreshed',
    'form_builder_mailchimp_connection_ok'      => 'Connection to Mailchimp successful',
    'form_builder_mailchimp_connection_fail'    => 'Could not connect to Mailchimp',

    // Mailchimp field config
    'form_builder_mailchimp_list_id'              => 'Mailchimp List',
    'form_builder_mailchimp_email_field'          => 'Email Source Field',
    'form_builder_mailchimp_email_field_desc'     => 'The form field containing the email address to subscribe.',
    'form_builder_mailchimp_default_checked'      => 'Default Checked',
    'form_builder_mailchimp_default_checked_desc' => 'Whether the checkbox is pre-checked on the front-end (best practice: no).',
    'form_builder_mailchimp_merge_fields'         => 'Merge Field Mapping',
    'form_builder_mailchimp_merge_fields_desc'    => 'One per line: MERGE_TAG=form_field_name. Example: FNAME=first_name',
    'form_builder_mailchimp_tags'                 => 'Tags',
    'form_builder_mailchimp_tags_desc'            => 'Comma-separated tags applied to subscribers. Example: newsletter-signup, footer-form',

    // Per-form Mailchimp confirmation text
    'form_builder_mailchimp_success_text'      => 'Subscription Success Text',
    'form_builder_mailchimp_success_text_desc' => 'Text inserted where {mailchimp_status} appears in your confirmation template, when subscription succeeds. Leave blank to use the default.',
    'form_builder_mailchimp_failure_text'      => 'Subscription Failure Text',
    'form_builder_mailchimp_failure_text_desc' => 'Text inserted where {mailchimp_status} appears in your confirmation template, when subscription fails. Leave blank to use the default.',
    'form_builder_mailchimp_default_success'   => 'You have been subscribed to our mailing list at your request. You can unsubscribe at any time using the link in our emails.',
    'form_builder_mailchimp_default_failure'   => 'There was an issue subscribing you to our mailing list. We\'ll add you manually.',

    // Submission viewer
    'form_builder_mailchimp_status'                       => 'Mailchimp Status',
    'form_builder_mailchimp_error'                        => 'Mailchimp Error',
    'form_builder_mailchimp_why'                          => 'Why It Failed',
    'form_builder_mailchimp_status_subscribed'            => 'Subscribed',
    'form_builder_mailchimp_status_reactivated'           => 'Re-subscribed',
    'form_builder_mailchimp_status_updated'               => 'Updated',
    'form_builder_mailchimp_status_skipped_unchecked'     => 'Not requested',
    'form_builder_mailchimp_status_skipped_misconfigured' => 'Skipped (misconfigured)',
    'form_builder_mailchimp_status_failed'                          => 'Failed',
    'form_builder_mailchimp_status_failed_rate_limit'               => 'Failed (rate limited)',
    'form_builder_mailchimp_status_failed_invalid_email'            => 'Failed (invalid email)',
    'form_builder_mailchimp_status_failed_permanently_deleted'      => 'Failed (permanently deleted)',

    // Field list warning
    'form_builder_mailchimp_field_misconfigured' => 'Mailchimp field requires configuration — not currently active on the front-end.',

    // Field type picker and type-change UX (v1.2.0)
    'form_builder_field_type_change_desc'     => 'Changing the type will reload the form. Only compatible types are available.',
    'form_builder_field_type_locked_desc'     => 'Field type cannot be changed after creation. To use a different type, delete this field and create a new one.',
    'form_builder_no_default'                 => '-- No default --',
    'form_builder_default_value_choice_desc'  => 'Enter a value matching one of your Field Options to set as default. After saving, you\'ll be able to pick from a dropdown.',
    'form_builder_save_and_continue'          => 'Save & Continue',

    // Warning field settings
    'form_builder_warning_color'      => 'Text Color',
    'form_builder_warning_color_desc' => 'Hex color applied to the warning label text (e.g. #cc0000). Leave blank to inherit the site default.',

    // Field type group labels (used by FIELD_TYPE_GROUPS constant via label_key)
    'form_builder_group_text_like'   => 'Text Fields',
    'form_builder_group_choice_like' => 'Choice Fields',
    'form_builder_group_binary'      => 'File Fields',
    'form_builder_group_mailchimp'   => 'Integrations',
    'form_builder_group_display'     => 'Display Only',
    'form_builder_group_composite'   => 'Repeating Sections',

    // Composite (repeating) fields (v1.3.0)
    'form_builder_max_rows'      => 'Maximum Entries',
    'form_builder_max_rows_desc' => 'How many entries an applicant may add (1–%d). Each entry has the columns: %s.',

    // Form templates (v1.3.0)
    'form_builder_templates'             => 'Templates',
    'form_builder_all_templates'         => 'Form Templates',
    'form_builder_save_as_template'      => 'Save as Template',
    'form_builder_new_from_template'     => 'New Form from Template',
    'form_builder_template_saved'        => 'Template saved.',
    'form_builder_template_deleted'      => 'Template deleted.',
    'form_builder_form_created_from_template' => 'Form created from template. Review the fields below.',
    'form_builder_no_templates'          => 'No templates yet. Open a form and choose "Save as Template".',
    'form_builder_confirm_delete_template' => 'Delete this template? Forms already created from it are not affected.',
);
