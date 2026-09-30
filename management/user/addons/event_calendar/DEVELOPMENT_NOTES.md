# Event Calendar Add-on — Development Notes

Notes accumulated during active development of this addon. Intended to help an agent build or extend EE 7 addons without repeating the same mistakes.

---

## Addon File Structure

```
event_calendar/
  addon.setup.php          — metadata array, no class
  mcp.event_calendar.php   — CP backend (Event_calendar_mcp)
  mod.event_calendar.php   — front-end template tags (Event_calendar)
  upd.event_calendar.php   — install / uninstall / update (Event_calendar_upd)
  language/english/        — lang file loaded via ee()->lang->loadfile('event_calendar')
  views/events/            — CP view PHP files rendered via ee('View')->make(...)
  assets/                  — JS/CSS for the front end (calendar.js, etc.)
  vendor/                  — Composer autoloaded classes (RecurrenceExpander)
  Model/                   — EE model classes referenced in addon.setup.php
```

---

## Class Naming — Critical

EE derives class names via `ucfirst($shortname)`. For shortname `event_calendar`:

| File | Class |
|------|-------|
| mcp.event_calendar.php | `Event_calendar_mcp` |
| mod.event_calendar.php | `Event_calendar` |
| upd.event_calendar.php | `Event_calendar_upd` |

**Capital E only — NOT `Event_Calendar`.** Wrong class name = silent failure, no error.

Do not add a `namespace` declaration in these files. EE 7 locates classes without one.

`list` is a reserved keyword in PHP 8. Template tag methods cannot be named `list()` — use `events_list()` instead.

---

## addon.setup.php

Returns a plain array. The `version` key here must be kept in sync with `$version` in `upd.event_calendar.php`. EE compares these to decide whether to run `update()`.

---

## Installation (upd — install())

EE 7 does **not** auto-insert into `exp_modules`. Register manually with a guard:

```php
$exists = ee()->db->where('module_name', 'Event_calendar')->count_all_results('exp_modules');
if (!$exists) {
    ee()->db->insert('exp_modules', [
        'module_name'        => 'Event_calendar',
        'module_version'     => $this->version,
        'has_cp_backend'     => 'y',
        'has_publish_fields' => 'n',
    ]);
}
```

Register AJAX actions the same way — guard with a count check before INSERT.

Seed a settings row for the current site immediately after creating the settings table.

---

## Update Migrations (upd — update())

Always guard ALTER TABLE with `SHOW COLUMNS`:

```php
$col = ee()->db->query("SHOW COLUMNS FROM exp_calendar_events LIKE 'my_column'");
if ($col->num_rows() === 0) {
    ee()->db->query("ALTER TABLE exp_calendar_events ADD COLUMN ...");
}
```

MySQL 8 does **not** support `ADD COLUMN IF NOT EXISTS`. The guard is mandatory.

Guard `ADD KEY` with `SHOW INDEX` the same way — duplicate key names throw an error.

TEXT/MEDIUMTEXT columns cannot have `DEFAULT ''` in MySQL. Never add DEFAULT on TEXT columns.

**Version already recorded problem:** If a migration is deployed but fails partway (e.g. a schema error), EE may have already written the new version to `exp_modules`. On the next deploy the `version_compare` check passes and the migration is skipped. Fix: run the SQL manually via Docker PDO, then bump the version number to a new patch.

After running a manual migration, also bump `module_version` in `exp_modules` so EE records the new state:

```bash
docker compose exec php php -r "
\$pdo = new PDO('mysql:host=db;dbname=recroomdev', 'recroomuser', 'PASSWORD');
\$pdo->exec('ALTER TABLE exp_calendar_events ...');
\$pdo->prepare('UPDATE exp_modules SET module_version = ? WHERE module_name = ?')
     ->execute(['2.x.x', 'Event_calendar']);
"
```

---

## CP Backend (mcp)

### Sidebar

```php
$sidebar = ee('CP/Sidebar')->make();
$list    = $sidebar->addHeader(lang('events'))->addBasicList();
$item    = $list->addItem(lang('label'), $url);
$item->isActive(); // call on the active item
```

`addHeader()->addItem()` does not exist. Call `addBasicList()` first, then `addItem()` on the list.

### CP Forms — View Structure

EE's CP CSS auto-handles required asterisks and spacing when you use this structure:

```html
<fieldset class="fieldset-required">
    <div class="field-instruct">
        <label><?= lang('field_name') ?></label>
        <em><?= lang('hint_text') ?></em>   <!-- optional hint -->
    </div>
    <div class="field-control">
        <input type="text" name="field_name" value="<?= $value ?>">
    </div>
</fieldset>
```

Override the `.field-instruct label:last-child` margin in `_setup_form()`:

```php
ee()->cp->add_to_head('<style>.field-instruct label:last-child{margin-bottom:5px}</style>');
```

### Rendering Views

```php
return [
    'body'       => ee('View')->make('event_calendar:events/my_view')->render($vars),
    'heading'    => lang('page_title'),
    'breadcrumb' => [$this->base_url->compile() => lang('module_name')],
];
```

The `event_calendar:` prefix maps to `views/` inside the addon directory.

### Pagination

```php
$pagination = ee('CP/Pagination', [
    'base_url'    => $base_url,
    'total_items' => $total,
    'per_page'    => $per_page,
    'cur_page'    => $offset,
]);
// render() requires the base URL object as an argument
$html = $pagination->render($base_url);
```

### Alerts

```php
ee('CP/Alert')->makeInline('event-calendar-success')
    ->asSuccess()
    ->withTitle(lang('event_saved'))
    ->defer(); // defer = show after redirect
```

### Date Picker

Use `rel="date-picker"` on the input (not `class="datepicker"`). Requires the `DateTrait`:

```php
use ExpressionEngine\Library\Date\DateTrait;
class Event_calendar_mcp {
    use DateTrait;
    ...
    private function _setup_form() {
        $this->addDatePickerScript();
        ee()->javascript->set_global('date.date_format', ee()->localize->get_date_format(false, true));
        ee()->javascript->set_global('date.include_seconds', ...);
        ee()->javascript->set_global('date.time_format', ...);
    }
}
```

Input:

```html
<input type="text" name="start_time" value="<?= $start_time ?>" rel="date-picker" data-include_time="true" autocomplete="off">
```

### CSRF

Add a hidden field in every CP form:

```html
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(CSRF_TOKEN) ?>">
```

EE validates CSRF automatically before the addon runs. Do **not** manually re-check `$_POST['csrf_token']` — EE unsets it after its own check, so your check will always fail.

### Delete Confirmation

Never use inline `confirm()` dialogs. Use EE's native `data-confirm` attribute on delete buttons:

```html
<a href="<?= $url ?>" data-confirm="<?= htmlspecialchars(lang('confirm_delete')) ?>">Delete</a>
```

---

## File Field (Image Picker)

Use `ee()->file_field->dragAndDropField()` to render EE's native drag-and-drop file field UI. This is the **only** approach that produces the `.fields-upload-chosen.list-item` element with drag-drop and file preview.

```php
// In _setup_form():
ee()->load->library('file_field');

// Render the field (returns HTML string):
$image_field = ee()->file_field->dragAndDropField('image', $current_value, 'all', 'image');
// $current_value is the stored {file:ID:url} string, or '' for new records
```

Pass `$image_field` to the view and output with `<?= $image_field ?>` inside the `.field-control` div.

**Storage format:** The drag-and-drop field stores data as `{file:67:url}` — a file ID reference, **not** `{filedir_X}filename.ext`. Do not assume the legacy format.

**URL resolution in front-end tags:**

```php
$img_file = null;
if (!empty($stored_value)) {
    if (preg_match('/^\{file:(\d+):url\}$/', $stored_value, $m)) {
        $img_file = ee('Model')->get('File', (int) $m[1])->first();
    } elseif (preg_match('/^\{filedir_(\d+)\}(.+)$/', $stored_value, $m)) {
        $img_file = ee('Model')->get('File')
            ->with('UploadDestination')
            ->filter('file_name', $m[2])
            ->filter('upload_location_id', (int) $m[1])
            ->filter('site_id', $site_id)
            ->first();
    }
}
$url = $img_file ? htmlspecialchars($img_file->getAbsoluteURL()) : '';
```

Handle **both** formats — future EE versions or legacy data may use either.

**Do not use `ee('CP/FilePicker')->make()->getLink()->render()`** for a standalone button. It only renders an anchor tag with no surrounding upload UI. The `dragAndDropField()` method calls `loadDragAndDropAssets()` internally and renders the full widget.

The `ee('CP/FilePicker')` DI service is registered as `'CP/FilePicker'` in `app.setup.php`. Do not use `'filepicker/FilePicker'` or `new FilePicker()`.

---

## URL Field (Link URLs)

EE's native URL fieldtype uses `type="text"` (not `type="url"`) and validates server-side. `type="url"` blocks browser submission of relative paths. Match EE's behaviour:

**View input:**
```html
<input type="text" name="url" value="<?= $url ?>" maxlength="512">
```

**Server-side validation** (mirrors EE's `ft.url.php`):

```php
private function _validate_url(string $url): bool
{
    if ($url === '') return true;
    $parsed = parse_url($url);
    if ($parsed === false) return false;
    if (!isset($parsed['host']) || !isset($parsed['scheme'])) {
        return strncmp($url, '/', 1) === 0; // allow relative paths
    }
    return in_array($parsed['scheme'] . '://', ['http://', 'https://'], true);
}
```

**Front-end resolution:** Prepend `site_url` to relative paths so the output is always an absolute URL. Determine external/internal before prepending to avoid applying `target="_blank"` to same-site links:

```php
'event_url' => htmlspecialchars(
    strncmp($row['url'], '/', 1) === 0 && strncmp($row['url'], '//', 2) !== 0
        ? rtrim((string) ee()->config->item('site_url'), '/') . $row['url']
        : $row['url']
),
'event_url_is_external' => preg_match('/^https?:\/\//', $row['url']) ? 'y' : 'n',
```

**In the EE template:**

```
{if event_url_is_external == "y"}
<a href="{event_url}" target="_blank" rel="noopener noreferrer">{event_url}</a>
{if:else}
<a href="{event_url}">{event_url}</a>
{/if}
```

---

## Template Tags (mod)

### Registering Tags

Each public method on `Event_calendar` is callable as `{exp:event_calendar:method_name}`.

### AJAX Security — Strict Order

For AJAX endpoints, validate in this exact order:

1. `ee()->session->userdata('member_id')` — 403 if not set
2. `X-Calendar-Nonce` header matches `$_SESSION['calendar_ajax_nonce']` — 403
3. `strlen($month) === 7` — before regex
4. Regex match `/^\d{4}-\d{2}$/` — 400 if not
5. Month number 01–12 — 400 if not

### Session / Nonce

`EE_Session::set_userdata()` does not exist in EE 7. Store addon-specific state in `$_SESSION` directly:

```php
$_SESSION['calendar_ajax_nonce'] = bin2hex(random_bytes(16));
```

### Modifying Page Title from a Tag

EE snippets (like `{head}`) are substituted into `ee()->TMPL->template` before tag methods run. This means you can regex-replace the rendered `<title>` tag directly from within the tag method:

```php
$page_title = htmlspecialchars($row['title'], ENT_QUOTES)
    . ' | ' . htmlspecialchars((string) ee()->config->item('site_name'), ENT_QUOTES);
ee()->TMPL->template = preg_replace(
    '~<title>[^<]*</title>~',
    '<title>' . $page_title . '</title>',
    (string) ee()->TMPL->template
);
```

This is the correct server-side approach — do not use JavaScript to set the title, as it will not be indexed by search engines.

Do not add a `<title>` tag to the `{head}` snippet if SEO Lite is installed. SEO Lite outputs its own `<title>` tag, resulting in duplicates. Remove any manually added `<title>` from the snippet.

### EE Active Record — SQL Literals

`ee()->db->select()` auto-backtick-quotes everything. Pass `FALSE` as the second argument when the SELECT contains SQL expressions:

```php
ee()->db->select('id, NULL AS day_of_week, "single" AS event_type', FALSE);
```

### Returning Template Variables

```php
$variables = [
    ['title' => 'Event One', 'slug' => 'event-one'],
    ['title' => 'Event Two', 'slug' => 'event-two'],
];
return ee()->TMPL->parse_variables(ee()->TMPL->tagdata, $variables);
```

For a single result (like `event_detail`), still wrap in an array: `$variables = [[ ... ]]`.

---

## Caching

```php
// Cache key pattern
$key = 'event_calendar_ajax_events_' . $site_id . '_' . $month;

// Write
ee()->cache->save($key, $json_string, 3600, Cache::LOCAL_SCOPE);

// Read
$cached = ee()->cache->get($key, Cache::LOCAL_SCOPE);

// Delete
ee()->cache->delete($key, Cache::LOCAL_SCOPE);
```

`Cache::LOCAL_SCOPE` is a constant on the legacy CI Cache library. **Do not add a `use` statement** — it is not a class that can be imported. The constant resolves correctly without one.

Bust the cache for current, previous, and next month on any write operation. The cache is a superset of all open events for a given month; filtering is done client-side in JS.

---

## Slug-Based URLs

Events use a `slug` column (VARCHAR 255, NOT NULL DEFAULT '') for human-readable URLs. Slugs are auto-generated from the title if left blank on save.

**URL style setting** controls which URL segment holds the slug:

| Setting | URL pattern | `slug_segment` | `date_segment` |
|---------|-------------|----------------|----------------|
| `clean` | `/event-detail/{slug}` | 2 | 3 |
| `index` | `/event-detail/index/{slug}` | 3 | 4 |

The `clean` style requires `strict_urls = n` in EE config (the addon sets this automatically). The `index` style works with `strict_urls = y`.

Syncing `strict_urls` to the EE config table:

```php
$exp_value = $url_style === 'index' ? 'y' : 'n';
// INSERT or UPDATE exp_config WHERE key = 'strict_urls' AND site_id = X
```

---

## Template Files

Templates live under `public/management/user/templates/{site_short_name}/`. EE reads them from the filesystem at runtime; the DB record (`exp_templates.template_data`) is a sync copy. When you edit the `.html` file on disk, also update the DB record:

```bash
docker compose exec php php -r "
\$pdo = new PDO('mysql:host=db;dbname=recroomdev', 'recroomuser', 'PASSWORD');
\$content = file_get_contents('/usr/share/nginx/html/management/user/templates/default_site/event-detail.group/index.html');
\$pdo->prepare('UPDATE exp_templates t JOIN exp_template_groups g ON g.group_id = t.group_id SET t.template_data = ? WHERE g.group_name = ? AND t.template_name = ?')
     ->execute([\$content, 'event-detail', 'index']);
"
```

The template file content path inside the PHP container is `/usr/share/nginx/html/...` (the `./public` directory is mounted there — **not** `/var/www/html`).

The `_event_detail_template()` method in `upd.event_calendar.php` defines the canonical template string used on fresh installs. Keep it in sync with the live template file whenever the template changes.

---

## DB Access (Docker)

```bash
# Credentials are in .env: DB_HOSTNAME=db, DB_DATABASE=recroomdev, DB_USERNAME=recroomuser
docker compose exec php php -r "
\$pdo = new PDO('mysql:host=db;dbname=recroomdev', 'recroomuser', 'PASSWORD');
// ... queries
"
```

`ee-helper.php` does not have a raw SQL command. Use inline PHP via `docker compose exec php php -r` for direct queries.

---

## Common Runtime Errors and Fixes

| Error | Cause | Fix |
|-------|-------|-----|
| `Unregistered service "ee:filepicker/FilePicker"` | Wrong DI service path | Use `ee('CP/FilePicker')` |
| `ParseError: unexpected token '->'` | `new Foo()->method()` without parens | Use `(new Foo())->method()` or assign to a variable first |
| `Column not found: image_file_id` | EE skipped migration (version already recorded) | Run ALTER TABLE manually via PDO, bump version in `exp_modules` |
| `Duplicate key name` on ALTER TABLE | Index already exists | Guard with `SHOW INDEX FROM table LIKE 'key_name'` |
| `TEXT column cannot have DEFAULT` | MySQL 8 restriction | Remove `DEFAULT ''` from TEXT columns in CREATE TABLE |
| `set_userdata() not found` | EE 7 removed that method | Use `$_SESSION` directly |
| `CP/RTE does not exist` | EE 7.5.x removed it | Do not use — use a plain `<textarea>` |
| Template tag method named `list()` | PHP 8 reserved word | Rename to `events_list()` |
| Date picker not appearing | Wrong attribute | Use `rel="date-picker"`, not `class="datepicker"` |
| CP table sort not working | Missing `FALSE` on `select()` | Pass `FALSE` as second arg when SELECT has SQL expressions |
| Image field only shows a button | Using `getLink()->render()` | Use `ee()->file_field->dragAndDropField()` instead |
| Image URL empty on front end | Regex only handled `{filedir_X}` format | Also handle `{file:ID:url}` format (what `dragAndDropField` stores) |

---

## addon.setup.php — Full Key Reference

```php
return [
    'author'         => 'Propagate',
    'author_url'     => 'https://propagate.com',
    'name'           => 'Event Calendar',          // human-readable name
    'description'    => 'Short description',
    'version'        => '1.0.0',                   // keep in sync with $version in upd file
    'namespace'      => 'Propagate\EventCalendar', // used for Model resolution only
    'settings_exist' => 'y',                       // shows Settings link in addon list
    'has_cp_backend' => 'y',                       // enables MCP routing
    'models'         => [
        'Event' => 'Model\Event',                  // maps to Model/Event.php in addon dir
    ],
];
```

The `version` here is what EE compares against `exp_modules.module_version` to decide whether to call `update()`. Bump both together.

---

## Table Naming Convention

Two distinct prefixes in this addon — follow the same pattern in new addons:

| Type | Pattern | Example |
|------|---------|---------|
| Content tables | `exp_{domain}_{noun}` | `exp_calendar_events` |
| Settings tables | `exp_{shortname}_settings` | `exp_event_calendar_settings` |

The shortname prefix (`event_calendar`) is used for settings so the table name clearly signals addon ownership. Content tables use a shorter domain prefix to avoid extremely long names.

---

## AJAX Endpoints

### Registration (install)

```php
$exists = ee()->db
    ->where('class', 'Event_calendar')
    ->where('method', 'fetch_events')
    ->count_all_results('actions');
if (!$exists) {
    ee()->db->insert('actions', [
        'class'       => 'Event_calendar',   // mod class name (ucfirst shortname)
        'method'      => 'fetch_events',     // public method on Event_calendar
        'csrf_exempt' => 1,                  // required for public AJAX calls
    ]);
}
```

### Getting the Action URL in PHP (to pass to JS)

```php
$action_id = ee()->db
    ->select('action_id')
    ->where('class', 'Event_calendar')
    ->where('method', 'fetch_events')
    ->get('actions')
    ->row_array()['action_id'] ?? 0;

$action_url = ee()->functions->fetch_site_index() . '?ACT=' . $action_id;
```

Pass this to JS via a `data-*` attribute on a container element in the template:

```html
<div data-calendar=""
     data-action-url="{calendar_ajax_url}"
     data-detail-url="{calendar_page_url}"
     data-url-style="{calendar_url_style}"
     data-nonce="{calendar_ajax_nonce}">
```

In JS, read the attributes off that element:

```javascript
var el = document.querySelector('[data-calendar]');
var CalendarData = {
    action_url: el.getAttribute('data-action-url'),
    detail_url: el.getAttribute('data-detail-url'),
    url_style:  el.getAttribute('data-url-style'),
    nonce:      el.getAttribute('data-nonce'),
};
```

**Do not use `ee()->javascript->set_global()`** for AJAX URLs — that API is for CP use only and injects into a CP-specific JS block. Use `data-*` attributes on front-end templates.

### The AJAX method (mod)

```php
public function fetch_events(): never
{
    // 1. Authenticate
    $nonce = (string) ee()->input->server('HTTP_X_YOUR_NONCE');
    if (!$nonce || ee()->cache->get('yourkey/' . $nonce, Cache::LOCAL_SCOPE) === FALSE) {
        $this->_send_json(json_encode(['error' => 'Unauthorized'], JSON_HEX_TAG), 403);
    }

    // 2. Validate input
    $month = (string) ee()->input->post('month');
    if (strlen($month) !== 7 || !preg_match('/^\d{4}-\d{2}$/', $month)) {
        $this->_send_json(json_encode(['error' => 'Bad request'], JSON_HEX_TAG), 400);
    }

    // 3. Check cache, query, encode, send
    $cache_key = 'yourkey/' . $site_id . '/' . $month;
    $cached    = ee()->cache->get($cache_key, Cache::LOCAL_SCOPE);
    if ($cached !== FALSE) {
        $this->_send_json($cached);
    }

    $json = json_encode(['success' => true, 'data' => $data], JSON_HEX_TAG);
    ee()->cache->save($cache_key, $json, 3600, Cache::LOCAL_SCOPE);
    $this->_send_json($json);
}

private function _send_json(string $json, int $status = 200): never
{
    while (ob_get_level() > 0) {
        ob_end_clean();   // drain EE's output buffers before writing JSON
    }
    http_response_code($status);
    header('Content-Type: application/json');
    echo $json;
    exit();
}
```

`ob_end_clean()` in a loop (not once) is required — EE opens multiple nested output buffers and any un-drained buffer will corrupt the JSON response.

### Nonce pattern

For public AJAX endpoints, store the nonce in the cache (not `$_SESSION`) so it works for non-logged-in visitors:

```php
// In the template tag that renders the calendar page:
$nonce = bin2hex(random_bytes(16));
ee()->cache->save(
    'event_calendar/nonce/' . $nonce,
    true, 900, Cache::LOCAL_SCOPE  // 15-minute TTL
);
// Expose to template as a variable
```

The AJAX handler then verifies: `ee()->cache->get('event_calendar/nonce/' . $nonce, Cache::LOCAL_SCOPE) !== FALSE`.

---

## Template Installation

The complete pattern for creating a template group + template on install:

```php
private function _install_template(): void
{
    $site_id = (int) ee()->config->item('site_id');

    // Write the .html file to the filesystem
    $dir = $this->_template_dir();
    if (!is_dir($dir)) {
        mkdir($dir, 0755, TRUE);
    }
    if (!file_exists($dir . 'index.html')) {
        file_put_contents($dir . 'index.html', $this->_my_template());
    }

    // Guard against re-installing an existing group
    $group_exists = ee()->db
        ->where('group_name', 'my-template-group')
        ->where('site_id', $site_id)
        ->count_all_results('exp_template_groups');
    if ($group_exists) return;

    ee()->db->insert('exp_template_groups', [
        'group_name'      => 'my-template-group',
        'group_order'     => 999,
        'is_site_default' => 'n',
        'site_id'         => $site_id,
    ]);
    $group_id = ee()->db->insert_id();

    ee()->db->insert('exp_templates', [
        'group_id'           => $group_id,
        'template_name'      => 'index',
        'template_type'      => 'webpage',
        'template_data'      => $this->_my_template(),
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

    // Grant access to all roles — without this the template is not publicly viewable
    $roles = ee()->db->select('role_id')->get('exp_roles')->result_array();
    foreach ($roles as $role) {
        ee()->db->insert('exp_templates_roles', [
            'template_id' => $template_id,
            'role_id'     => $role['role_id'],
        ]);
    }
}
```

`exp_templates_roles` access grants are **required**. If you skip this, EE returns a 403 for the template even when the visitor has no auth requirement. There is no default "public" access — every role must be explicitly inserted.

Getting the correct template directory path:

```php
private function _template_dir(): string
{
    $site_short = ee()->db
        ->select('site_name')
        ->where('site_id', (int) ee()->config->item('site_id'))
        ->get('exp_sites')
        ->row_array()['site_name'] ?? 'default_site';

    return rtrim(ee()->config->item('tmpl_file_basepath'), '/\\')
        . DIRECTORY_SEPARATOR . $site_short
        . DIRECTORY_SEPARATOR . 'my-template-group.group'
        . DIRECTORY_SEPARATOR;
}
```

The `site_name` column in `exp_sites` holds the short name (e.g. `default_site`), which is the directory name under `templates/`.

### Template Uninstall

Always clean up `exp_templates_roles` before deleting from `exp_templates`:

```php
// Delete role grants first (no FK cascade)
foreach ($template_ids as $tid) {
    ee()->db->where('template_id', $tid)->delete('exp_templates_roles');
}
ee()->db->where('group_id', $group_id)->delete('exp_templates');
ee()->db->where('group_id', $group_id)->delete('exp_template_groups');

// Remove files
ee()->load->helper('file');
delete_files($dir, TRUE);
@rmdir($dir);
```

---

## Theme Assets

Never hardcode absolute paths for theme assets. Use `theme_folder_path` (server path) and `theme_folder_url` (web URL):

```php
// Install: copy assets from addon directory to themes/user/addons/{shortname}/
$src  = __DIR__ . '/assets/';
$dest = ee()->config->item('theme_folder_path') . 'user/addons/event_calendar/';
if (!is_dir($dest)) {
    mkdir($dest, 0755, TRUE);
}
// copy individual files as needed
copy($src . 'calendar.js',  $dest . 'calendar.js');
copy($src . 'calendar.css', $dest . 'calendar.css');

// Reference in template tags:
$url = ee()->config->item('theme_folder_url') . 'user/addons/event_calendar/calendar.js';

// Uninstall: remove and clean up
ee()->load->helper('file');
delete_files($dest, TRUE);
@rmdir($dest);
```

---

## Uninstall Checklist

Order matters — follow this sequence:

1. Delete from `exp_modules` and `exp_actions` **first** (before dropping tables they reference)
2. Drop tables in dependency order: pivot tables → child tables → parent tables → settings
3. Remove theme assets (`delete_files` + `@rmdir`)
4. Uninstall templates (roles → templates → groups → files)
5. Clear any cached data

Failing to remove actions before dropping their mod class's table can leave orphaned `exp_actions` rows that cause errors on the next addon install.

---

## exp_config Key Quoting

`key` is a MySQL reserved word. Always backtick-quote it in EE Active Record:

```php
ee()->db->where('`key`', 'strict_urls')
```

Without the backticks, MySQL throws a syntax error.

---

## Rendering a Real RTE (Rich Text Editor) Field Outside a Channel Field

`ee('CP/RTE')` does not exist in EE 7.5.x — but the core `rte` addon (shortname `rte`) is a
real, separately-installed EE addon (`exp_modules` row `Rte`) with its own toolsets table
(`exp_rte_toolsets`) and DI-registered services. You can render the exact same CKEditor/RedactorX
widget a channel RTE field uses, in your own CP form, without touching `ee('CP/RTE')`:

```php
use ExpressionEngine\Addons\Rte\RteHelper;

private function _rte_field(string $field_name, ?string $value): string
{
    $value = (string) $value;

    $toolset_id = (int) (ee()->config->item('rte_default_toolset') ?: 0);
    $toolset    = $toolset_id
        ? ee('Model')->get('rte:Toolset')->filter('toolset_id', $toolset_id)->first()
        : null;
    if (!$toolset) {
        $toolset = ee('Model')->get('rte:Toolset')->first(); // fallback: first configured toolset
    }

    ee()->load->helper('form');
    if (!$toolset) {
        return form_textarea(['name' => $field_name, 'value' => $value, 'rows' => 10]);
    }

    $service_name  = ucfirst($toolset->toolset_type) . 'Service'; // e.g. CkeditorService, RedactorXService
    $config_handle = ee('rte:' . $service_name)->init([], $toolset); // enqueues all JS/CSS assets itself

    $id = str_replace(['[', ']'], ['_', ''], $field_name);
    ee()->cp->add_to_foot('<script type="text/javascript">new Rte("' . $id . '", "' . $config_handle . '", false);</script>');

    RteHelper::replaceFileTags($value);   // {file:ID:url} -> absolute URL for display
    RteHelper::replacePageTags($value);

    return form_textarea([
        'name' => $field_name, 'value' => $value, 'id' => $id, 'rows' => 10,
        'data-config' => $config_handle, 'class' => ee('rte:' . $service_name)->getClass(),
        'data-defer' => 'n',
    ]);
}
```

Key facts:
- **`ee('rte:' . $serviceName)`** is the correct DI path (`rte:CkeditorService`, `rte:RedactorXService`) —
  distinct from the nonexistent `ee('CP/RTE')`. It resolves because the `rte` addon registers its own
  Service classes with EE's DI container once installed.
- **`->init($settings, $toolset)`** self-enqueues every JS/CSS asset (CKEditor/Redactor bundle, `rte.js`,
  drag-and-drop file assets) via `ee()->cp->add_js_script()` — you do not need to load any of that yourself.
- **On save**, mirror the core fieldtype's normalization (trim whitespace-only content, strip
  `?cachebuster:N` query strings, decode `&quot;`, and convert absolute file/page URLs back to
  `{file:ID:url}` / page tags via `RteHelper::replaceFileUrls()` / `replacePageUrls()`) — otherwise
  saved content contains environment-specific absolute URLs instead of portable tags.
- **On front-end output**, do NOT `strip_tags()` or run `auto_typography()` on RTE content — it is
  already valid HTML. Just run it through `RteHelper::replaceFileTags()` / `replacePageTags()` (both
  take `$data` by reference) so any embedded file/page tags resolve to real URLs.
- If the `rte` addon were ever uninstalled, `ee('Model')->get('rte:Toolset')->first()` returns null —
  fall back to a plain `<textarea>` rather than fataling.

---

## Vendor / Composer Classes

If the addon ships PHP classes via Composer, require the autoloader at the top of the mod/mcp file:

```php
require_once __DIR__ . '/vendor/autoload.php';
use Propagate\EventCalendar\Service\RecurrenceExpander;
```

The `namespace` key in `addon.setup.php` affects EE Model resolution only — it does not enable PSR-4 autoloading. Composer's autoloader is completely separate.

---

## Things That Are Not What They Seem

- **`ee()->file_field->dragAndDropField()`** loads its own JS assets — you do not need to separately call `loadDragAndDropAssets()`.
- **`ee()->TMPL->template`** already has snippets substituted when your tag method runs. You can regex-replace content in it (e.g. the `<title>` tag) and it will be reflected in the final page output.
- **`Cache::LOCAL_SCOPE`** is the integer `2`. It is defined on the Cache class as a class constant but you cannot import it with `use`. Reference it as `Cache::LOCAL_SCOPE` directly.
- **EE's `{if}` conditionals** do not support `^=` (starts with) or other string operators. For conditional logic based on string content, compute a flag in PHP and expose it as a template variable (e.g. `event_url_is_external`).
- **`parse_url()` on a relative URL** like `/contact` returns `['path' => '/contact']` — no `scheme` or `host`. This is valid and not `false`. EE's URL fieldtype uses this to detect relative paths.
- **The `{head}` snippet** is a simple text substitution. It cannot accept parameters. Any dynamic content (page title, per-page meta) must be injected by modifying `ee()->TMPL->template` directly, not by passing variables to the snippet.
- **`exp_templates_roles` has no default rows.** A freshly installed template is inaccessible to everyone until you insert one row per role. Forgetting this produces a 403 with no error message.
- **`ee()->javascript->set_global()`** only works in CP views. It injects into a CP-specific JS block that does not exist on front-end pages. Pass data to front-end JS via `data-*` attributes on template elements.
- **Action `csrf_exempt = 1`** does not mean unauthenticated — it means EE skips its own CSRF token check for this endpoint. You still need your own nonce or other auth check.
- **EE records the new version in `exp_modules` before calling `update()`**, not after. If `update()` throws mid-way, the version is already bumped and the migration will never re-run. Always test migrations on a copy of the DB first.
- **`ob_end_clean()` must be called in a loop** — `while (ob_get_level() > 0)` — not once. EE opens several nested buffers and a single call only closes the innermost one.
