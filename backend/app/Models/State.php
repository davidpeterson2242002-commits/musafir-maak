<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class State extends Model
{
    use Auditable;

    protected $fillable = ['name_ar', 'name_en', 'enabled', 'sort'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }
}
