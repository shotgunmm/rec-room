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
);
