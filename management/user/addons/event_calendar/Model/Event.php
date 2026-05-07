<?php

namespace Propagate\EventCalendar\Model;

/**
 * Event model -- thin data class + persistence helpers for exp_calendar_events.
 *
 * Why a plain class instead of EE Model? Our queries (especially the front-end
 * occurrence query) are bespoke. EE Model adds overhead without benefit here.
 * We use ee()->db directly throughout.
 */
class Event
{
    public int $id = 0;
    public int $site_id = 1;
    public string $title = '';
    public string $slug = '';
    public string $description = '';
    public string $location = '';
    public string $url = '';
    public int $start_time = 0;
    public int $end_time = 0;
    public bool $all_day = false;
    public ?string $rrule = null;
    public ?int $recurrence_end = null;
    public string $status = 'open';
    public int $created_at = 0;
    public int $updated_at = 0;

    /** @var int[] Category IDs (loaded/saved alongside the event row) */
    public array $category_ids = [];

    public static function find(int $id, int $siteId): ?self
    {
        $row = ee()->db->where('id', $id)
            ->where('site_id', $siteId)
            ->get('calendar_events')
            ->row_array();

        if (!$row) return null;

        $event = self::fromRow($row);
        $event->category_ids = self::loadCategoryIds($id);
        return $event;
    }

    public static function fromRow(array $row): self
    {
        $e = new self();
        $e->id             = (int) $row['id'];
        $e->site_id        = (int) $row['site_id'];
        $e->title          = (string) $row['title'];
        $e->slug           = (string) $row['slug'];
        $e->description    = (string) $row['description'];
        $e->location       = (string) $row['location'];
        $e->url            = (string) $row['url'];
        $e->start_time     = (int) $row['start_time'];
        $e->end_time       = (int) $row['end_time'];
        $e->all_day        = (bool) $row['all_day'];
        $e->rrule          = $row['rrule'] !== null ? (string) $row['rrule'] : null;
        $e->recurrence_end = $row['recurrence_end'] !== null ? (int) $row['recurrence_end'] : null;
        $e->status         = (string) $row['status'];
        $e->created_at     = (int) $row['created_at'];
        $e->updated_at     = (int) $row['updated_at'];
        return $e;
    }

    /** @return int  Inserted/updated event ID */
    public function save(): int
    {
        $now = ee()->localize->now;
        $data = [
            'site_id'        => $this->site_id,
            'title'          => $this->title,
            'slug'           => $this->slug,
            'description'    => $this->description,
            'location'       => $this->location,
            'url'            => $this->url,
            'start_time'     => $this->start_time,
            'end_time'       => $this->end_time,
            'all_day'        => $this->all_day ? 1 : 0,
            'rrule'          => $this->rrule,
            'recurrence_end' => $this->recurrence_end,
            'status'         => $this->status,
            'updated_at'     => $now,
        ];

        if ($this->id > 0) {
            ee()->db->where('id', $this->id)->update('calendar_events', $data);
        } else {
            $data['created_at'] = $now;
            ee()->db->insert('calendar_events', $data);
            $this->id = (int) ee()->db->insert_id();
        }

        $this->saveCategoryIds($this->id, $this->category_ids);
        return $this->id;
    }

    public function delete(): bool
    {
        if ($this->id === 0) return false;
        // FK CASCADE handles exceptions and category pivot
        ee()->db->where('id', $this->id)->delete('calendar_events');
        return true;
    }

    public function isRecurring(): bool
    {
        return $this->rrule !== null && $this->rrule !== '';
    }

    private static function loadCategoryIds(int $eventId): array
    {
        $rows = ee()->db->select('cat_id')
            ->where('event_id', $eventId)
            ->get('calendar_event_categories')
            ->result_array();
        return array_map(fn($r) => (int) $r['cat_id'], $rows);
    }

    private function saveCategoryIds(int $eventId, array $catIds): void
    {
        // Idempotent: clear and re-insert. Pivot is small per-event.
        ee()->db->where('event_id', $eventId)->delete('calendar_event_categories');
        if (empty($catIds)) return;

        $rows = array_map(
            fn($cid) => ['event_id' => $eventId, 'cat_id' => (int) $cid],
            array_unique(array_filter($catIds, fn($c) => (int) $c > 0))
        );
        if (!empty($rows)) {
            ee()->db->insert_batch('calendar_event_categories', $rows);
        }
    }
}
