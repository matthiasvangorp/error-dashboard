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
        'letsdothis_base_url',
        'letsdothis_project_token',
    ];

    protected $casts = [
        'alert_channels' => 'array',
        'event_retention_days' => 'integer',
        'rate_limit_per_minute' => 'integer',
        'letsdothis_project_token' => 'encrypted',
    ];

    protected $hidden = [
        'secret',
        'letsdothis_project_token',
    ];

    public function isLinkedToLetsdothis(): bool
    {
        return filled($this->letsdothis_base_url) && filled($this->letsdothis_project_token);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function users(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
