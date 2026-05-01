<?php

namespace App\Traits;

use App\Models\Audit;

trait Auditable
{
    public function audit($action, $description, $metadata = [], $performerId = null)
    {
        return Audit::create([
            'user_id' => $this->id,
            'performer_id' => $performerId ?? auth()->id(),
            'action' => $action,
            'description' => $description,
            'metadata' => array_merge($metadata, [
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]),
        ]);
    }
}
