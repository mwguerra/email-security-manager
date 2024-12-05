<?php

namespace MWGuerra\EmailSecurityManager\Traits;

use MWGuerra\EmailSecurityManager\Models\EmailSecurityAudit;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasEmailSecurity
{
    /**
     * Get all security audits for the authenticatable.
     */
    public function securityAudits(): MorphMany
    {
        return $this->morphMany(EmailSecurityAudit::class, 'authenticatable');
    }

    /**
     * Get security audits where this authenticatable was the trigger.
     */
    public function triggeredSecurityAudits(): MorphMany
    {
        return $this->morphMany(EmailSecurityAudit::class, 'triggered_by_authenticatable');
    }
}