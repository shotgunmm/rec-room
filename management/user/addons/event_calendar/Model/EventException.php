<?php

namespace Propagate\EventCalendar\Model;

/**
 * EventException model -- per-occurrence cancellations (and, in v2.1, overrides).
 *
 * v2.0 only ships type='cancelled'. The override_* columns exist in schema
 * for v2.1 forward-compatibility.
 */
class EventException
{
    public int $id = 0;
    public int $event_id = 0;
    public string $occurrence_date = ''; // YYYY-MM-DD
    public string $type = 'cancelled';
    public ?int $override_start = null;
    public ?int $override_end = null;
    public ?string $override_title = null;
    public ?string $override_location = null;
    public ?string $override_description = null;
    public int $created_at = 0;
    public int $updated_at = 0;

    /** Load all exceptions for an event in a date range. */
    public static function forEventInRange(int $eventId, int $rangeStart, int $rangeEnd): array
    {
        $startDate = date('Y-m-d', $rangeStart);
        $endDate   = date('Y-m-d', $rangeEnd);

        $rows = ee()->db->where('event_id', $eventId)
            ->where('occurrence_date >=', $startDate)
            ->where('occurrence_date <=', $endDate)
            ->get('calendar_event_exceptions')
            ->result_array();

        return array_map([self::class, 'fromRow'], $rows);
    }

    /** Load all exceptions for a set of event IDs in a date range (Phase 3 batch query). */
    public static function forEventsInRange(array $eventIds, int $rangeStart, int $rangeEnd): array
    {
        if (empty($eventIds)) return [];
        $startDate = date('Y-m-d', $rangeStart);
        $endDate   = date('Y-m-d', $rangeEnd);

        $rows = ee()->db->where_in('event_id', $eventIds)
            ->where('occurrence_date >=', $startDate)
            ->where('occurrence_date <=', $endDate)
            ->get('calendar_event_exceptions')
            ->result_array();

        return array_map([self::class, 'fromRow'], $rows);
    }

    public static function fromRow(array $row): self
    {
        $x = new self();
        $x->id                   = (int) $row['id'];
        $x->event_id             = (int) $row['event_id'];
        $x->occurrence_date      = (string) $row['occurrence_date'];
        $x->type                 = (string) $row['type'];
        $x->override_start       = $row['override_start'] !== null ? (int) $row['override_start'] : null;
        $x->override_end         = $row['override_end'] !== null ? (int) $row['override_end'] : null;
        $x->override_title       = $row['override_title'];
        $x->override_location    = $row['override_location'];
        $x->override_description = $row['override_description'];
        $x->created_at           = (int) $row['created_at'];
        $x->updated_at           = (int) $row['updated_at'];
        return $x;
    }

    public function save(): int
    {
        $now = ee()->localize->now;
        $data = [
            'event_id'             => $this->event_id,
            'occurrence_date'      => $this->occurrence_date,
            'type'                 => $this->type,
            'override_start'       => $this->override_start,
            'override_end'         => $this->override_end,
            'override_title'       => $this->override_title,
            'override_location'    => $this->override_location,
            'override_description' => $this->override_description,
            'updated_at'           => $now,
        ];

        if ($this->id > 0) {
            ee()->db->where('id', $this->id)->update('calendar_event_exceptions', $data);
        } else {
            $data['created_at'] = $now;
            ee()->db->insert('calendar_event_exceptions', $data);
            $this->id = (int) ee()->db->insert_id();
        }
        return $this->id;
    }

    public function delete(): bool
    {
        if ($this->id === 0) return false;
        ee()->db->where('id', $this->id)->delete('calendar_event_exceptions');
        return true;
    }

    /** Cancel an occurrence on a given date. Idempotent (UNIQUE KEY in schema). */
    public static function cancel(int $eventId, string $occurrenceDate): self
    {
        $existing = ee()->db->where('event_id', $eventId)
            ->where('occurrence_date', $occurrenceDate)
            ->get('calendar_event_exceptions')
            ->row_array();

        if ($existing) {
            $x = self::fromRow($existing);
            $x->type = 'cancelled';
            $x->save();
            return $x;
        }

        $x = new self();
        $x->event_id = $eventId;
        $x->occurrence_date = $occurrenceDate;
        $x->type = 'cancelled';
        $x->save();
        return $x;
    }

    /** Restore (uncancel) an occurrence by deleting its exception row. */
    public static function restore(int $eventId, string $occurrenceDate): bool
    {
        ee()->db->where('event_id', $eventId)
            ->where('occurrence_date', $occurrenceDate)
            ->delete('calendar_event_exceptions');
        return ee()->db->affected_rows() > 0;
    }
}
