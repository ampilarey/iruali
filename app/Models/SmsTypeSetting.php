<?php

namespace App\Models;

use App\Support\SmsTypes;
use Illuminate\Database\Eloquent\Model;

/**
 * An admin's choice for one SMS type (App\Support\SmsTypes): on or off, and its own wording in
 * English and/or Dhivehi (empty: the built-in wording).
 */
class SmsTypeSetting extends Model
{
    protected $fillable = ['key', 'enabled', 'template_en', 'template_dv', 'updated_by'];

    protected $casts = ['enabled' => 'boolean'];

    protected static function booted(): void
    {
        static::saved(fn () => SmsTypes::forget());
        static::deleted(fn () => SmsTypes::forget());
    }
}
