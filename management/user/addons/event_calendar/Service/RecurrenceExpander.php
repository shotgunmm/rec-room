<?php

namespace Propagate\EventCalendar\Service;

use DateTimeImmutable;
use DateTimeZone;
use Sabre\VObject\Recur\RRuleIterator;

/**
 * Expands an RFC 5545 RRULE string into concrete occurrence start times
 * for a given date range.
 *
 * Recurrence runs in wall-clock time of the site time zone, so an event at 6:00 PM stays at
 * 6:00 PM across daylight saving changes (a fixed UTC time would drift by an hour). Weekdays in
 * BYDAY are likewise local weekdays. Occurrence timestamps are still plain UTC unix timestamps.
 *
 * Stateless. Safe to instantiate per-query.
 */
class RecurrenceExpander
{
    /** Safety limit on iterator steps, counting occurrences before the range as well. */
    private const MAX_STEPS = 5000;

    private DateTimeZone $zone;

    /**
     * @param DateTimeZone|null $zone Zone recurrence runs in. Defaults to PHP's default zone,
     *                                which ExpressionEngine sets to the site time zone -- the same
     *                                zone the CP forms use (mktime/date) to read and write times.
     */
    public function __construct(?DateTimeZone $zone = null)
    {
        $this->zone = $zone ?? new DateTimeZone(date_default_timezone_get());
    }

    /**
     * Expand a recurring event into occurrence start timestamps that fall
     * within [$rangeStart, $rangeEnd].
     *
     * @param string $rrule         RRULE string, e.g. "FREQ=WEEKLY;BYDAY=MO,WE"
     * @param int    $eventStart    UTC unix timestamp of the FIRST occurrence; its local
     *                              time of day is the time every occurrence keeps
     * @param int    $rangeStart    UTC unix timestamp -- range start (inclusive)
     * @param int    $rangeEnd      UTC unix timestamp -- range end (inclusive)
     * @param int    $hardCap       Max occurrences to RETURN (e.g. the first 6 after the range
     *                              start). Occurrences before the range do not count against it;
     *                              a separate step limit (MAX_STEPS) guards against runaway rules.
     * @param int|null $now         "Now" as a UTC unix timestamp (defaults to time()). An event
     *                              whose $eventStart is more than a year before this is treated as
     *                              starting one year back, so nothing older than that is returned.
     *
     * @return int[]  Array of occurrence start UTC unix timestamps (sorted asc)
     */
    public function expand(
        string $rrule,
        int $eventStart,
        int $rangeStart,
        int $rangeEnd,
        int $hardCap = 500,
        ?int $now = null
    ): array {
        if ($eventStart > $rangeEnd) {
            return [];
        }

        // An entry whose start is more than a year old is treated as starting one year back.
        $floor = strtotime('-1 year', $now ?? time());
        if ($eventStart < $floor) {
            if ($rangeEnd < $floor) {
                return [];
            }
            $eventStart = $this->anchorAtOrBefore($rrule, $eventStart, $floor);
            $rangeStart = max($rangeStart, $floor);
        }

        $startDt = (new DateTimeImmutable('@' . $eventStart))->setTimezone($this->zone);

        try {
            $iter = new RRuleIterator($rrule, $startDt);
        } catch (\Throwable $e) {
            // Invalid RRULE -- fail closed (no occurrences) rather than 500
            return [];
        }

        $byDays = $this->weeklyByDays($rrule);

        $occurrences = [];
        $steps = 0;

        while ($iter->valid() && count($occurrences) < $hardCap && $steps < self::MAX_STEPS) {
            $current = $iter->current();
            $ts = $current->getTimestamp();

            if ($ts > $rangeEnd) {
                break;
            }
            // RFC 5545 always counts DTSTART as an occurrence, even when it falls on a weekday
            // outside BYDAY (e.g. a start date that is not a Wednesday for "every Wednesday").
            if ($ts >= $rangeStart && ($byDays === [] || in_array((int) $current->format('w'), $byDays, true))) {
                $occurrences[] = $ts;
            }

            $iter->next();
            $steps++;
        }

        return $occurrences;
    }

    /**
     * The occurrence to describe when no specific date is asked for (event detail page without a
     * date, and the date fields of a recurring row in events_list).
     *
     * That is the next occurrence starting today or later (today counts all day, so an event
     * that already started this afternoon is still "today"). If the series has no upcoming
     * occurrence (it has ended), the latest earlier one within the one-year lookback. Null when
     * there is none at all; callers then fall back to the stored start.
     *
     * @return int|null UTC unix timestamp of the occurrence start
     */
    public function representativeOccurrence(string $rrule, int $eventStart, ?int $now = null): ?int
    {
        $now ??= time();
        $todayStart = (new DateTimeImmutable('@' . $now))->setTimezone($this->zone)->setTime(0, 0)->getTimestamp();

        $ahead = $this->expand($rrule, $eventStart, $todayStart, $todayStart + 400 * 86400, 500, $now);
        if ($ahead !== []) {
            return $ahead[0];
        }

        $past = $this->expand($rrule, $eventStart, $eventStart, $todayStart - 1, 500, $now);

        return $past !== [] ? end($past) : null;
    }

    /**
     * Weekday numbers (0 = Sunday ... 6 = Saturday) named by BYDAY on a WEEKLY rule, or an empty
     * array when the rule has no such restriction.
     *
     * @return int[]
     */
    private function weeklyByDays(string $rrule): array
    {
        if (!preg_match('/FREQ=WEEKLY/i', $rrule)
            || !preg_match('/BYDAY=([A-Z]{2}(?:,[A-Z]{2})*)/i', $rrule, $m)) {
            return [];
        }

        $map  = ['SU' => 0, 'MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6];
        $days = [];
        foreach (explode(',', strtoupper($m[1])) as $code) {
            if (isset($map[$code])) {
                $days[] = $map[$code];
            }
        }

        return $days;
    }

    /**
     * Move the anchor forward by whole recurrence periods to the last one at or before $floor.
     *
     * Expansion walks occurrence by occurrence from the anchor and stops at the hard cap, so an
     * anchor far in the past (recurring events are stored on a 1970-01-01 time-of-day anchor)
     * would use up the cap long before reaching today and yield nothing. Shifting by a whole
     * number of days/weeks (in local time, so the wall-clock time survives daylight saving
     * changes) keeps the interval phase intact. Rules with COUNT depend on the true first
     * occurrence, and only DAILY/WEEKLY periods are fixed-length, so those are left alone.
     */
    private function anchorAtOrBefore(string $rrule, int $eventStart, int $floor): int
    {
        if (preg_match('/COUNT=/i', $rrule)
            || !preg_match('/FREQ=(DAILY|WEEKLY)/i', $rrule, $freq)) {
            return $eventStart;
        }

        $interval = preg_match('/INTERVAL=(\d+)/i', $rrule, $m) ? max(1, (int) $m[1]) : 1;
        $unit     = strtoupper($freq[1]) === 'WEEKLY' ? 'weeks' : 'days';
        $period   = ($unit === 'weeks' ? 7 : 1) * 86400 * $interval;

        // seconds-based estimate of the number of periods, then settled in local time
        $steps = intdiv($floor - $eventStart, $period);
        if ($steps <= 0) {
            return $eventStart;
        }

        $start  = (new DateTimeImmutable('@' . $eventStart))->setTimezone($this->zone);
        $anchor = $start->modify('+' . ($steps * $interval) . ' ' . $unit);
        while ($steps > 0 && $anchor->getTimestamp() > $floor) {
            $steps--;
            $anchor = $start->modify('+' . ($steps * $interval) . ' ' . $unit);
        }

        return $anchor->getTimestamp();
    }

    /**
     * Compute the absolute end of recurrence for storage in
     * exp_calendar_events.recurrence_end.
     *
     * Returns NULL for infinite recurrences (no UNTIL, no COUNT).
     * Returns the timestamp of the last occurrence's start otherwise.
     */
    public function computeRecurrenceEnd(string $rrule, int $eventStart): ?int
    {
        if (!preg_match('/(UNTIL|COUNT)=/i', $rrule)) {
            return null;
        }

        $startDt = (new DateTimeImmutable('@' . $eventStart))->setTimezone($this->zone);

        try {
            $iter = new RRuleIterator($rrule, $startDt);
        } catch (\Throwable $e) {
            return null;
        }

        $last = null;
        $hardCap = 10000;
        $count = 0;

        while ($iter->valid() && $count < $hardCap) {
            $last = $iter->current()->getTimestamp();
            $iter->next();
            $count++;
        }

        return $last;
    }

    /**
     * Validate an RRULE string. Returns null on valid, error message on invalid.
     */
    public function validate(string $rrule, int $eventStart): ?string
    {
        $startDt = (new DateTimeImmutable('@' . $eventStart))->setTimezone($this->zone);

        try {
            $iter = new RRuleIterator($rrule, $startDt);
            if ($iter->valid()) {
                $iter->current();
            }
        } catch (\Throwable $e) {
            return $e->getMessage();
        }

        return null;
    }
}
