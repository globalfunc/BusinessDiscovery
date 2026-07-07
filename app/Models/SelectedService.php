<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SelectedService extends Model
{
    use HasFactory;

    protected $fillable = [
        'discovery_session_id',
        'service_id',
        'custom',
        'name',
        'description',
        'features',
        'priority',
        'note',
        'origin',
        'reference_links',
        'price_min',
        'price_max',
    ];

    protected function casts(): array
    {
        return [
            'custom' => 'boolean',
            'features' => 'array',
            'priority' => 'boolean',
            'reference_links' => 'array',
            'price_min' => 'integer',
            'price_max' => 'integer',
        ];
    }

    public function discoverySession(): BelongsTo
    {
        return $this->belongsTo(DiscoverySession::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function toDiscoveryArray(): array
    {
        return [
            'id' => $this->id,
            'service_id' => $this->service_id,
            'custom' => $this->custom,
            'name' => $this->name,
            'description' => $this->description,
            'features' => $this->features ?? [],
            'priority' => $this->priority,
            'note' => $this->note,
            'origin' => $this->origin,
            'reference_links' => $this->reference_links ?? [],
            'price_min' => $this->price_min,
            'price_max' => $this->price_max,
        ];
    }
}
