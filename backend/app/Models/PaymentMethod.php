<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use Auditable, HasFactory;

    protected $fillable = ['name', 'account_name', 'account_number', 'instructions', 'agent_locations', 'requires_reference', 'enabled', 'sort'];

    protected function casts(): array
    {
        return ['agent_locations' => 'array', 'requires_reference' => 'boolean', 'enabled' => 'boolean'];
    }
}
