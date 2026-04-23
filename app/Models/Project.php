<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'token',
        'secret',
        'event_retention_days',
        'rate_limit_per_minute',
        'alert_channels',
    ];

    protected $casts = [
        'alert_channels' => 'array',
        'event_retention_days' => 'integer',
        'rate_limit_per_minute' => 'integer',
    ];

    protected $hidden = [
        'secret',
    ];

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }
}
