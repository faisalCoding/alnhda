<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One day of what the web server itself served.
 */
class ServerLogDay extends Model
{
    /** @use HasFactory<\Database\Factories\ServerLogDayFactory> */
    use HasFactory;

    protected $fillable = [
        'date',
        'requests',
        'unique_addresses',
        'bytes',
        'bot_requests',
        'status_2xx',
        'status_3xx',
        'status_4xx',
        'status_5xx',
        'top_paths',
        'top_bots',
        'not_found',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'requests' => 'integer',
            'unique_addresses' => 'integer',
            'bytes' => 'integer',
            'bot_requests' => 'integer',
            'status_2xx' => 'integer',
            'status_3xx' => 'integer',
            'status_4xx' => 'integer',
            'status_5xx' => 'integer',
            'top_paths' => 'array',
            'top_bots' => 'array',
            'not_found' => 'array',
        ];
    }

    /**
     * Requests that were not a crawler, which is the closest a log gets to a
     * count of people.
     */
    public function humanRequests(): int
    {
        return max(0, $this->requests - $this->bot_requests);
    }
}
