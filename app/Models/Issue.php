<?php

namespace App\Models;

use App\Enums\IssueLevel;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Issue extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'fingerprint',
        'title',
        'type',
        'level',
        'status',
        'environment',
        'first_seen_at',
        'last_seen_at',
        'occurrence_count',
        'last_event_id',
        'letsdothis_ticket_id',
        'letsdothis_ticket_url',
    ];

    protected $casts = [
        'type' => IssueType::class,
        'level' => IssueLevel::class,
        'status' => IssueStatus::class,
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'occurrence_count' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function lastEvent(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'last_event_id');
    }
}
