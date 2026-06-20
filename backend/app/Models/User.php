<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function customerGroups()
    {
        return $this->morphToMany(
            config('customer_groups.models.customer_group'),
            'model',
            config('customer_groups.table_names.model_has_customer_groups'),
            config('customer_groups.column_names.model_morph_key') ?? 'model_id',
            config('customer_groups.column_names.customer_group_pivot_key') ?? 'customer_group_id'
        );
    }
}
