<?php

$lang = [
    // Navigation
    'calendar_module_name'    => 'Event Calendar',
    'events'                  => 'Events',
    'all_events'              => 'All Events',
    'add_event'               => 'Add Event',
    'edit_event'              => 'Edit Event',
    'add_single_event'        => 'Add Single Event',
    'add_recurring_event'     => 'Add Recurring Event',

    // Event types
    'event_type'              => 'Type',
    'single_event'            => 'Single',
    'recurring_event'         => 'Recurring',

    // Table headings
    'title'                   => 'Title',
    'start_time'              => 'Start Time',
    'end_time'                => 'End Time',
    'actions'                 => 'Actions',

    // Form labels
    'description'             => 'Description',
    'day_of_week'             => 'Day of Week',
    'open_date'               => 'Start Date',
    'expiration_date'         => 'End Date',

    // Status
    'status'                  => 'Status',
    'open'                    => 'Open',
    'closed'                  => 'Closed',

    // Days of week
    'sunday'                  => 'Sunday',
    'monday'                  => 'Monday',
    'tuesday'                 => 'Tuesday',
    'wednesday'               => 'Wednesday',
    'thursday'                => 'Thursday',
    'friday'                  => 'Friday',
    'saturday'                => 'Saturday',

    // Buttons
    'save'                    => 'Save',
    'edit'                    => 'Edit',
    'delete'                  => 'Delete',
    'cancel'                  => 'Cancel',

    // Success / error messages
    'error'                   => 'Error',
    'event_created'           => 'Event created successfully.',
    'event_updated'           => 'Event updated successfully.',
    'event_deleted'           => 'Event deleted successfully.',
    'recurring_created'       => 'Recurring event created successfully.',
    'recurring_updated'       => 'Recurring event updated successfully.',
    'recurring_deleted'       => 'Recurring event deleted successfully.',
    'event_not_found'         => 'Event not found.',
    'title_required'          => 'Title is required.',
    'invalid_end_time'        => 'End time must be on or after start time.',
    'start_time_invalid'               => 'Start time is invalid. Use format: YYYY-MM-DD HH:MM AM/PM (e.g. 2026-05-15 10:00 AM).',
    'end_time_invalid'                 => 'End time is invalid. Use format: YYYY-MM-DD HH:MM AM/PM (e.g. 2026-05-15 11:00 AM).',
    'start_time_invalid_recurring'     => 'Start time is invalid.',
    'end_time_invalid_recurring'       => 'End time is invalid.',
    'invalid_expiration_date' => 'Expiration date must be after the open date.',
    'invalid_status'          => 'Status must be open or closed.',
    'invalid_day_of_week'     => 'Please select a valid day of the week.',
    'no_events'               => 'No events have been added yet.',
    'confirm_delete'          => 'Are you sure you want to delete this event?',
    'confirm_delete_title'    => 'Confirm Delete',
    'confirm_delete_body'     => 'Are you sure you want to permanently delete <strong>%s</strong>? This cannot be undone.',
    'cancel'                  => 'Cancel',
    'csrf_error'              => 'Invalid form submission.',

    // CP page titles
    'page_all_events'         => 'All Events',
    'page_add_event'          => 'Add Event',
    'page_edit_event'         => 'Edit Event',
    'page_add_recurring'      => 'Add Recurring Event',
    'page_edit_recurring'     => 'Edit Recurring Event',

    // Pagination
    'per_page'                => 'Per Page',
    'showing_events'          => 'Showing %s events',
    'no_more_events'          => 'No more events.',

    // Filter labels
    'filter_type'             => 'Type',
    'filter_type_all'         => 'All Types',
    'filter_status'           => 'Status',
    'filter_status_all'       => 'All Statuses',

    // Front-end / AJAX fallback
    'ajax_error'              => 'Unable to load events. Please try again.',

    // Install / requirements (v2.0)
    'install_requires_ee_7'      => 'ExpressionEngine 7.0.0 or higher is required.',
    'install_requires_php_83'    => 'PHP 8.3 or higher is required.',

    // Event status (expanded in v2.0)
    'cancelled'                  => 'Cancelled',
    'draft'                      => 'Draft',

    // New event fields (v2.0)
    'location'                   => 'Location',
    'event_url'                  => 'URL',
    'all_day'                    => 'All day',
    'slug'                       => 'URL Slug',
    'recurrence'                 => 'Recurrence',
    'no_recurrence'              => 'Does not repeat',

    // Validation (v2.0)
    'invalid_rrule'              => 'Recurrence rule is invalid: %s',
    'slug_invalid'               => 'Slug must contain only lowercase letters, numbers, and hyphens.',
    'slug_taken'                 => 'This slug is already in use by another event.',

    // Categories
    'categories'                 => 'Categories',
    'category_group'             => 'Category Group',
    'filter_category'            => 'Category',
    'filter_category_all'        => 'All Categories',
    'no_categories'              => 'No categories have been added to this group.',
    'no_category_groups'         => 'No category groups found. Create one first.',
    'invalid_category_group'     => 'Please select a valid category group.',
    'category_group_saved'       => 'Category group saved.',

    // Settings / sidebar
    'settings'                   => 'Settings',
    'templates'                  => 'Templates',

    // Template reference page
    'copy'                       => 'Copy',
    'copied'                     => 'Copied!',
    'tpl_param'                  => 'Parameter',
    'tpl_values'                 => 'Values',
    'tpl_default'                => 'Default',
    'tpl_notes'                  => 'Notes',
    'tpl_variable'               => 'Variable',
    'tpl_type'                   => 'Type',

    // Category group setup
    'setup_category_group_title' => 'Choose a Category Group',
    'setup_category_group_intro' => 'Event Calendar uses ExpressionEngine\'s native categories. Pick an existing category group or create a new one.',
    'setup_create_new_group'     => 'Create a new category group',
];
