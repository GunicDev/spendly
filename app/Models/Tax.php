<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tax extends Model
{
    use HasFactory;

    protected $fillable = [
        'tax_name',
        'tax_rate',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'tax_rate' => 'decimal:2',
            'is_default' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (Tax $tax): void {
            if (! $tax->is_default) {
                return;
            }

            static::query()
                ->whereKeyNot($tax->getKey())
                ->where('is_default', true)
                ->update(['is_default' => false]);
        });
    }
}
