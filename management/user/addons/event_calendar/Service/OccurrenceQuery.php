<?php

namespace Propagate\EventCalendar\Service;

/**
 * Resolves event rows + RRULE + exceptions into a flat array of
 * concrete occurrence DTOs for a given date range.
 *
 * This is the front-end query layer. The CP does NOT use this --
 * the CP queries the events table directly (events, not occurrences).
 */
class OccurrenceQuery
{
    private RecurrenceExpander $expander;

    public function __construct(?RecurrenceExpander $expander = null)
    {
        $this->expander = $expander ?? new RecurrenceExpander();
    }

    /**
     * @param int   $siteId
     * @param int   $rangeStart  UTC unix timestamp
     * @param int   $rangeEnd    UTC unix timestamp
     * @param array $filters     ['category_ids' => [int], 'status' => 'open']
     *
     * @return array  Array of occurrence DTOs, each:
     *     [
     *       'event_id'    => int,
     *       'title'       => string,
     *       'slug'        => string,
     *       'description' => string,
     *       'location'    => string,
     *       'url'         => string,
     *       'start_time'  => int (UTC),
     *       'end_time'    => int (UTC),
     *       'all_day'     => bool,
     *       'is_recurring'=> bool,
     *       'is_cancelled'=> bool,
     *       'categories'  => [['cat_id'=>int,'cat_name'=>string,'cat_url_title'=>string,'color'=>string]],
     *     ]
     */
    public function forRange(int $siteId, int $rangeStart, int $rangeEnd, array $filters = []): array
    {
        // PHASE 3 (Task 3.1): full implementation.
        // Phase 1 ships only the empty-array stub so MOD code can be built against it.
        return [];
    }
}
