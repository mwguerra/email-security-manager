<?php

namespace MWGuerra\EmailSecurityManager\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EmailSecurityAudit extends Model
{
    use HasFactory;

    protected $fillable = [
        'authenticatable_id',
        'authenticatable_type',
        'email',
        'verified_at',
        'password_changed_at',
        'triggered_by',
        'reason'
    ];

    protected $casts = [
        'verified_at' => 'datetime',
        'password_changed_at' => 'datetime',
    ];

    /**
     * Get the owning authenticatable model.
     */
    public function authenticatable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who triggered the action.
     */
    public function triggeredByAuthenticatable(): MorphTo
    {
        return $this->morphTo('triggered_by_authenticatable', 'triggered_by_type', 'triggered_by')
            ->withDefault(['name' => 'System']);
    }

    /**
     * Scope a query to only include recent verifications.
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope a query to only include password changes.
     */
    public function scopePasswordChanges($query)
    {
        return $query->whereNotNull('password_changed_at');
    }

    /**
     * Scope a query to only include email verifications.
     */
    public function scopeEmailVerifications($query)
    {
        return $query->whereNotNull('verified_at');
    }
}
