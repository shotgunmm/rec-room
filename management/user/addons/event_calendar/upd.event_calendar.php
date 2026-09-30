<?php

class Event_calendar_upd
{
    public $version = '2.6.0';

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
            short_description MEDIUMTEXT NULL DEFAULT NULL,
            event_details   MEDIUMTEXT NULL DEFAULT NULL,
            location        VARCHAR(255) NOT NULL DEFAULT '',
            url             VARCHAR(512) NOT NULL DEFAULT '',
            banner_image    VARCHAR(512) NOT NULL DEFAULT '',
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
            site_id           INT(5)   UNSIGNED NOT NULL DEFAULT 1,
            cat_group_id      INT(4)   UNSIGNED NULL DEFAULT NULL,
            color_field_id    INT(6)   UNSIGNED NULL DEFAULT NULL,
            default_view      VARCHAR(10) NOT NULL DEFAULT 'month',
            calendar_page_url VARCHAR(512) NOT NULL DEFAULT '',
            url_style         VARCHAR(10) NOT NULL DEFAULT 'clean',
            created_at        INT(10)  UNSIGNED NOT NULL,
            updated_at        INT(10)  UNSIGNED NOT NULL,
            PRIMARY KEY (site_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Seed a settings row for the current site
        $now = ee()->localize->now;
        ee()->db->insert('event_calendar_settings', [
            'site_id'    => (int) ee()->config->item('site_id'),
            'url_style'  => 'clean',
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

        $this->_install_template();
        $this->_ensure_loose_urls('clean');

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

        $this->_uninstall_template();

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
        if (version_compare($current, '2.1.0', '<')) {
            $cols = ee()->db->query(
                "SHOW COLUMNS FROM exp_event_calendar_settings LIKE 'calendar_page_url'"
            );
            if ($cols->num_rows() === 0) {
                ee()->db->query(
                    "ALTER TABLE exp_event_calendar_settings
                     ADD COLUMN calendar_page_url VARCHAR(512) NOT NULL DEFAULT ''
                     AFTER default_view"
                );
            }
        }

        if (version_compare($current, '2.2.0', '<')) {
            $this->_ensure_loose_urls('clean');
            $this->_backfill_slugs();
        }

        if (version_compare($current, '2.3.0', '<')) {
            $cols = ee()->db->query(
                "SHOW COLUMNS FROM exp_event_calendar_settings LIKE 'url_style'"
            );
            if ($cols->num_rows() === 0) {
                ee()->db->query(
                    "ALTER TABLE exp_event_calendar_settings
                     ADD COLUMN url_style VARCHAR(10) NOT NULL DEFAULT 'clean'
                     AFTER calendar_page_url"
                );
            }
        }

        if (version_compare($current, '2.3.1', '<')) {
            $this->_backfill_slugs();
        }

        if (version_compare($current, '2.3.2', '<')) {
            $cols = ee()->db->query(
                "SHOW COLUMNS FROM exp_event_calendar_settings LIKE 'url_style'"
            );
            if ($cols->num_rows() === 0) {
                ee()->db->query(
                    "ALTER TABLE exp_event_calendar_settings
                     ADD COLUMN url_style VARCHAR(10) NOT NULL DEFAULT 'clean'
                     AFTER calendar_page_url"
                );
            }
        }

        if (version_compare($current, '2.3.3', '<')) {
            // Drop old image VARCHAR column if it was added before this fix
            $old = ee()->db->query("SHOW COLUMNS FROM exp_calendar_events LIKE 'image'");
            if ($old->num_rows() > 0) {
                ee()->db->query("ALTER TABLE exp_calendar_events DROP COLUMN image");
            }
            // Add the correct image_file_id INT column
            $col = ee()->db->query("SHOW COLUMNS FROM exp_calendar_events LIKE 'image_file_id'");
            if ($col->num_rows() === 0) {
                ee()->db->query(
                    "ALTER TABLE exp_calendar_events
                     ADD COLUMN image_file_id INT(10) UNSIGNED NULL DEFAULT NULL
                     AFTER url"
                );
            }
        }

        if (version_compare($current, '2.3.4', '<')) {
            $old_col = ee()->db->query("SHOW COLUMNS FROM exp_calendar_events LIKE 'image_file_id'");
            if ($old_col->num_rows() > 0) {
                ee()->db->query("ALTER TABLE exp_calendar_events DROP COLUMN image_file_id");
            }
            $new_col = ee()->db->query("SHOW COLUMNS FROM exp_calendar_events LIKE 'image'");
            if ($new_col->num_rows() === 0) {
                ee()->db->query(
                    "ALTER TABLE exp_calendar_events
                     ADD COLUMN image VARCHAR(512) NOT NULL DEFAULT ''
                     AFTER url"
                );
            }
        }

        if (version_compare($current, '2.4.0', '<')) {
            $col = ee()->db->query("SHOW COLUMNS FROM exp_calendar_events LIKE 'event_details'");
            if ($col->num_rows() === 0) {
                ee()->db->query(
                    "ALTER TABLE exp_calendar_events
                     ADD COLUMN event_details MEDIUMTEXT NULL DEFAULT NULL
                     AFTER description"
                );
            }
        }

        if (version_compare($current, '2.5.0', '<')) {
            $new_col = ee()->db->query("SHOW COLUMNS FROM exp_calendar_events LIKE 'short_description'");
            if ($new_col->num_rows() === 0) {
                $old_col = ee()->db->query("SHOW COLUMNS FROM exp_calendar_events LIKE 'description'");
                if ($old_col->num_rows() > 0) {
                    ee()->db->query(
                        "ALTER TABLE exp_calendar_events
                         CHANGE COLUMN description short_description MEDIUMTEXT NULL DEFAULT NULL"
                    );
                } else {
                    ee()->db->query(
                        "ALTER TABLE exp_calendar_events
                         ADD COLUMN short_description MEDIUMTEXT NULL DEFAULT NULL
                         AFTER slug"
                    );
                }
            }

            $this->_update_event_detail_template();
        }

        if (version_compare($current, '2.6.0', '<')) {
            $new_col = ee()->db->query("SHOW COLUMNS FROM exp_calendar_events LIKE 'banner_image'");
            if ($new_col->num_rows() === 0) {
                $old_col = ee()->db->query("SHOW COLUMNS FROM exp_calendar_events LIKE 'image'");
                if ($old_col->num_rows() > 0) {
                    ee()->db->query(
                        "ALTER TABLE exp_calendar_events
                         CHANGE COLUMN image banner_image VARCHAR(512) NOT NULL DEFAULT ''"
                    );
                } else {
                    ee()->db->query(
                        "ALTER TABLE exp_calendar_events
                         ADD COLUMN banner_image VARCHAR(512) NOT NULL DEFAULT ''
                         AFTER url"
                    );
                }
            }

            $this->_update_event_detail_template();
        }

        return TRUE;
    }

    // -------------------------------------------------------------------------

    private function _template_dir(): string
    {
        $site_short = ee()->db
            ->select('site_name')
            ->where('site_id', (int) ee()->config->item('site_id'))
            ->get('exp_sites')
            ->row_array()['site_name'] ?? 'default_site';

        return rtrim(ee()->config->item('tmpl_file_basepath'), '/\\')
            . DIRECTORY_SEPARATOR . $site_short
            . DIRECTORY_SEPARATOR . 'event-detail.group'
            . DIRECTORY_SEPARATOR;
    }

    private function _install_template(): void
    {
        $site_id = (int) ee()->config->item('site_id');

        // Write template file
        $dir = $this->_template_dir();
        if (!is_dir($dir)) {
            mkdir($dir, 0755, TRUE);
        }
        if (!file_exists($dir . 'index.html')) {
            file_put_contents($dir . 'index.html', $this->_event_detail_template());
        }

        // Register template group in DB (guard against duplicate)
        $group_exists = ee()->db
            ->where('group_name', 'event-detail')
            ->where('site_id', $site_id)
            ->count_all_results('exp_template_groups');

        if (!$group_exists) {
            ee()->db->insert('exp_template_groups', [
                'group_name'      => 'event-detail',
                'group_order'     => 999,
                'is_site_default' => 'n',
                'site_id'         => $site_id,
            ]);
            $group_id = ee()->db->insert_id();

            ee()->db->insert('exp_templates', [
                'group_id'           => $group_id,
                'template_name'      => 'index',
                'template_type'      => 'webpage',
                'template_data'      => $this->_event_detail_template(),
                'edit_date'          => ee()->localize->now,
                'last_author_id'     => 1,
                'cache'              => 'n',
                'refresh'            => 0,
                'hits'               => 0,
                'allow_php'          => 'n',
                'protect_javascript' => 'n',
                'site_id'            => $site_id,
            ]);
            $template_id = ee()->db->insert_id();

            // Grant access to all roles so the template is publicly viewable
            $roles = ee()->db->select('role_id')->get('exp_roles')->result_array();
            foreach ($roles as $role) {
                ee()->db->insert('exp_templates_roles', [
                    'template_id' => $template_id,
                    'role_id'     => $role['role_id'],
                ]);
            }
        }
    }

    private function _uninstall_template(): void
    {
        $site_id = (int) ee()->config->item('site_id');

        $group = ee()->db
            ->where('group_name', 'event-detail')
            ->where('site_id', $site_id)
            ->get('exp_template_groups')
            ->row_array();

        if ($group) {
            $tmpl_ids = ee()->db->select('template_id')
                ->where('group_id', $group['group_id'])
                ->get('exp_templates')
                ->result_array();
            foreach ($tmpl_ids as $t) {
                ee()->db->where('template_id', $t['template_id'])->delete('exp_templates_roles');
            }
            ee()->db->where('group_id', $group['group_id'])->delete('exp_templates');
            ee()->db->where('group_id', $group['group_id'])->delete('exp_template_groups');
        }

        // Remove template file and directory
        $dir = $this->_template_dir();
        if (is_dir($dir)) {
            ee()->load->helper('file');
            delete_files($dir, TRUE);
            @rmdir($dir);
        }
    }

    private function _ensure_loose_urls(string $url_style = 'clean'): void
    {
        $site_id    = (int) ee()->config->item('site_id');
        $exp_value  = $url_style === 'index' ? 'y' : 'n';

        $exists = ee()->db
            ->where('site_id', $site_id)
            ->where('`key`', 'strict_urls')
            ->count_all_results('exp_config');

        if ($exists) {
            ee()->db
                ->where('site_id', $site_id)
                ->where('`key`', 'strict_urls')
                ->update('exp_config', ['value' => $exp_value]);
        } else {
            ee()->db->insert('exp_config', [
                'site_id' => $site_id,
                'key'     => 'strict_urls',
                'value'   => $exp_value,
            ]);
        }
    }

    private function _update_event_detail_template(): void
    {
        $site_id  = (int) ee()->config->item('site_id');
        $content  = $this->_event_detail_template();

        // Update filesystem file
        $file = $this->_template_dir() . 'index.html';
        if (file_exists($file)) {
            file_put_contents($file, $content);
        }

        // Update DB record
        $group = ee()->db
            ->where('group_name', 'event-detail')
            ->where('site_id', $site_id)
            ->get('exp_template_groups')
            ->row_array();

        if ($group) {
            ee()->db
                ->where('group_id', $group['group_id'])
                ->where('template_name', 'index')
                ->update('exp_templates', ['template_data' => $content]);
        }
    }

    private function _backfill_slugs(): void
    {
        $site_id = (int) ee()->config->item('site_id');

        $rows = ee()->db
            ->select('id, title')
            ->where('site_id', $site_id)
            ->where('slug', '')
            ->get('exp_calendar_events')
            ->result_array();

        if (empty($rows)) {
            return;
        }

        $used = ee()->db
            ->select('slug')
            ->where('site_id', $site_id)
            ->where('slug !=', '')
            ->get('exp_calendar_events')
            ->result_array();
        $taken = array_column($used, 'slug');
        $taken = array_flip($taken);

        foreach ($rows as $row) {
            $base = $this->_slugify_title($row['title']);
            $slug = $base;
            $n    = 2;
            while (isset($taken[$slug])) {
                $slug = $base . '-' . $n++;
            }
            $taken[$slug] = true;
            ee()->db->update(
                'exp_calendar_events',
                ['slug' => $slug],
                ['id' => (int) $row['id'], 'site_id' => $site_id]
            );
        }
    }

    private function _slugify_title(string $title): string
    {
        $slug = strtolower($title);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug !== '' ? $slug : 'event';
    }

    private function _event_detail_template(): string
    {
        return <<<'EETEMPLATE'
{head}
{navigation}

{exp:event_calendar:event_detail}
<section class="sec fade-in py-5">
  <div class="container">

    <p class="mt-4"><a href="{calendar_page_url}" onclick="if(history.length>1){history.back();return false;}">&larr; Back to Calendar</a></p>

    <div class="row">
      <div class="col-md-6">

        <h1>{title}</h1>

        <p class="date mb-1">{day_of_week}</p>
        <p class="date mb-1">{start_date}</p>
        <p class="mb-3">{start_time} &ndash; {end_time}</p>

        {if location}
        <p class="mb-3"><strong>Location:</strong> {location}</p>
        {/if}

        {if short_description}
        <div class="mt-4">
          <h3>Short Description</h3>
          {short_description}
        </div>
        {/if}

        {if event_details}
        <div class="mt-4">
          <h3>Event Details</h3>
          {event_details}
        </div>
        {/if}

        {if event_url}
        <div class="mt-4">
          <h3>Additional Information</h3>
          {if event_url_is_external == "y"}
          <p><a href="{event_url}" target="_blank" rel="noopener noreferrer">{event_url}</a></p>
          {if:else}
          <p><a href="{event_url}">{event_url}</a></p>
          {/if}
        </div>
        {/if}

      </div>
      <div class="col-md-6">

        {if banner_image}
        <img src="{banner_image}" alt="{title}" class="img-fluid">
        {/if}

      </div>
    </div>

  </div>
</section>
{/exp:event_calendar:event_detail}

{footer}
EETEMPLATE;
    }
}
