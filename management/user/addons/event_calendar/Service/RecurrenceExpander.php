<?php

namespace Propagate\EventCalendar\Service;

use DateTimeImmutable;
use DateTimeZone;
use Sabre\VObject\Recur\RRuleIterator;

/**
 * Expands an RFC 5545 RRULE string into concrete occurrence start times
 * for a given date range.
 *
 * Stateless. Safe to instantiate per-query.
 */
class RecurrenceExpander
{
    /**
     * Expand a recurring event into occurrence start timestamps that fall
     * within [$rangeStart, $rangeEnd].
     *
     * @param string $rrule         RRULE string, e.g. "FREQ=WEEKLY;BYDAY=MO,WE"
     * @param int    $eventStart    UTC unix timestamp of the FIRST occurrence
     * @param int    $rangeStart    UTC unix timestamp -- range start (inclusive)
     * @param int    $rangeEnd      UTC unix timestamp -- range end (inclusive)
     * @param int    $hardCap       Max occurrences to return (safety against runaway rules)
     *
     * @return int[]  Array of occurrence start UTC unix timestamps (sorted asc)
     */
    public function expand(
        string $rrule,
        int $eventStart,
        int $rangeStart,
        int $rangeEnd,
        int $hardCap = 500
    ): array {
        if ($eventStart > $rangeEnd) {
            return [];
        }

        $tz = new DateTimeZone('UTC');
        $startDt = (new DateTimeImmutable('@' . $eventStart))->setTimezone($tz);

        try {
            $iter = new RRuleIterator($rrule, $startDt);
        } catch (\Throwable $e) {
            // Invalid RRULE -- fail closed (no occurrences) rather than 500
            return [];
        }

        $occurrences = [];
        $count = 0;

        while ($iter->valid() && $count < $hardCap) {
            $current = $iter->current();
            $ts = $current->getTimestamp();

            if ($ts > $rangeEnd) {
                break;
            }
            if ($ts >= $rangeStart) {
                $occurrences[] = $ts;
            }

            $iter->next();
            $count++;
        }

        return $occurrences;
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

        $tz = new DateTimeZone('UTC');
        $startDt = (new DateTimeImmutable('@' . $eventStart))->setTimezone($tz);

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
        $tz = new DateTimeZone('UTC');
        $startDt = (new DateTimeImmutable('@' . $eventStart))->setTimezone($tz);

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
