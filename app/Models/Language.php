<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Language extends Model
{
    protected $fillable = ['code', 'route_key', 'name', 'native_name'];

    public function localizations(): HasMany
    {
        return $this->hasMany(PageLocalization::class);
    }
}
