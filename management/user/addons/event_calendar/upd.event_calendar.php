<?php

class Event_calendar_upd
{
    public $version = '2.0.0';

    public function install()
    {
        if (version_compare(APP_VER, '7.0.0', '<')) {
            ee('CP/Alert')->makeInline('event-calendar-install-error')
                ->asIssue()
                ->withTitle(lang('calendar_module_name'))
                ->addToBody(lang('install_requires_ee_7'))
                ->defer();
            return FALSE;
        }

        if (version_compare(PHP_VERSION, '8.3.0', '<')) {
            ee('CP/Alert')->makeInline('event-calendar-install-error')
                ->asIssue()
                ->withTitle(lang('calendar_module_name'))
                ->addToBody(lang('install_requires_php_83'))
                ->defer();
            return FALSE;
        }

        // exp_calendar_events -- unified single + recurring events table
        // rrule = NULL means single event
        ee()->db->query("CREATE TABLE IF NOT EXISTS exp_calendar_events (
            id              INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            site_id         INT(5)  UNSIGNED NOT NULL DEFAULT 1,
            title           VARCHAR(255) NOT NULL,
            slug            VARCHAR(255) NOT NULL DEFAULT '',
            description     TEXT NOT NULL,
            location        VARCHAR(255) NOT NULL DEFAULT '',
            url             VARCHAR(512) NOT NULL DEFAULT '',
            start_time      INT(10) UNSIGNED NOT NULL,
            end_time        INT(10) UNSIGNED NOT NULL,
            all_day         TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
            rrule           VARCHAR(512) NULL DEFAULT NULL,
            recurrence_end  INT(10) UNSIGNED NULL DEFAULT NULL,
            status          VARCHAR(20) NOT NULL DEFAULT 'open',
            created_at      INT(10) UNSIGNED NOT NULL,
            updated_at      INT(10) UNSIGNED NOT NULL,
            PRIMARY KEY (id),
            KEY site_start (site_id, start_time),
            KEY site_recur_end (site_id, recurrence_end),
            KEY site_status (site_id, status),
            KEY slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // exp_calendar_event_exceptions -- per-occurrence cancellations
        // (v2.0: type is always 'cancelled'; override columns reserved for v2.1)
        ee()->db->query("CREATE TABLE IF NOT EXISTS exp_calendar_event_exceptions (
            id                   INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            event_id             INT(10) UNSIGNED NOT NULL,
            occurrence_date      DATE NOT NULL,
            type                 VARCHAR(20) NOT NULL DEFAULT 'cancelled',
            override_start       INT(10) UNSIGNED NULL DEFAULT NULL,
            override_end         INT(10) UNSIGNED NULL DEFAULT NULL,
            override_title       VARCHAR(255) NULL DEFAULT NULL,
            override_location    VARCHAR(255) NULL DEFAULT NULL,
            override_description TEXT NULL DEFAULT NULL,
            created_at           INT(10) UNSIGNED NOT NULL,
            updated_at           INT(10) UNSIGNED NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY event_occurrence (event_id, occurrence_date),
            KEY event_id (event_id),
            CONSTRAINT fk_exception_event FOREIGN KEY (event_id)
                REFERENCES exp_calendar_events (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // exp_calendar_event_categories -- pivot to exp_categories
        // ON DELETE CASCADE on cat_id => EE native category deletion cleans up pivot rows
        ee()->db->query("CREATE TABLE IF NOT EXISTS exp_calendar_event_categories (
            event_id   INT(10) UNSIGNED NOT NULL,
            cat_id     INT(4)  UNSIGNED NOT NULL,
            PRIMARY KEY (event_id, cat_id),
            KEY cat_id (cat_id),
            CONSTRAINT fk_evcat_event FOREIGN KEY (event_id)
                REFERENCES exp_calendar_events (id) ON DELETE CASCADE,
            CONSTRAINT fk_evcat_category FOREIGN KEY (cat_id)
                REFERENCES exp_categories (cat_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // exp_event_calendar_settings -- per-site addon settings
        ee()->db->query("CREATE TABLE IF NOT EXISTS exp_event_calendar_settings (
            site_id        INT(5)  UNSIGNED NOT NULL DEFAULT 1,
            cat_group_id   INT(4)  UNSIGNED NULL DEFAULT NULL,
            color_field_id INT(6)  UNSIGNED NULL DEFAULT NULL,
            default_view   VARCHAR(10) NOT NULL DEFAULT 'month',
            created_at     INT(10) UNSIGNED NOT NULL,
            updated_at     INT(10) UNSIGNED NOT NULL,
            PRIMARY KEY (site_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Seed a settings row for the current site
        $now = ee()->localize->now;
        ee()->db->insert('event_calendar_settings', [
            'site_id'    => (int) ee()->config->item('site_id'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Register AJAX action -- guard against duplicate
        $exists = ee()->db
            ->where('class', 'Event_calendar')
            ->where('method', 'fetch_events')
            ->count_all_results('actions');
        if (!$exists) {
            ee()->db->insert('actions', [
                'class'       => 'Event_calendar',
                'method'      => 'fetch_events',
                'csrf_exempt' => 1,
            ]);
        }

        // Register module
        $exists = ee()->db
            ->where('module_name', 'Event_calendar')
            ->count_all_results('exp_modules');
        if (!$exists) {
            ee()->db->insert('exp_modules', [
                'module_name'        => 'Event_calendar',
                'module_version'     => $this->version,
                'has_cp_backend'     => 'y',
                'has_publish_fields' => 'n',
            ]);
        }

        // Publish theme assets
        $theme_dest = ee()->config->item('theme_folder_path') . 'user/addons/event_calendar/';
        if (!is_dir($theme_dest)) {
            mkdir($theme_dest, 0755, TRUE);
        }
        copy(__DIR__ . '/assets/calendar.js', $theme_dest . 'calendar.js');

        return TRUE;
    }

    public function uninstall()
    {
        $site_id = (int) ee()->config->item('site_id');

        // Remove module + action registrations FIRST (avoid orphaned references)
        ee()->db->delete('exp_modules', ['module_name' => 'Event_calendar']);
        ee()->db->delete('actions', [
            'class'  => 'Event_calendar',
            'method' => 'fetch_events',
        ]);

        // Drop tables in dependency order: pivot -> exceptions -> events -> settings
        ee()->db->query('DROP TABLE IF EXISTS exp_calendar_event_categories');
        ee()->db->query('DROP TABLE IF EXISTS exp_calendar_event_exceptions');
        ee()->db->query('DROP TABLE IF EXISTS exp_calendar_events');
        ee()->db->query('DROP TABLE IF EXISTS exp_event_calendar_settings');

        // Pattern A: do NOT touch exp_categories or exp_category_groups

        // Remove theme assets
        $theme_dest = ee()->config->item('theme_folder_path') . 'user/addons/event_calendar/';
        ee()->load->helper('file');
        delete_files($theme_dest, TRUE);
        @rmdir($theme_dest);

        // Clear AJAX month caches for the current site
        $months = [
            date('Y-m'),
            date('Y-m', strtotime('first day of last month')),
            date('Y-m', strtotime('first day of next month')),
        ];
        foreach ($months as $m) {
            ee()->cache->delete(
                'event_calendar/ajax_events/' . $site_id . '/' . $m,
                Cache::LOCAL_SCOPE
            );
        }

        return TRUE;
    }

    public function update($current = '')
    {
        // Greenfield v2.0 -- no upgrade path from 1.x.
        return TRUE;
    }
}
