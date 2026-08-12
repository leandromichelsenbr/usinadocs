<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Site extends Model
{
    protected $fillable = ['name', 'slug'];

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }
}
