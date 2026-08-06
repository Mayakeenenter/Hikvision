<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class HikvisionEvent extends Model
{
    use HasFactory;

 protected $fillable = [
    'event_id',
    'employee_id',
    'employee_name',
    'card_number',
    'card_reader_id',
    'event_type',
    'sub_type',
    'major_type',
    'status_badge',
    'recorded_at',
    'event_date',
    'event_time',
    'remote_host',
    'raw_payload',
    'synced_to_server',
];

 protected $casts = [
    'recorded_at' => 'datetime',
    'event_date' => 'date',
    'raw_payload' => 'array',
    'synced_to_server' => 'boolean',
];


    /**
     * Scope to search by term (employee name, ID, card number, or event type)
     */
    public function scopeSearch($query, ?string $term)
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('employee_name', 'like', "%{$term}%")
              ->orWhere('employee_id', 'like', "%{$term}%")
              ->orWhere('card_number', 'like', "%{$term}%")
              ->orWhere('event_type', 'like', "%{$term}%")
              ->orWhere('sub_type', 'like', "%{$term}%");
        });
    }

    /**
     * Helper to format recording date for presentation (e.g. 07-31-2026)
     */
    public function getFormattedDateAttribute(): string
    {
        return $this->recorded_at 
            ? $this->recorded_at->format('m-d-Y') 
            : ($this->event_date ? Carbon::parse($this->event_date)->format('m-d-Y') : '--');
    }

    /**
     * Helper to format recording time for presentation (e.g. 15:51:05)
     */
    public function getFormattedTimeAttribute(): string
    {
        return $this->recorded_at 
            ? $this->recorded_at->format('H:i:s') 
            : ($this->event_time ?? '--');
    }

    /**
     * Full date time string formatted like Hikvision UI: MM-DD-YYYY HH:mm:ss
     */
    public function getFormattedDateTimeAttribute(): string
    {
        return $this->recorded_at 
            ? $this->recorded_at->format('m-d-Y H:i:s') 
            : "{$this->formatted_date} {$this->formatted_time}";
    }
}
