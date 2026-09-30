<?php
/**
 * Calendar - Template Reference
 *
 * Copyable EE template code for the Event Calendar add-on.
 */
?>
<style>
.cal-tpl-section       { margin-bottom: 40px; }
.cal-tpl-section h3    { margin: 0 0 6px; font-size: 14px; font-weight: 600; }
.cal-tpl-section p     { margin: 0 0 8px; color: #666; font-size: 13px; }
.cal-tpl-block         { position: relative; }
.cal-tpl-block pre     { background: #f4f4f4; border: 1px solid #ddd; border-radius: 3px;
                          padding: 14px 14px 14px 14px; margin: 0;
                          font-family: monospace; font-size: 12px; line-height: 1.6;
                          white-space: pre; overflow-x: auto; tab-size: 4; }
.cal-tpl-copy          { position: absolute; top: 8px; right: 8px;
                          padding: 3px 10px; font-size: 11px; cursor: pointer; }
.cal-tpl-copy.copied   { background: #3e8a4d; border-color: #3e8a4d; color: #fff; }
.cal-tpl-params        { margin: 10px 0 0; border-collapse: collapse; width: 100%; font-size: 12px; }
.cal-tpl-params th,
.cal-tpl-params td     { border: 1px solid #ddd; padding: 5px 8px; text-align: left; vertical-align: top; }
.cal-tpl-params th     { background: #f4f4f4; font-weight: 600; }
.cal-tpl-params code   { font-family: monospace; background: #eee; padding: 1px 4px;
                          border-radius: 2px; font-size: 11px; }
</style>

<?php

$templates = [

    // ------------------------------------------------------------------
    'display' => [
        'title'  => '{exp:event_calendar:display} — Interactive Calendar Grid',
        'desc'   => 'Embeds the interactive month-view calendar. Outputs JavaScript that bootstraps the grid and handles month navigation via AJAX. Place inside a wrapping element that your theme styles. When detail_url is provided, each event in the calendar exposes a "See Details" link to its landing page.',
        'code'   => '{exp:event_calendar:display
    single="yes"
    recurring="yes"
    status="open"
    detail_url="{site_url}event-detail"
}',
        'params' => [
            ['single',      'yes / no',    'yes',   'Include single (one-off) events'],
            ['recurring',   'yes / no',    'yes',   'Include recurring events'],
            ['status',      'open / any',  'open',  'Filter by status. "any" skips the filter entirely'],
            ['detail_url',  'URL',         '(none)', 'Base URL for event detail pages. Use {site_url}event-detail. When set, calendar events show a "See Details" link to {detail_url}/{slug} for single events or {detail_url}/{slug}/{date_key} for recurring events'],
            ['date_format', 'EE date str', '%F %j, %Y', 'Date portion of formatted timestamps'],
            ['time_format', 'EE date str', '%g:%i %A',  'Time portion of formatted timestamps'],
        ],
        'vars'   => [],
    ],

    // ------------------------------------------------------------------
    'event_detail' => [
        'title'  => '{exp:event_calendar:event_detail} — Single Event Landing Page',
        'desc'   => 'Renders a single event. Reads the event slug from URL segment 2 and the optional date key (YYYY-MM-DD) from segment 3, matching the URL structure /event-detail/{slug} for single events and /event-detail/{slug}/{date_key} for recurring events. For recurring events the correct occurrence is expanded from the date key. Returns no_results if the event is not found or not open. Place in the event-detail.group/index.html template. Requires strict_urls = "n" in config.php (set automatically on addon install).',
        'code'   => '{exp:event_calendar:event_detail slug_segment="2" date_segment="3"}

<p>
    <a href="{calendar_page_url}"
       onclick="if(history.length>1){history.back();return false;}">
        &larr; Back to Calendar
    </a>
</p>

<div class="row">
  <div class="col-md-6">

    <h1>{title}</h1>

    <p>{day_of_week}</p>
    <p>{start_date}</p>
    <p>{start_time} &ndash; {end_time}</p>

    {if location}<p><strong>Location:</strong> {location}</p>{/if}

    {if short_description}
    <div>
      <h3>Short Description</h3>
      {short_description}
    </div>
    {/if}

    {if event_details}
    <div>
      <h3>Event Details</h3>
      {event_details}
    </div>
    {/if}

    {if event_url}
    <div>
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

{/exp:event_calendar:event_detail}',
        'params' => [
            ['slug_segment', 'integer', '2', 'URI segment holding the event slug. Segment 2 matches the URL /event-detail/{slug}'],
            ['date_segment', 'integer', '3', 'URI segment holding the date key (YYYY-MM-DD). Used to resolve the correct recurring occurrence. Omitted for single events'],
            ['date_format',  'EE date str', '%F %j, %Y', 'Format for {start_date} and {end_date}'],
            ['time_format',  'EE date str', '%g:%i %A',  'Format for {start_time} and {end_time}'],
        ],
        'vars' => [
            ['{event_id}',         'int',    'Database ID of the event'],
            ['{event_type}',       'string', '"single" or "recurring"'],
            ['{title}',            'string', 'Event title (HTML-escaped)'],
            ['{slug}',             'string', 'URL slug'],
            ['{short_description}', 'string', 'Short description — rich text (HTML), file/page tags resolved to URLs'],
            ['{event_details}',    'string', 'Full event details — rich text (HTML), file/page tags resolved to URLs'],
            ['{location}',         'string', 'Location (HTML-escaped). Empty string if not set'],
            ['{event_url}',             'string', 'Link URL (HTML-escaped). Empty string if not set'],
            ['{event_url_is_external}', 'string', '"y" if the URL starts with http:// or https://, "n" otherwise (relative URLs are treated as same-site)'],
            ['{banner_image}',          'string', 'Absolute URL of the event banner image. Empty string if not set'],
            ['{all_day}',          'string', '"yes" or "no"'],
            ['{start_date}',       'string', 'Formatted date using date_format parameter'],
            ['{end_date}',         'string', 'Formatted date using date_format parameter'],
            ['{start_time}',       'string', 'Formatted time using time_format parameter'],
            ['{end_time}',         'string', 'Formatted time using time_format parameter'],
            ['{start_time_raw}',   'int',    'UTC Unix timestamp of occurrence start'],
            ['{end_time_raw}',     'int',    'UTC Unix timestamp of occurrence end'],
            ['{date_key}',         'string', 'YYYY-MM-DD of the occurrence — matches the URL segment'],
            ['{day_of_week}',      'string', 'Full day name, e.g. "Tuesday"'],
            ['{day_of_week_int}',  'int',    '0 (Sun) – 6 (Sat)'],
            ['{status}',           'string', '"open" or "closed"'],
            ['{rrule}',            'string', 'Recurrence rule string. Empty for single events'],
            ['{calendar_page_url}','string', 'Fallback "Back to Calendar" URL from addon settings'],
        ],
    ],

    // ------------------------------------------------------------------
    'events_list_upcoming' => [
        'title'  => '{exp:event_calendar:events_list} — Upcoming Events',
        'desc'   => 'Returns a flat list of upcoming events sorted by start time. Recurring events are expanded into individual occurrences up to the configured limit. {start_time} and {end_time} are Unix timestamps — use EE\'s format= parameter to control display. Supports {if no_results}. When detail_url is provided, {event_url} is populated for each event.',
        'code'   => '{exp:event_calendar:events_list
    limit="5"
    single="yes"
    recurring="yes"
    status="open"
    detail_url="{site_url}event-detail"
}
    <article>
        <h3><a href="{event_url}">{title}</a></h3>
        <p class="meta">
            {day_of_week},
            {start_time format="%F %j, %Y"} at {start_time format="%g:%i %A"}
            &ndash; {end_time format="%g:%i %A"}
        </p>
        <div class="body">{short_description}</div>
    </article>
{/exp:event_calendar:events_list}',
        'params' => [
            ['limit',      'integer',        '5',     'Maximum occurrences to return (after expansion)'],
            ['single',     'yes / no',       'yes',   'Include single (one-off) events'],
            ['recurring',  'yes / no',       'yes',   'Include recurring events'],
            ['status',     'open / any',     'open',  'Filter by status. "any" skips the filter entirely'],
            ['detail_url', 'URL',            '(none)', 'Base URL for event detail pages. Use {site_url}event-detail. When set, {event_url} is populated as {detail_url}/{slug} for single events or {detail_url}/{slug}/{date_key} for recurring events'],
            ['category',   'url_title | …',  '(none)', 'Pipe-separated category URL titles. Only events in any of the listed categories are returned. Omit to show all.'],
        ],
        'vars'   => [
            ['{event_id}',        'int',    'Database ID of the event definition'],
            ['{event_type}',      'string', '"single" or "recurring"'],
            ['{title}',           'string', 'Event title (HTML-escaped)'],
            ['{slug}',            'string', 'URL slug (HTML-escaped)'],
            ['{event_url}',       'string', 'Full detail page URL. Populated when detail_url param is set. Empty string otherwise'],
            ['{short_description}', 'string', 'Plain-text preview — HTML tags stripped after auto_typography()'],
            ['{date_key}',        'string', 'YYYY-MM-DD of the occurrence start date'],
            ['{start_time}',      'int (timestamp)', 'Start — UTC Unix timestamp. Use format= for display: {start_time format="%F %j, %Y"}'],
            ['{end_time}',        'int (timestamp)', 'End — UTC Unix timestamp. Use format= for display: {end_time format="%g:%i %A"}'],
            ['{start_time_raw}',  'int (timestamp)', 'Alias for {start_time} — same value'],
            ['{end_time_raw}',    'int (timestamp)', 'Alias for {end_time} — same value'],
            ['{status}',          'string', '"open" or "closed"'],
            ['{day_of_week}',     'string', 'Full day name, e.g. "Monday"'],
            ['{day_of_week_int}', 'int',    '0 (Sun) – 6 (Sat)'],
            ['{categories}',      'pair',   'Variable pair — loops over each category assigned to the event. Contains {cat_id}, {cat_name}, {cat_url_title}. Empty if no categories assigned.'],
        ],
    ],

    // ------------------------------------------------------------------
    'events_list_single_only' => [
        'title'  => '{exp:event_calendar:events_list} — Single Events Only',
        'desc'   => 'Same as the upcoming list but restricted to one-off events. Useful for a "special dates" sidebar.',
        'code'   => '{exp:event_calendar:events_list
    limit="10"
    single="yes"
    recurring="no"
    status="open"
    detail_url="{site_url}event-detail"
}
    <li>
        <a href="{event_url}">{title}</a>
        &mdash; {start_time format="%F %j, %Y"} at {start_time format="%g:%i %A"}
    </li>
{/exp:event_calendar:events_list}',
        'params' => [],
        'vars'   => [],
    ],

    // ------------------------------------------------------------------
    'events_list_recurring_only' => [
        'title'  => '{exp:event_calendar:events_list} — Recurring Events Only',
        'desc'   => 'Returns only recurring event occurrences — useful for a "weekly schedule" block.',
        'code'   => '{exp:event_calendar:events_list
    limit="14"
    single="no"
    recurring="yes"
    status="open"
}
    <div class="schedule-item">
        <span class="day">{day_of_week}</span>
        <span class="time">
            {start_time format="%g:%i %A"} &ndash; {end_time format="%g:%i %A"}
        </span>
        <span class="name">{title}</span>
    </div>
{/exp:event_calendar:events_list}',
        'params' => [],
        'vars'   => [],
    ],

    // ------------------------------------------------------------------
    'events_list_by_category' => [
        'title'  => '{exp:event_calendar:events_list} — Filter by Category',
        'desc'   => 'The category parameter accepts one or more category URL titles separated by a pipe (|). Only events assigned to any of those categories are returned. URL titles are set in EE\'s category manager.',
        'code'   => '{!-- Single category --}
{exp:event_calendar:events_list
    category="events"
    limit="5"
    status="open"
}
    <li>{title} &mdash; {start_time format="%F %j, %Y"}</li>
{/exp:event_calendar:events_list}

{!-- Multiple categories (OR logic — events in either category) --}
{exp:event_calendar:events_list
    category="events|workshops"
    limit="10"
    status="open"
}
    <li>{title} &mdash; {start_time format="%F %j, %Y"}</li>
{/exp:event_calendar:events_list}',
        'params' => [],
        'vars'   => [],
    ],

    // ------------------------------------------------------------------
    'upcoming_months' => [
        'title'  => '{exp:event_calendar:upcoming_months} — Dynamic Month Label',
        'desc'   => 'Returns the unique month name(s) covered by the next N upcoming events in the given category — e.g. "MAY" when all events fall in one month, or "MAY / JUNE" when they span two. Output is uppercase. Useful for hero sections or schedule headings where the month is displayed as a label.',
        'code'   => '{exp:event_calendar:upcoming_months
    limit="5"
    single="yes"
    recurring="yes"
    status="open"
    category="live-band"
    separator=" / "
}',
        'params' => [
            ['limit',     'integer',       '5',       'Number of upcoming events to consider when collecting months'],
            ['single',    'yes / no',      'yes',     'Include single (one-off) events'],
            ['recurring', 'yes / no',      'yes',     'Include recurring events'],
            ['status',    'open / any',    'open',    'Filter by status. "any" skips the filter entirely'],
            ['category',  'url_title | …', '(none)',  'Pipe-separated category URL titles. Omit to consider all events.'],
            ['separator', 'string',        ' / ',     'String placed between month names when events span multiple months'],
        ],
        'vars'   => [],
    ],

    // ------------------------------------------------------------------
    'events_list_categories_pair' => [
        'title'  => '{exp:event_calendar:events_list} — Displaying Categories on Each Event',
        'desc'   => 'Every event exposes a {categories} variable pair containing the categories assigned to that event. Loop over it to render category labels, badges, or data attributes. The pair is empty when no categories are assigned.',
        'code'   => '{exp:event_calendar:events_list limit="10" status="open"}
    <article>
        <h3>{title}</h3>
        <p class="meta">{start_time} &ndash; {end_time}</p>

        {!-- Category badges --}
        {categories}
            <span class="badge" data-category="{cat_url_title}">{cat_name}</span>
        {/categories}

        {!-- Conditional: only show section if event has categories --}
        {if categories}
            <ul class="event-categories">
                {categories}
                    <li class="cat-{cat_url_title}">{cat_name}</li>
                {/categories}
            </ul>
        {/if}
    </article>
{/exp:event_calendar:events_list}',
        'params' => [],
        'vars'   => [
            ['{cat_id}',        'int',    'Category database ID'],
            ['{cat_name}',      'string', 'Category display name (HTML-escaped)'],
            ['{cat_url_title}', 'string', 'Category URL title (slug)'],
        ],
    ],

];

?>

<?php foreach ($templates as $key => $tpl): ?>
<div class="cal-tpl-section">
    <h3><?= htmlspecialchars($tpl['title']) ?></h3>
    <?php if ($tpl['desc']): ?>
        <p><?= htmlspecialchars($tpl['desc']) ?></p>
    <?php endif ?>
    <div class="cal-tpl-block">
        <button class="button button--default cal-tpl-copy" data-target="cal-tpl-<?= htmlspecialchars($key) ?>">
            <?= lang('copy') ?>
        </button>
        <pre id="cal-tpl-<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($tpl['code']) ?></pre>
    </div>

    <?php if (!empty($tpl['params'])): ?>
        <table class="cal-tpl-params">
            <thead>
                <tr><th><?= lang('tpl_param') ?></th><th><?= lang('tpl_values') ?></th><th><?= lang('tpl_default') ?></th><th><?= lang('tpl_notes') ?></th></tr>
            </thead>
            <tbody>
                <?php foreach ($tpl['params'] as [$param, $values, $default, $note]): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($param) ?></code></td>
                        <td><?= htmlspecialchars($values) ?></td>
                        <td><code><?= htmlspecialchars($default) ?></code></td>
                        <td><?= htmlspecialchars($note) ?></td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>

    <?php if (!empty($tpl['vars'])): ?>
        <table class="cal-tpl-params" style="margin-top:6px">
            <thead>
                <tr><th><?= lang('tpl_variable') ?></th><th><?= lang('tpl_type') ?></th><th><?= lang('tpl_notes') ?></th></tr>
            </thead>
            <tbody>
                <?php foreach ($tpl['vars'] as [$var, $type, $note]): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($var) ?></code></td>
                        <td><?= htmlspecialchars($type) ?></td>
                        <td><?= htmlspecialchars($note) ?></td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>
</div>
<?php endforeach ?>

<script>
document.querySelectorAll('.cal-tpl-copy').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var pre = document.getElementById(btn.dataset.target);
        navigator.clipboard.writeText(pre.textContent).then(function() {
            btn.textContent = '<?= lang('copied') ?>';
            btn.classList.add('copied');
            setTimeout(function() {
                btn.textContent = '<?= lang('copy') ?>';
                btn.classList.remove('copied');
            }, 2000);
        });
    });
});
</script>
