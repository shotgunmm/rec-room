<?php

use ExpressionEngine\Library\Date\DateTrait;

class Event_calendar_mcp
{
    use DateTrait;

    private $base_url;

    public function __construct()
    {
        $this->base_url = ee('CP/URL')->make('addons/settings/event_calendar');
        ee()->lang->loadfile('event_calendar');
    }

    // -------------------------------------------------------------------------

    private function _make_sidebar($active = 'index')
    {
        $list_url             = ee('CP/URL')->make('addons/settings/event_calendar');
        $create_url           = ee('CP/URL')->make('addons/settings/event_calendar/create');
        $create_recurring_url = ee('CP/URL')->make('addons/settings/event_calendar/create_recurring');
        $settings_url         = ee('CP/URL')->make('addons/settings/event_calendar/setup_category_group');

        $sidebar     = ee('CP/Sidebar')->make();
        $events_list = $sidebar->addHeader(lang('events'))->addBasicList();

        $list_item = $events_list->addItem(lang('all_events'), $list_url);
        if ($active === 'index') {
            $list_item->isActive();
        }

        $create_item = $events_list->addItem(lang('add_single_event'), $create_url);
        if ($active === 'create') {
            $create_item->isActive();
        }

        $create_rec_item = $events_list->addItem(lang('add_recurring_event'), $create_recurring_url);
        if ($active === 'create_recurring') {
            $create_rec_item->isActive();
        }

        $settings_list  = $sidebar->addHeader(lang('settings'))->addBasicList();
        $settings_item  = $settings_list->addItem(lang('category_group'), $settings_url);
        if ($active === 'setup_category_group') {
            $settings_item->isActive();
        }

        $templates_url  = ee('CP/URL')->make('addons/settings/event_calendar/templates');
        $templates_item = $settings_list->addItem(lang('templates'), $templates_url);
        if ($active === 'templates') {
            $templates_item->isActive();
        }
    }

    // -------------------------------------------------------------------------

    private function _setup_form()
    {
        $this->addDatePickerScript();
        ee()->javascript->set_global(
            'date.date_format',
            ee()->localize->get_date_format(false, true)
        );
        ee()->javascript->set_global(
            'date.include_seconds',
            ee()->session->userdata('include_seconds', ee()->config->item('include_seconds'))
        );
        ee()->javascript->set_global(
            'date.time_format',
            ee()->session->userdata('time_format', ee()->config->item('time_format'))
        );
        ee()->cp->add_to_head('<style>
            .field-instruct label:last-child{margin-bottom:5px}
            input[type=time]{display:block;width:100%;padding:8px 15px;font-size:1rem;line-height:1.6;color:var(--ee-input-color);background-color:var(--ee-input-bg);background-image:none;transition:border-color 200ms ease,box-shadow 200ms ease;-webkit-appearance:none;border:1px solid var(--ee-input-border);border-radius:5px;box-shadow:0 1px 2px 0 var(--ee-shadow-input)}
            input[type=time]:focus{border-color:var(--ee-input-focus-border);outline:none;box-shadow:0 0 0 2px var(--ee-accent-medium)}
        </style>');
    }

    // -------------------------------------------------------------------------

    private function _parse_hhmm(string $value): int|false
    {
        if (!preg_match('/^(\d{2}):(\d{2})$/', $value, $m)) {
            return false;
        }
        $h = (int) $m[1];
        $i = (int) $m[2];
        if ($h > 23 || $i > 59) {
            return false;
        }
        return mktime($h, $i, 0, 1, 1, 1970);
    }

    // -------------------------------------------------------------------------

    private function _bust_event_cache()
    {
        $site_id = ee()->config->item('site_id');

        $months = [
            date('Y-m'),
            date('Y-m', strtotime('first day of last month')),
            date('Y-m', strtotime('first day of next month')),
        ];
        foreach ($months as $m) {
            ee()->cache->delete(
                'event_calendar_ajax_events_' . $site_id . '_' . $m,
                Cache::LOCAL_SCOPE
            );
        }
    }

    // -------------------------------------------------------------------------

    private function _load_settings(): array
    {
        $site_id = ee()->config->item('site_id');
        $row     = ee()->db->where('site_id', $site_id)->get('event_calendar_settings')->row_array();
        return $row ?: ['cat_group_id' => null, 'color_field_id' => null];
    }

    // -------------------------------------------------------------------------

    private function _require_category_group(): void
    {
        $settings = $this->_load_settings();
        if (empty($settings['cat_group_id'])) {
            ee()->functions->redirect(
                ee('CP/URL')->make('addons/settings/event_calendar/setup_category_group')->compile()
            );
        }
    }

    // -------------------------------------------------------------------------

    private function _load_categories(int $groupId): array
    {
        $site_id = ee()->config->item('site_id');
        return ee()->db
            ->select('cat_id, cat_name, cat_url_title')
            ->where('group_id', $groupId)
            ->where('site_id', $site_id)
            ->order_by('cat_order', 'ASC')
            ->get('categories')
            ->result_array();
    }

    // -------------------------------------------------------------------------

    private function _load_selected_cat_ids(int $eventId): array
    {
        $rows = ee()->db
            ->select('cat_id')
            ->where('event_id', $eventId)
            ->get('calendar_event_categories')
            ->result_array();
        return array_map(fn($r) => (int) $r['cat_id'], $rows);
    }

    // -------------------------------------------------------------------------

    private function _save_category_ids(int $eventId, array $catIds, int $groupId): void
    {
        ee()->db->where('event_id', $eventId)->delete('calendar_event_categories');
        if (empty($catIds)) {
            return;
        }

        // Only keep IDs that actually belong to the configured group
        $valid = ee()->db
            ->select('cat_id')
            ->where('group_id', $groupId)
            ->where_in('cat_id', $catIds)
            ->get('categories')
            ->result_array();
        $validIds = array_map(fn($r) => (int) $r['cat_id'], $valid);

        if (!empty($validIds)) {
            $rows = array_map(fn($cid) => ['event_id' => $eventId, 'cat_id' => $cid], $validIds);
            ee()->db->insert_batch('calendar_event_categories', $rows);
        }
    }

    // -------------------------------------------------------------------------

    public function setup_category_group()
    {
        $site_id  = ee()->config->item('site_id');
        $settings = $this->_load_settings();

        $groups = ee()->db
            ->select('group_id, group_name')
            ->where('site_id', $site_id)
            ->order_by('group_name', 'ASC')
            ->get('category_groups')
            ->result_array();

        $errors = [];

        if (ee()->input->post('submit')) {
            $group_id  = (int) ee()->input->post('cat_group_id');
            $valid_ids = array_map(fn($g) => (int) $g['group_id'], $groups);

            if (!in_array($group_id, $valid_ids, true)) {
                $errors[] = lang('invalid_category_group');
            }

            if (empty($errors)) {
                ee()->db->update(
                    'event_calendar_settings',
                    ['cat_group_id' => $group_id, 'updated_at' => ee()->localize->now],
                    ['site_id' => $site_id]
                );

                ee('CP/Alert')->makeInline('event-calendar-success')
                    ->asSuccess()
                    ->withTitle(lang('category_group_saved'))
                    ->defer();

                ee()->functions->redirect($this->base_url->compile());
            }
        }

        ee()->view->cp_page_title = lang('calendar_module_name') . ' - ' . lang('setup_category_group_title');
        ee()->cp->set_breadcrumb($this->base_url->compile(), lang('calendar_module_name'));
        $this->_make_sidebar('setup_category_group');

        return [
            'body'       => ee('View')->make('event_calendar:events/setup_category_group')->render([
                'errors'           => $errors,
                'groups'           => $groups,
                'current_group_id' => (int) ($settings['cat_group_id'] ?? 0),
                'create_group_url' => ee('CP/URL')->make('categories/group/create')->compile(),
                'form_url'         => ee('CP/URL')->make('addons/settings/event_calendar/setup_category_group'),
            ]),
            'heading'    => lang('setup_category_group_title'),
            'breadcrumb' => [
                $this->base_url->compile() => lang('calendar_module_name'),
            ],
        ];
    }

    // -------------------------------------------------------------------------

    public function index()
    {
        $this->_require_category_group();

        $site_id      = ee()->config->item('site_id');
        $settings     = $this->_load_settings();
        $cat_group_id = (int) $settings['cat_group_id'];

        // Per-page
        $allowed  = [10, 25, 50, 100];
        $per_page = (int) (ee()->input->get('per_page')
            ?: ($_SESSION['calendar_per_page'] ?? null)
            ?: 10);
        $per_page = in_array($per_page, $allowed) ? $per_page : 10;
        $_SESSION['calendar_per_page'] = $per_page;

        $offset = (int) (ee()->input->get('page') ?: 0);

        // Filters
        $type_filter   = ee()->input->get('type')          ?: 'all';
        $status_filter = ee()->input->get('status_filter') ?: 'all';
        $cat_filter    = (int) (ee()->input->get('cat_id') ?: 0);

        if (!in_array($type_filter, ['all', 'single', 'recurring'])) {
            $type_filter = 'all';
        }
        if (!in_array($status_filter, ['all', 'open', 'closed'])) {
            $status_filter = 'all';
        }

        $base_url = ee('CP/URL')->make('addons/settings/event_calendar');

        ee()->cp->add_to_head('<style>
            .column-sort-header--active .column-sort--asc::after  { content: "\f107"; }
            .column-sort-header--active .column-sort--desc::after { content: "\f106"; }
        </style>');

        // Sort — map column label to qualified DB column
        $start_time_label = lang('start_time') . ' / ' . lang('day_of_week');
        $col_map = [
            lang('title')      => 'e.title',
            lang('event_type') => 'event_type',
            lang('status')     => 'e.status',
            $start_time_label  => 'e.start_time',
            lang('end_time')   => 'e.end_time',
        ];

        $sort_col_raw   = ee()->input->get('sort_col') ?: $start_time_label;
        $sort_dir       = strtolower(ee()->input->get('sort_dir') ?: 'asc');
        $sort_dir       = in_array($sort_dir, ['asc', 'desc']) ? $sort_dir : 'asc';
        $sort_db_col    = $col_map[$sort_col_raw] ?? 'e.start_time';
        $sort_col_label = array_key_exists($sort_col_raw, $col_map) ? $sort_col_raw : $start_time_label;

        // Query — use explicit table alias so JOIN doesn't cause ambiguity
        ee()->db->select('e.id, e.title, e.status, e.start_time, e.end_time, e.rrule,
                          IF(e.rrule IS NULL, "single", "recurring") AS event_type', FALSE)
                ->from('exp_calendar_events e')
                ->where('e.site_id', $site_id);

        if ($status_filter !== 'all') {
            ee()->db->where('e.status', $status_filter);
        }
        if ($type_filter === 'single') {
            ee()->db->where('e.rrule IS NULL', NULL, FALSE);
        } elseif ($type_filter === 'recurring') {
            ee()->db->where('e.rrule IS NOT NULL', NULL, FALSE);
        }
        if ($cat_filter > 0) {
            ee()->db->join('exp_calendar_event_categories ec_f', 'ec_f.event_id = e.id', 'inner')
                    ->where('ec_f.cat_id', $cat_filter);
        }

        $all_events = ee()->db->order_by($sort_db_col, strtoupper($sort_dir))->get()->result_array();

        $total      = count($all_events);
        $all_events = array_slice($all_events, $offset, $per_page);

        // Batch-load categories for the visible page of events
        $event_ids    = array_column($all_events, 'id');
        $category_map = [];
        if (!empty($event_ids)) {
            $cat_rows = ee()->db
                ->select('ec.event_id, c.cat_name', FALSE)
                ->from('exp_calendar_event_categories ec')
                ->join('exp_categories c', 'c.cat_id = ec.cat_id', 'inner')
                ->where_in('ec.event_id', $event_ids)
                ->order_by('c.cat_order', 'ASC')
                ->get()
                ->result_array();
            foreach ($cat_rows as $r) {
                $category_map[(int) $r['event_id']][] = htmlspecialchars($r['cat_name']);
            }
        }

        // Load categories for the filter dropdown
        $all_categories = $this->_load_categories($cat_group_id);

        // Build table
        $table = ee('CP/Table', ['sortable' => TRUE, 'sort_col' => $sort_col_label, 'sort_dir' => $sort_dir]);
        $table->setColumns([
            lang('title'),
            lang('event_type'),
            lang('status'),
            ['label' => $start_time_label, 'encode' => FALSE],
            ['label' => lang('end_time'), 'encode' => FALSE],
            ['label' => lang('categories'), 'encode' => FALSE, 'sort' => FALSE],
            ['label' => lang('actions'), 'encode' => FALSE, 'sort' => FALSE],
        ]);

        $table_data = [];
        foreach ($all_events as $event) {
            $end_date = ee()->localize->format_date('%F %j, %Y', $event['end_time']);
            $end_time = ee()->localize->format_date('%g:%i %A', $event['end_time']);
            $end_cell = $end_date . '<br>' . $end_time;

            if ($event['event_type'] === 'single') {
                $type_label = lang('single_event');
                $start_date = ee()->localize->format_date('%F %j, %Y', $event['start_time']);
                $start_time = ee()->localize->format_date('%g:%i %A', $event['start_time']);
                $time_col   = $start_date . '<br>' . $start_time;
            } else {
                $type_label = lang('recurring_event');
                $dow_labels = [lang('sunday'), lang('monday'), lang('tuesday'), lang('wednesday'), lang('thursday'), lang('friday'), lang('saturday')];
                $dow_label  = $dow_labels[(int) date('w', $event['start_time'])] ?? '';
                $start_date = ee()->localize->format_date('%F %j, %Y', $event['start_time']);
                $start_time = ee()->localize->format_date('%g:%i %A', $event['start_time']);
                $time_col   = $dow_label . '<br>' . $start_date . '<br>' . $start_time;
            }

            $status_label = $event['status'] === 'open' ? lang('open') : lang('closed');

            $edit_route   = $event['event_type'] === 'recurring' ? 'edit_recurring' : 'edit';
            $delete_route = $event['event_type'] === 'recurring' ? 'delete_recurring' : 'delete';
            $edit_url    = ee('CP/URL')->make('addons/settings/event_calendar/' . $edit_route   . '/' . $event['id'])->compile();
            $delete_url  = ee('CP/URL')->make('addons/settings/event_calendar/' . $delete_route . '/' . $event['id'])->compile();
            $edit_link   = '<a href="' . htmlspecialchars($edit_url)   . '" class="button button--default">' . lang('edit')   . '</a>';
            $delete_link = '<a href="' . htmlspecialchars($delete_url) . '" class="button button--danger">'  . lang('delete') . '</a>';

            $cat_names = $category_map[(int) $event['id']] ?? [];
            $cat_cell  = implode(', ', $cat_names);

            $table_data[] = [
                htmlspecialchars($event['title']),
                $type_label,
                $status_label,
                $time_col,
                $end_cell,
                $cat_cell,
                ['content' => $edit_link . '&ensp;' . $delete_link, 'encode' => FALSE],
            ];
        }

        if (empty($table_data)) {
            $table->setNoResultsText(lang('no_events'));
        }

        $table->setData($table_data);

        $pagination = ee('CP/Pagination', [
            'base_url'    => $base_url,
            'total_items' => $total,
            'per_page'    => $per_page,
            'cur_page'    => $offset,
        ]);

        ee()->view->cp_page_title = lang('calendar_module_name') . ' - ' . lang('page_all_events');
        ee()->cp->set_breadcrumb(
            $this->base_url->compile(),
            lang('calendar_module_name')
        );

        $this->_make_sidebar('index');

        return [
            'body'       => ee('View')->make('event_calendar:events/index')->render([
                'table'          => $table->viewData($base_url),
                'pagination'     => $pagination->render($base_url),
                'per_page'       => $per_page,
                'total'          => $total,
                'base_url'       => $base_url,
                'type_filter'    => $type_filter,
                'status_filter'  => $status_filter,
                'cat_filter'     => $cat_filter,
                'all_categories' => $all_categories,
            ]),
            'heading'    => lang('page_all_events'),
            'breadcrumb' => [
                $this->base_url->compile() => lang('calendar_module_name'),
            ],
        ];
    }

    // -------------------------------------------------------------------------

    public function create()
    {
        $this->_require_category_group();
        $this->_setup_form();

        $settings       = $this->_load_settings();
        $cat_group_id   = (int) $settings['cat_group_id'];
        $all_categories = $this->_load_categories($cat_group_id);

        ee()->view->cp_page_title = lang('calendar_module_name') . ' - ' . lang('page_add_event');
        ee()->cp->set_breadcrumb($this->base_url->compile(), lang('calendar_module_name'));

        $vars = [
            'errors'         => [],
            'title'          => '',
            'description'    => '',
            'start_time'     => '',
            'end_time'       => '',
            'status'         => 'open',
            'category_ids'   => [],
            'all_categories' => $all_categories,
            'form_url'       => ee('CP/URL')->make('addons/settings/event_calendar/create'),
        ];

        if (ee()->input->post('submit')) {
            $title       = strip_tags(ee()->input->post('title'));
            $description = strip_tags(ee()->input->post('description'));
            $start_raw   = ee()->input->post('start_time');
            $end_raw     = ee()->input->post('end_time');
            $start_time  = ee()->localize->string_to_timestamp($start_raw);
            $end_time    = ee()->localize->string_to_timestamp($end_raw);
            $status      = ee()->input->post('status');
            $cat_ids_raw = ee()->input->post('category_ids');
            $cat_ids     = is_array($cat_ids_raw) ? array_map('intval', $cat_ids_raw) : [];

            $errors = [];

            if ($title === '' || strlen($title) > 255) {
                $errors[] = lang('title_required');
            }
            if (!$start_time || $start_time <= 0) {
                $errors[] = lang('start_time_invalid');
            }
            if (!$end_time || $end_time <= 0) {
                $errors[] = lang('end_time_invalid');
            }
            if (empty($errors) && $end_time < $start_time) {
                $errors[] = lang('invalid_end_time');
            }
            if (!in_array($status, ['open', 'closed'])) {
                $errors[] = lang('invalid_status');
            }

            if (empty($errors)) {
                $now     = ee()->localize->now;
                $site_id = ee()->config->item('site_id');

                ee()->db->insert('exp_calendar_events', [
                    'site_id'        => $site_id,
                    'title'          => $title,
                    'slug'           => '',
                    'description'    => $description,
                    'location'       => '',
                    'url'            => '',
                    'start_time'     => $start_time,
                    'end_time'       => $end_time,
                    'all_day'        => 0,
                    'rrule'          => null,
                    'recurrence_end' => null,
                    'status'         => $status,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]);
                $new_id = (int) ee()->db->insert_id();

                $this->_save_category_ids($new_id, $cat_ids, $cat_group_id);
                $this->_bust_event_cache();

                ee('CP/Alert')->makeInline('event-calendar-success')
                    ->asSuccess()
                    ->withTitle(lang('event_created'))
                    ->defer();

                ee()->functions->redirect($this->base_url->compile());
            }

            $vars['errors']       = $errors;
            $vars['title']        = htmlspecialchars(ee()->input->post('title'));
            $vars['description']  = htmlspecialchars(ee()->input->post('description'));
            $vars['start_time']   = htmlspecialchars($start_raw);
            $vars['end_time']     = htmlspecialchars($end_raw);
            $vars['status']       = in_array($status, ['open', 'closed']) ? $status : 'open';
            $vars['category_ids'] = $cat_ids;
        }

        $this->_make_sidebar('create');

        return [
            'body'       => ee('View')->make('event_calendar:events/create')->render($vars),
            'heading'    => lang('page_add_event'),
            'breadcrumb' => [
                $this->base_url->compile() => lang('calendar_module_name'),
            ],
        ];
    }

    // -------------------------------------------------------------------------

    public function edit($id)
    {
        $this->_require_category_group();
        $this->_setup_form();

        $id      = (int) $id;
        $site_id = ee()->config->item('site_id');

        $settings       = $this->_load_settings();
        $cat_group_id   = (int) $settings['cat_group_id'];
        $all_categories = $this->_load_categories($cat_group_id);

        $event = ee()->db
            ->select('id, title, description, start_time, end_time, status')
            ->where('id', $id)
            ->where('site_id', $site_id)
            ->where('rrule IS NULL', NULL, FALSE)
            ->get('exp_calendar_events')
            ->row_array();

        if (empty($event)) {
            show_404();
        }

        $selected_cat_ids = $this->_load_selected_cat_ids($id);

        ee()->view->cp_page_title = lang('calendar_module_name') . ' - ' . lang('page_edit_event');
        ee()->cp->set_breadcrumb($this->base_url->compile(), lang('calendar_module_name'));

        $vars = [
            'errors'         => [],
            'event_id'       => $id,
            'title'          => htmlspecialchars($event['title']),
            'description'    => htmlspecialchars($event['description']),
            'start_time'     => ee()->localize->format_date(ee()->localize->get_date_format(false, true), $event['start_time']),
            'end_time'       => ee()->localize->format_date(ee()->localize->get_date_format(false, true), $event['end_time']),
            'status'         => $event['status'],
            'category_ids'   => $selected_cat_ids,
            'all_categories' => $all_categories,
            'form_url'       => ee('CP/URL')->make('addons/settings/event_calendar/edit/' . $id),
        ];

        if (ee()->input->post('submit')) {
            $title       = strip_tags(ee()->input->post('title'));
            $description = strip_tags(ee()->input->post('description'));
            $start_raw   = ee()->input->post('start_time');
            $end_raw     = ee()->input->post('end_time');
            $start_time  = ee()->localize->string_to_timestamp($start_raw);
            $end_time    = ee()->localize->string_to_timestamp($end_raw);
            $status      = ee()->input->post('status');
            $cat_ids_raw = ee()->input->post('category_ids');
            $cat_ids     = is_array($cat_ids_raw) ? array_map('intval', $cat_ids_raw) : [];

            $errors = [];

            if ($title === '' || strlen($title) > 255) {
                $errors[] = lang('title_required');
            }
            if (!$start_time || $start_time <= 0) {
                $errors[] = lang('start_time_invalid');
            }
            if (!$end_time || $end_time <= 0) {
                $errors[] = lang('end_time_invalid');
            }
            if (empty($errors) && $end_time < $start_time) {
                $errors[] = lang('invalid_end_time');
            }
            if (!in_array($status, ['open', 'closed'])) {
                $errors[] = lang('invalid_status');
            }

            if (empty($errors)) {
                ee()->db->update(
                    'exp_calendar_events',
                    [
                        'title'       => $title,
                        'description' => $description,
                        'start_time'  => $start_time,
                        'end_time'    => $end_time,
                        'status'      => $status,
                        'updated_at'  => ee()->localize->now,
                    ],
                    ['id' => $id, 'site_id' => $site_id]
                );

                $this->_save_category_ids($id, $cat_ids, $cat_group_id);
                $this->_bust_event_cache();

                ee('CP/Alert')->makeInline('event-calendar-success')
                    ->asSuccess()
                    ->withTitle(lang('event_updated'))
                    ->defer();

                ee()->functions->redirect($this->base_url->compile());
            }

            $vars['errors']       = $errors;
            $vars['title']        = htmlspecialchars(ee()->input->post('title'));
            $vars['description']  = htmlspecialchars(ee()->input->post('description'));
            $vars['start_time']   = htmlspecialchars($start_raw);
            $vars['end_time']     = htmlspecialchars($end_raw);
            $vars['status']       = in_array($status, ['open', 'closed']) ? $status : 'open';
            $vars['category_ids'] = $cat_ids;
        }

        $this->_make_sidebar('index');

        return [
            'body'       => ee('View')->make('event_calendar:events/edit')->render($vars),
            'heading'    => lang('page_edit_event'),
            'breadcrumb' => [
                $this->base_url->compile() => lang('calendar_module_name'),
            ],
        ];
    }

    // -------------------------------------------------------------------------

    public function delete($id)
    {
        $id      = (int) $id;
        $site_id = ee()->config->item('site_id');

        $event = ee()->db
            ->where('id', $id)
            ->where('site_id', $site_id)
            ->get('exp_calendar_events')
            ->row_array();

        if (empty($event)) {
            show_404();
        }

        if (ee()->input->server('REQUEST_METHOD') === 'POST') {
            ee()->db->delete('exp_calendar_events', ['id' => $id, 'site_id' => $site_id]);
            $this->_bust_event_cache();

            ee('CP/Alert')->makeInline('event-calendar-success')
                ->asSuccess()
                ->withTitle(lang('event_deleted'))
                ->defer();

            ee()->functions->redirect($this->base_url->compile());
        }

        ee()->view->cp_page_title = lang('calendar_module_name') . ' - ' . lang('confirm_delete_title');
        ee()->cp->set_breadcrumb($this->base_url->compile(), lang('calendar_module_name'));

        $vars = [
            'title'      => htmlspecialchars($event['title']),
            'action_url' => ee('CP/URL')->make('addons/settings/event_calendar/delete/' . $id),
            'cancel_url' => $this->base_url->compile(),
            'csrf_token' => CSRF_TOKEN,
        ];

        return [
            'body'       => ee('View')->make('event_calendar:events/confirm_delete')->render($vars),
            'heading'    => lang('confirm_delete_title'),
            'breadcrumb' => [
                $this->base_url->compile() => lang('calendar_module_name'),
            ],
        ];
    }

    // -------------------------------------------------------------------------

    public function create_recurring()
    {
        $this->_require_category_group();
        $this->_setup_form();

        $settings       = $this->_load_settings();
        $cat_group_id   = (int) $settings['cat_group_id'];
        $all_categories = $this->_load_categories($cat_group_id);

        ee()->view->cp_page_title = lang('calendar_module_name') . ' - ' . lang('page_add_recurring');
        ee()->cp->set_breadcrumb($this->base_url->compile(), lang('calendar_module_name'));

        $days_options = [
            0 => lang('sunday'),
            1 => lang('monday'),
            2 => lang('tuesday'),
            3 => lang('wednesday'),
            4 => lang('thursday'),
            5 => lang('friday'),
            6 => lang('saturday'),
        ];

        $vars = [
            'errors'         => [],
            'title'          => '',
            'description'    => '',
            'day_of_week'    => '',
            'start_time'     => '',
            'end_time'       => '',
            'status'         => 'open',
            'category_ids'   => [],
            'all_categories' => $all_categories,
            'days_options'   => $days_options,
            'form_url'       => ee('CP/URL')->make('addons/settings/event_calendar/create_recurring'),
        ];

        if (ee()->input->post('submit')) {
            $title       = strip_tags(ee()->input->post('title'));
            $description = strip_tags(ee()->input->post('description'));
            $dow_raw     = ee()->input->post('day_of_week');
            $dow         = ($dow_raw !== FALSE && $dow_raw !== '') ? (int) $dow_raw : -1;
            $start_raw   = (string) ee()->input->post('start_time'); // HH:MM
            $end_raw     = (string) ee()->input->post('end_time');   // HH:MM
            $status      = ee()->input->post('status');
            $cat_ids_raw = ee()->input->post('category_ids');
            $cat_ids     = is_array($cat_ids_raw) ? array_map('intval', $cat_ids_raw) : [];

            $errors = [];

            if ($title === '' || strlen($title) > 255) {
                $errors[] = lang('title_required');
            }
            if ($dow < 0 || $dow > 6) {
                $errors[] = lang('invalid_day_of_week');
            }
            $start_time = $this->_parse_hhmm($start_raw);
            if ($start_time === false) {
                $errors[] = lang('start_time_invalid_recurring');
            }
            $end_time = $this->_parse_hhmm($end_raw);
            if ($end_time === false) {
                $errors[] = lang('end_time_invalid_recurring');
            }
            if (empty($errors) && $end_time < $start_time) {
                $errors[] = lang('invalid_end_time');
            }
            if (!in_array($status, ['open', 'closed'])) {
                $errors[] = lang('invalid_status');
            }

            if (empty($errors)) {
                $now     = ee()->localize->now;
                $site_id = ee()->config->item('site_id');
                $byday   = ['SU','MO','TU','WE','TH','FR','SA'][$dow];
                $rrule   = 'FREQ=WEEKLY;BYDAY=' . $byday;

                ee()->db->insert('exp_calendar_events', [
                    'site_id'        => $site_id,
                    'title'          => $title,
                    'slug'           => '',
                    'description'    => $description,
                    'location'       => '',
                    'url'            => '',
                    'start_time'     => $start_time,
                    'end_time'       => $end_time,
                    'all_day'        => 0,
                    'rrule'          => $rrule,
                    'recurrence_end' => null,
                    'status'         => $status,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]);
                $new_id = (int) ee()->db->insert_id();

                $this->_save_category_ids($new_id, $cat_ids, $cat_group_id);
                $this->_bust_event_cache();

                ee('CP/Alert')->makeInline('event-calendar-success')
                    ->asSuccess()
                    ->withTitle(lang('recurring_created'))
                    ->defer();

                ee()->functions->redirect($this->base_url->compile());
            }

            $vars['errors']       = $errors;
            $vars['title']        = htmlspecialchars(ee()->input->post('title'));
            $vars['description']  = htmlspecialchars(ee()->input->post('description'));
            $vars['day_of_week']  = $dow_raw;
            $vars['start_time']   = htmlspecialchars($start_raw);
            $vars['end_time']     = htmlspecialchars($end_raw);
            $vars['status']       = in_array($status, ['open', 'closed']) ? $status : 'open';
            $vars['category_ids'] = $cat_ids;
        }

        $this->_make_sidebar('create_recurring');

        return [
            'body'       => ee('View')->make('event_calendar:events/create_recurring')->render($vars),
            'heading'    => lang('page_add_recurring'),
            'breadcrumb' => [
                $this->base_url->compile() => lang('calendar_module_name'),
            ],
        ];
    }

    // -------------------------------------------------------------------------

    public function edit_recurring($id)
    {
        $this->_require_category_group();
        $this->_setup_form();

        $id      = (int) $id;
        $site_id = ee()->config->item('site_id');

        $settings       = $this->_load_settings();
        $cat_group_id   = (int) $settings['cat_group_id'];
        $all_categories = $this->_load_categories($cat_group_id);

        $event = ee()->db
            ->select('id, title, description, start_time, end_time, status, rrule')
            ->where('id', $id)
            ->where('site_id', $site_id)
            ->where('rrule IS NOT NULL', NULL, FALSE)
            ->get('exp_calendar_events')
            ->row_array();

        if (empty($event)) {
            show_404();
        }

        $selected_cat_ids = $this->_load_selected_cat_ids($id);

        ee()->view->cp_page_title = lang('calendar_module_name') . ' - ' . lang('page_edit_recurring');
        ee()->cp->set_breadcrumb($this->base_url->compile(), lang('calendar_module_name'));

        $days_options = [
            0 => lang('sunday'),
            1 => lang('monday'),
            2 => lang('tuesday'),
            3 => lang('wednesday'),
            4 => lang('thursday'),
            5 => lang('friday'),
            6 => lang('saturday'),
        ];

        $byday_map = ['SU' => 0, 'MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6];
        $dow_from_rrule = '';
        if (preg_match('/BYDAY=([A-Z]{2})/', (string) $event['rrule'], $m)) {
            $dow_from_rrule = (string) ($byday_map[$m[1]] ?? '');
        }

        $vars = [
            'errors'         => [],
            'event_id'       => $id,
            'title'          => htmlspecialchars($event['title']),
            'description'    => htmlspecialchars($event['description']),
            'day_of_week'    => $dow_from_rrule,
            'start_time'     => date('H:i', (int) $event['start_time']),
            'end_time'       => date('H:i', (int) $event['end_time']),
            'status'         => $event['status'],
            'category_ids'   => $selected_cat_ids,
            'all_categories' => $all_categories,
            'days_options'   => $days_options,
            'form_url'       => ee('CP/URL')->make('addons/settings/event_calendar/edit_recurring/' . $id),
        ];

        if (ee()->input->post('submit')) {
            $title       = strip_tags(ee()->input->post('title'));
            $description = strip_tags(ee()->input->post('description'));
            $dow_raw     = ee()->input->post('day_of_week');
            $dow         = ($dow_raw !== FALSE && $dow_raw !== '') ? (int) $dow_raw : -1;
            $start_raw   = (string) ee()->input->post('start_time'); // HH:MM
            $end_raw     = (string) ee()->input->post('end_time');   // HH:MM
            $status      = ee()->input->post('status');
            $cat_ids_raw = ee()->input->post('category_ids');
            $cat_ids     = is_array($cat_ids_raw) ? array_map('intval', $cat_ids_raw) : [];

            $errors = [];

            if ($title === '' || strlen($title) > 255) {
                $errors[] = lang('title_required');
            }
            if ($dow < 0 || $dow > 6) {
                $errors[] = lang('invalid_day_of_week');
            }
            $start_time = $this->_parse_hhmm($start_raw);
            if ($start_time === false) {
                $errors[] = lang('start_time_invalid_recurring');
            }
            $end_time = $this->_parse_hhmm($end_raw);
            if ($end_time === false) {
                $errors[] = lang('end_time_invalid_recurring');
            }
            if (empty($errors) && $end_time < $start_time) {
                $errors[] = lang('invalid_end_time');
            }
            if (!in_array($status, ['open', 'closed'])) {
                $errors[] = lang('invalid_status');
            }

            if (empty($errors)) {
                $byday = ['SU','MO','TU','WE','TH','FR','SA'][$dow];
                $rrule = 'FREQ=WEEKLY;BYDAY=' . $byday;

                ee()->db->update(
                    'exp_calendar_events',
                    [
                        'title'       => $title,
                        'description' => $description,
                        'start_time'  => $start_time,
                        'end_time'    => $end_time,
                        'rrule'       => $rrule,
                        'status'      => $status,
                        'updated_at'  => ee()->localize->now,
                    ],
                    ['id' => $id, 'site_id' => $site_id]
                );

                $this->_save_category_ids($id, $cat_ids, $cat_group_id);
                $this->_bust_event_cache();

                ee('CP/Alert')->makeInline('event-calendar-success')
                    ->asSuccess()
                    ->withTitle(lang('recurring_updated'))
                    ->defer();

                ee()->functions->redirect($this->base_url->compile());
            }

            $vars['errors']       = $errors;
            $vars['title']        = htmlspecialchars(ee()->input->post('title'));
            $vars['description']  = htmlspecialchars(ee()->input->post('description'));
            $vars['day_of_week']  = $dow_raw;
            $vars['start_time']   = htmlspecialchars($start_raw);
            $vars['end_time']     = htmlspecialchars($end_raw);
            $vars['status']       = in_array($status, ['open', 'closed']) ? $status : 'open';
            $vars['category_ids'] = $cat_ids;
        }

        $this->_make_sidebar('index');

        return [
            'body'       => ee('View')->make('event_calendar:events/edit_recurring')->render($vars),
            'heading'    => lang('page_edit_recurring'),
            'breadcrumb' => [
                $this->base_url->compile() => lang('calendar_module_name'),
            ],
        ];
    }

    // -------------------------------------------------------------------------

    public function templates()
    {
        ee()->view->cp_page_title = lang('calendar_module_name') . ' - ' . lang('templates');
        ee()->cp->set_breadcrumb($this->base_url->compile(), lang('calendar_module_name'));
        $this->_make_sidebar('templates');

        return [
            'body'       => ee('View')->make('event_calendar:events/templates')->render([]),
            'heading'    => lang('templates'),
            'breadcrumb' => [
                $this->base_url->compile() => lang('calendar_module_name'),
            ],
        ];
    }

    // -------------------------------------------------------------------------

    public function delete_recurring($id)
    {
        $id      = (int) $id;
        $site_id = ee()->config->item('site_id');

        $event = ee()->db
            ->where('id', $id)
            ->where('site_id', $site_id)
            ->where('rrule IS NOT NULL', NULL, FALSE)
            ->get('exp_calendar_events')
            ->row_array();

        if (empty($event)) {
            show_404();
        }

        if (ee()->input->server('REQUEST_METHOD') === 'POST') {
            ee()->db->delete('exp_calendar_events', ['id' => $id, 'site_id' => $site_id]);
            $this->_bust_event_cache();

            ee('CP/Alert')->makeInline('event-calendar-success')
                ->asSuccess()
                ->withTitle(lang('recurring_deleted'))
                ->defer();

            ee()->functions->redirect($this->base_url->compile());
        }

        ee()->view->cp_page_title = lang('calendar_module_name') . ' - ' . lang('confirm_delete_title');
        ee()->cp->set_breadcrumb($this->base_url->compile(), lang('calendar_module_name'));

        $vars = [
            'title'      => htmlspecialchars($event['title']),
            'action_url' => ee('CP/URL')->make('addons/settings/event_calendar/delete_recurring/' . $id),
            'cancel_url' => $this->base_url->compile(),
            'csrf_token' => CSRF_TOKEN,
        ];

        return [
            'body'       => ee('View')->make('event_calendar:events/confirm_delete')->render($vars),
            'heading'    => lang('confirm_delete_title'),
            'breadcrumb' => [
                $this->base_url->compile() => lang('calendar_module_name'),
            ],
        ];
    }
}
