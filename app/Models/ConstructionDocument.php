<?php

namespace App\Models;

use App\Models\Company;
use App\Models\Project;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\URL;

class ConstructionDocument extends Model
{
    protected $table = 'construction_documents';

    protected $fillable = [
        'company_id',
        'project_id',
        'documentable_type',
        'documentable_id',
        'folder',
        'file_name',
        'original_name',
        'mime_type',
        'file_size',
        'disk',
        'path',
        'version_no',
        'uploaded_by_type',
        'uploaded_by_id',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploadedBy(): MorphTo
    {
        return $this->morphTo();
    }

    protected function signedViewUrl(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                try {
                    $ttlMinutes = (int) config('media.signed_view_ttl_minutes', 30);
                    $expires = now()->addMinutes(max(1, $ttlMinutes));
                    return URL::temporarySignedRoute(
                        name: 'signed_documents.view',
                        expiration: $expires,
                        parameters: ['document' => $this->getKey()],
                        absolute: true,
                    );
                } catch (\Throwable) {
                    return null;
                }
            },
        )->shouldCache();
    }
}
