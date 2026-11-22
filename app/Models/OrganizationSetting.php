<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class OrganizationSetting extends Model
{
    protected $fillable = [
        'name',
        'short_name',
        'tagline',
        'email',
        'phone',
        'website',
        'address',
        'report_footer',
        'logo_path',
        'logo_disk',
    ];

    public static function current(): self
    {
        $existing = static::query()->first();
        if ($existing) {
            return $existing;
        }

        return static::query()->create([
            'name' => 'Mulago Specialized Womens and Neonatal Hospital',
            'short_name' => 'MWH WorkHub',
            'tagline' => 'Hospital operations',
            'email' => null,
            'phone' => null,
            'website' => null,
            'address' => null,
            'report_footer' => 'Confidential — for official hospital use only',
        ]);
    }

    public function logoUrl(): ?string
    {
        if (! $this->logo_path || ! $this->logo_disk) {
            return null;
        }

        if ($this->logo_disk === 'public') {
            return Storage::disk('public')->url($this->logo_path);
        }

        return null;
    }

    public function logoDataUri(): ?string
    {
        if (! $this->logo_path || ! $this->logo_disk) {
            return null;
        }

        $disk = Storage::disk($this->logo_disk);
        if (! $disk->exists($this->logo_path)) {
            return null;
        }

        $binary = $disk->get($this->logo_path);
        $mime = $disk->mimeType($this->logo_path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($binary);
    }
}
