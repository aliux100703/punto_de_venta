<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Contracts\Auth\Authenticatable;

class AuditLogger
{
    public static function record(string $event, ?Authenticatable $actor = null, ?string $subject = null, ?int $id = null, array $old = [], array $new = []): void
    {
        AuditLog::create([
            'user_id' => $actor?->getAuthIdentifier(), 'actor_name' => $actor?->name,
            'event' => $event, 'subject_type' => $subject, 'subject_id' => $id,
            'ip_address' => request()->ip(), 'user_agent' => mb_substr(request()->userAgent() ?? '', 0, 500),
            'old_values' => $old ?: null, 'new_values' => $new ?: null, 'created_at' => now(),
        ]);
    }
}
