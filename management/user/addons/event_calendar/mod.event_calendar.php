<?php

require_once __DIR__ . '/vendor/autoload.php';

use Propagate\EventCalendar\Service\RecurrenceExpander;

class Event_calendar
{
    public function __construct() {}

    // -------------------------------------------------------------------------

    public function events_list()
    {
        $site_id     = ee()->config->item('site_id');

        $param_limit = ee()->TMPL->fetch_param('limit');
        $limit       = ($param_limit !== FALSE && (int) $param_limit > 0)
            ? (int) $param_limit
            : 5;

        $single     = ee()->TMPL->fetch_param('single',     'yes');
        $recurring  = ee()->TMPL->fetch_param('recurring',  'yes');
        $status     = ee()->TMPL->fetch_param('status',     'open');
        $var_prefix = ee()->TMPL->fetch_param('var_prefix', '');
        $prefix     = ($var_prefix !== '' && $var_prefix !== FALSE)
            ? rtrim($var_prefix, ':') . ':'
            : '';
        $now        = ee()->localize->now;
        $far_future = strtotime('+5 years', $now);

        // Month filtering — defaults to current month
        $month_param = ee()->TMPL->fetch_param('month', 'current');
        if (
            $month_param === 'current' || $month_param === FALSE || $month_param === '' ||
            !preg_match('/^(\d{4})-(\d{2})$/', $month_param, $mp) ||
            (int) $mp[2] < 1 || (int) $mp[2] > 12
        ) {
            $filter_year  = (int) date('Y', $now);
            $filter_month = (int) date('n', $now);
        } else {
            $filter_year  = (int) $mp[1];
            $filter_month = (int) $mp[2];
        }

        // Optional category filter: pipe-separated cat_url_title values
        $category_param = ee()->TMPL->fetch_param('category', '');
        $filter_cat_ids = [];
        if ($category_param !== '' && $category_param !== FALSE) {
            $url_titles = array_filter(array_map('trim', explode('|', $category_param)));
            if (!empty($url_titles)) {
                $cat_rows = ee()->db
                    ->select('cat_id')
                    ->where('site_id', $site_id)
                    ->where_in('cat_url_title', $url_titles)
                    ->get('categories')
                    ->result_array();
                $filter_cat_ids = array_map(fn($r) => (int) $r['cat_id'], $cat_rows);
            }
        }

        ee()->db->select('id, title, description, start_time, end_time, rrule,
                          recurrence_end, status,
                          IF(rrule IS NULL, "single", "recurring") AS event_type', FALSE)
                ->where('site_id', $site_id);

        if ($status !== 'any') {
            ee()->db->where('status', $status);
        }

        // Apply category filter at the DB level when possible
        if (!empty($filter_cat_ids)) {
            ee()->db->join('exp_calendar_event_categories ec_list', 'ec_list.event_id = exp_calendar_events.id', 'inner')
                    ->where_in('ec_list.cat_id', $filter_cat_ids);
        }

        $rows = ee()->db->order_by('start_time', 'ASC')->get('exp_calendar_events')->result_array();

        $results = [];
        $expander = new RecurrenceExpander();

        foreach ($rows as $row) {
            $is_recurring = $row['rrule'] !== null && $row['rrule'] !== '';

            if ($is_recurring) {
                if ($recurring !== 'yes') continue;
                $results[] = $row;
            } else {
                if ($single !== 'yes') continue;
                $ev_year  = (int) date('Y', (int) $row['start_time']);
                $ev_month = (int) date('n', (int) $row['start_time']);
                if ($ev_year !== $filter_year || $ev_month !== $filter_month) continue;
                $results[] = $row;
            }
        }

        usort($results, fn($a, $b) => (int) $a['start_time'] - (int) $b['start_time']);
        $results = array_slice($results, 0, $limit);

        if (empty($results)) {
            return ee()->TMPL->no_results();
        }

        // Batch-load categories for all result events
        $result_event_ids = array_unique(array_column($results, 'id'));
        $cat_map = [];
        if (!empty($result_event_ids)) {
            $cat_rows = ee()->db
                ->select('ec.event_id, c.cat_id, c.cat_name, c.cat_url_title', FALSE)
                ->from('exp_calendar_event_categories ec')
                ->join('exp_categories c', 'c.cat_id = ec.cat_id', 'inner')
                ->where_in('ec.event_id', $result_event_ids)
                ->order_by('c.cat_order', 'ASC')
                ->get()
                ->result_array();
            foreach ($cat_rows as $r) {
                $cat_map[(int) $r['event_id']][] = [
                    'cat_id'        => (int) $r['cat_id'],
                    'cat_name'      => htmlspecialchars($r['cat_name']),
                    'cat_url_title' => $r['cat_url_title'],
                ];
            }
        }

        $variables = [];
        foreach ($results as $event) {
            $start_ts   = (int) $event['start_time'];
            $end_ts     = (int) $event['end_time'];
            $day_n      = (int) date('j', $start_ts);
            $ord_sfx    = in_array($day_n % 100, [11, 12, 13]) ? 'th'
                        : (['st', 'nd', 'rd'][$day_n % 10 - 1] ?? 'th');
            $date_line  = date('l', $start_ts) . ', ' . date('F', $start_ts) . ' ' . $day_n . $ord_sfx
                        . ', ' . ee()->localize->format_date('%g:%i %A', $start_ts)
                        . ' – '  . ee()->localize->format_date('%g:%i %A', $end_ts);

            $row = [
                'event_id'        => (int) $event['id'],
                'event_type'      => $event['event_type'],
                'title'           => htmlspecialchars($event['title']),
                'description'     => strip_tags(ee()->typography->auto_typography($event['description'])),
                'date_key'        => date('Y-m-d', $start_ts),
                'day_num'         => $day_n,
                'date_line'       => htmlspecialchars($date_line),
                'start_time'      => $start_ts,
                'end_time'        => $end_ts,
                'start_time_raw'  => $start_ts,
                'end_time_raw'    => $end_ts,
                'status'          => $event['status'],
                'day_of_week'     => date('l', $start_ts),
                'day_of_week_int' => (int) date('w', $start_ts),
                'categories'      => $cat_map[(int) $event['id']] ?? [],
            ];

            if ($prefix !== '') {
                $prefixed = [];
                foreach ($row as $k => $v) {
                    $prefixed[$prefix . $k] = $v;
                }
                $variables[] = $prefixed;
            } else {
                $variables[] = $row;
            }
        }

        return ee()->TMPL->parse_variables(ee()->TMPL->tagdata, $variables);
    }

    // -------------------------------------------------------------------------

    public function upcoming_months()
    {
        $site_id   = ee()->config->item('site_id');

        $param_limit = ee()->TMPL->fetch_param('limit');
        $limit       = ($param_limit !== FALSE && (int) $param_limit > 0)
            ? (int) $param_limit
            : 5;

        $single    = ee()->TMPL->fetch_param('single',    'yes');
        $recurring = ee()->TMPL->fetch_param('recurring', 'yes');
        $status    = ee()->TMPL->fetch_param('status',    'open');
        $separator = ee()->TMPL->fetch_param('separator', ' / ');
        $now       = ee()->localize->now;
        $far_future = strtotime('+5 years', $now);

        $category_param = ee()->TMPL->fetch_param('category', '');
        $filter_cat_ids = [];
        if ($category_param !== '' && $category_param !== FALSE) {
            $url_titles = array_filter(array_map('trim', explode('|', $category_param)));
            if (!empty($url_titles)) {
                $cat_rows = ee()->db
                    ->select('cat_id')
                    ->where('site_id', $site_id)
                    ->where_in('cat_url_title', $url_titles)
                    ->get('categories')
                    ->result_array();
                $filter_cat_ids = array_map(fn($r) => (int) $r['cat_id'], $cat_rows);
            }
        }

        ee()->db->select('id, start_time, end_time, rrule, recurrence_end, status', FALSE)
                ->where('site_id', $site_id);

        if ($status !== 'any') {
            ee()->db->where('status', $status);
        }

        if (!empty($filter_cat_ids)) {
            ee()->db->join('exp_calendar_event_categories ec_um', 'ec_um.event_id = exp_calendar_events.id', 'inner')
                    ->where_in('ec_um.cat_id', $filter_cat_ids);
        }

        $rows     = ee()->db->order_by('start_time', 'ASC')->get('exp_calendar_events')->result_array();
        $expander = new RecurrenceExpander();
        $starts   = [];

        foreach ($rows as $row) {
            $is_recurring = $row['rrule'] !== null && $row['rrule'] !== '';

            if ($is_recurring) {
                if ($recurring !== 'yes') continue;

                $occurrences = $expander->expand(
                    $row['rrule'],
                    (int) $row['start_time'],
                    $now,
                    $far_future,
                    $limit
                );
                foreach ($occurrences as $occ_start) {
                    $starts[] = $occ_start;
                }
            } else {
                if ($single !== 'yes') continue;
                if ((int) $row['start_time'] < $now) continue;
                $starts[] = (int) $row['start_time'];
            }
        }

        sort($starts);
        $starts = array_slice($starts, 0, $limit);

        if (empty($starts)) {
            return '';
        }

        $months = [];
        foreach ($starts as $ts) {
            $key = date('n', $ts);
            if (!isset($months[$key])) {
                $months[$key] = strtoupper(date('F', $ts));
            }
        }

        return implode($separator, $months);
    }

    // -------------------------------------------------------------------------

    public function display()
    {
        $site_id     = (int) ee()->config->item('site_id');
        $date_format = ee()->TMPL->fetch_param('date_format', '%F %j, %Y');
        $time_format = ee()->TMPL->fetch_param('time_format', '%g:%i %A');
        $fmt         = $date_format . ' - ' . $time_format;

        $single    = ee()->TMPL->fetch_param('single',    'yes');
        $recurring = ee()->TMPL->fetch_param('recurring', 'yes');
        $status    = ee()->TMPL->fetch_param('status',    'open');
        $show_meta = ee()->TMPL->fetch_param('show_meta', 'yes');
        $meta_text = ee()->TMPL->fetch_param('meta_text',
            'Days in red have events or entertainment.<br>Click on a day to jump to it.');
        $now       = ee()->localize->now;

        $nonce     = ee()->functions->random('encrypt', 32);
        $nonce_key = 'event_calendar/nonce/' . $nonce;
        ee()->cache->save($nonce_key, '1', 7200, Cache::LOCAL_SCOPE);

        $action_id = ee()->functions->fetch_action_id('Event_calendar', 'fetch_events');
        $fetch_url = ee()->functions->create_url('?ACT=' . $action_id);

        $current_month = ee()->localize->format_date('%Y-%m', $now);
        $today         = ee()->localize->format_date('%Y-%m-%d', $now);

        [$year_str, $month_str] = explode('-', $current_month);
        $year  = (int) $year_str;
        $month = (int) $month_str;

        $month_start   = ee()->localize->string_to_timestamp(
            sprintf('%04d-%02d-01 00:00:00', $year, $month)
        );
        $days_in_month = (int) date('t', $month_start);
        $month_end     = ee()->localize->string_to_timestamp(
            sprintf('%04d-%02d-%02d 23:59:59', $year, $month, $days_in_month)
        );

        $events = $this->_query_events_for_range(
            $site_id, $month_start, $month_end, $status, $single, $recurring, $fmt
        );

        $events_by_date = [];
        foreach ($events as $ev) {
            $events_by_date[$ev['date_key']] = true;
        }

        $calendar_data = [
            'events'        => $events,
            'current_month' => $current_month,
            'today'         => $today,
            'fetch_url'     => $fetch_url,
            'nonce'         => $nonce,
            'single'        => $single,
            'recurring'     => $recurring,
            'status'        => $status,
        ];

        $month_names   = ['January','February','March','April','May','June',
                          'July','August','September','October','November','December'];
        $current_label = $month_names[$month - 1] . ' ' . $year;

        $grid_html = $this->_render_calendar_grid($year, $month, $events_by_date, $today);

        $meta_html = $show_meta === 'yes'
            ? '<div class="meta-calendar text-center mt-3"><p>' . $meta_text . '</p></div>'
            : '';

        $widget = '<div class="calendar-wrap">'
            . '<div class="calendar" data-calendar>'
            . '<div class="month-nav d-flex align-items-center px-3">'
            . '<div class="me-auto month text-uppercase" data-calendar-month-label>'
            . htmlspecialchars($current_label, ENT_QUOTES) . '</div>'
            . '<button class="prev border-0 fs-4 lh-1 p-0 bg-transparent mx-1 bttn" data-calendar-nav-prev>'
            . '<i class="bi bi-caret-left-fill"></i></button>'
            . '<button class="next border-0 fs-4 lh-1 p-0 bg-transparent mx-1 bttn" data-calendar-nav-next>'
            . '<i class="bi bi-caret-right-fill"></i></button>'
            . '</div>'
            . '<div class="week-days">'
            . '<div class="day">S</div><div class="day">M</div><div class="day">T</div>'
            . '<div class="day">W</div><div class="day">T</div><div class="day">F</div>'
            . '<div class="day">S</div>'
            . '</div>'
            . '<div class="days" data-calendar-grid>' . $grid_html . '</div>'
            . '</div>'
            . $meta_html
            . '</div>';

        $json   = json_encode($calendar_data, JSON_HEX_TAG);
        $js_url = ee()->config->item('theme_folder_url') . 'user/addons/event_calendar/calendar.js';

        return '<script>window.CalendarData = ' . $json . ';</script>' . "\n"
            . '<script src="' . htmlspecialchars($js_url, ENT_QUOTES) . '"></script>' . "\n"
            . $widget;
    }

    private function _render_calendar_grid(int $year, int $month, array $events_by_date, string $today): string
    {
        $days_in_month = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
        $first_dow     = (int) date('w', mktime(0, 0, 0, $month, 1, $year));

        $prev_month = $month === 1 ? 12 : $month - 1;
        $prev_year  = $month === 1 ? $year - 1 : $year;
        $prev_dim   = (int) date('t', mktime(0, 0, 0, $prev_month, 1, $prev_year));

        $next_month = $month === 12 ? 1 : $month + 1;
        $next_year  = $month === 12 ? $year + 1 : $year;

        $html = '';

        for ($p = $first_dow - 1; $p >= 0; $p--) {
            $d        = $prev_dim - $p;
            $date_key = sprintf('%04d-%02d-%02d', $prev_year, $prev_month, $d);
            $html .= '<div class="day faded" data-calendar-day="" data-date="'
                . $date_key . '" data-other-month="">' . $d . '</div>';
        }

        for ($day = 1; $day <= $days_in_month; $day++) {
            $date_key = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $classes  = 'day';
            $extra    = '';

            if ($date_key === $today) {
                $classes .= ' today';
                $extra   .= ' data-today=""';
            }

            if (!empty($events_by_date[$date_key])) {
                $classes .= ' event-day';
                $extra   .= ' data-has-events=""';
            }

            $html .= '<div class="' . $classes . '" data-calendar-day="" data-date="'
                . $date_key . '"' . $extra . '>' . $day . '</div>';
        }

        $total_cells = $first_dow + $days_in_month;
        $trailing    = $total_cells % 7 === 0 ? 0 : 7 - ($total_cells % 7);
        for ($n = 1; $n <= $trailing; $n++) {
            $date_key = sprintf('%04d-%02d-%02d', $next_year, $next_month, $n);
            $html .= '<div class="day faded" data-calendar-day="" data-date="'
                . $date_key . '" data-other-month="">' . $n . '</div>';
        }

        return $html;
    }

    // -------------------------------------------------------------------------

    public function fetch_events()
    {
        $site_id = ee()->config->item('site_id');

        $request_nonce = (string) ee()->input->server('HTTP_X_CALENDAR_NONCE');
        $nonce_valid   = false;
        if ($request_nonce !== '') {
            $nonce_key   = 'event_calendar/nonce/' . $request_nonce;
            $nonce_valid = ee()->cache->get($nonce_key, Cache::LOCAL_SCOPE) !== FALSE;
        }
        if (!$nonce_valid) {
            $this->_send_json(json_encode(['success' => false, 'message' => 'Unauthorized'], JSON_HEX_TAG), 403);
        }

        $month = (string) ee()->input->post('month');
        if (strlen($month) !== 7 || !preg_match('/^\d{4}-\d{2}$/', $month)) {
            $this->_send_json(json_encode(['success' => false, 'message' => 'Invalid request'], JSON_HEX_TAG), 400);
        }
        $month_num_check = (int) substr($month, 5, 2);
        if ($month_num_check < 1 || $month_num_check > 12) {
            $this->_send_json(json_encode(['success' => false, 'message' => 'Invalid request'], JSON_HEX_TAG), 400);
        }

        ee()->load->library('typography');

        $cache_key = 'event_calendar/ajax_events/' . $site_id . '/' . $month;
        $cached    = ee()->cache->get($cache_key, Cache::LOCAL_SCOPE);
        if ($cached !== FALSE) {
            $this->_send_json($cached);
        }

        [$year, $month_num] = explode('-', $month);
        $month_start   = ee()->localize->string_to_timestamp(
            $year . '-' . $month_num . '-01 00:00:00'
        );
        $days_in_month = date('t', $month_start);
        $month_end     = ee()->localize->string_to_timestamp(
            $year . '-' . $month_num . '-' . $days_in_month . ' 23:59:59'
        );

        $fmt    = '%F %j, %Y - %g:%i %A';
        $events = $this->_query_events_for_range($site_id, $month_start, $month_end, 'open', 'yes', 'yes', $fmt);

        $json = json_encode(['success' => true, 'events' => $events], JSON_HEX_TAG);
        ee()->cache->save($cache_key, $json, 3600, Cache::LOCAL_SCOPE);
        $this->_send_json($json);
    }

    private function _send_json(string $json, int $status = 200): never
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code($status);
        header('Content-Type: application/json');
        echo $json;
        exit();
    }

    // -------------------------------------------------------------------------

    private function _query_events_for_range(
        $site_id, $month_start, $month_end, $status, $single, $recurring, $fmt
    ): array {
        ee()->db->select('id, title, description, start_time, end_time, rrule, recurrence_end, status', FALSE)
                ->where('site_id', $site_id)
                ->where('start_time <=', $month_end)
                ->where('(rrule IS NULL OR recurrence_end IS NULL OR recurrence_end >= ' . (int) $month_start . ')', NULL, FALSE);

        if ($status !== 'any') {
            ee()->db->where('status', $status);
        }

        $rows     = ee()->db->get('exp_calendar_events')->result_array();
        $expander = new RecurrenceExpander();
        $events   = [];

        foreach ($rows as $row) {
            $is_recurring = $row['rrule'] !== null && $row['rrule'] !== '';

            if ($is_recurring) {
                if ($recurring !== 'yes') continue;

                $occurrences = $expander->expand(
                    $row['rrule'],
                    (int) $row['start_time'],
                    $month_start,
                    $month_end
                );
                $duration = (int) $row['end_time'] - (int) $row['start_time'];
                foreach ($occurrences as $occ_start) {
                    $events[] = [
                        'id'             => (int) $row['id'],
                        'title'          => htmlspecialchars($row['title']),
                        'description'    => ee()->typography->auto_typography($row['description']),
                        'start_time'     => ee()->localize->format_date($fmt, $occ_start),
                        'end_time'       => ee()->localize->format_date($fmt, $occ_start + $duration),
                        'start_time_raw' => $occ_start,
                        'end_time_raw'   => $occ_start + $duration,
                        'date_key'       => ee()->localize->format_date('%Y-%m-%d', $occ_start),
                        'event_type'     => 'recurring',
                        'day_of_week'    => (int) date('w', $occ_start),
                    ];
                }
            } else {
                if ($single !== 'yes') continue;
                if ((int) $row['start_time'] < $month_start) continue;

                $events[] = [
                    'id'             => (int) $row['id'],
                    'title'          => htmlspecialchars($row['title']),
                    'description'    => ee()->typography->auto_typography($row['description']),
                    'start_time'     => ee()->localize->format_date($fmt, $row['start_time']),
                    'end_time'       => ee()->localize->format_date($fmt, $row['end_time']),
                    'start_time_raw' => (int) $row['start_time'],
                    'end_time_raw'   => (int) $row['end_time'],
                    'date_key'       => ee()->localize->format_date('%Y-%m-%d', $row['start_time']),
                    'event_type'     => 'single',
                ];
            }
        }

        usort($events, fn($a, $b) => $a['start_time_raw'] - $b['start_time_raw']);
        return $events;
    }
}
