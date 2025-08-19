<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    protected $fillable = [
        'name',
        'display_name',
        'description',
        'is_default'
    ];

    protected $casts = [
        'is_default' => 'boolean'
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles');
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    public function hasPermission($permission)
    {
        if (is_string($permission)) {
            return $this->permissions()->where('name', $permission)->exists();
        }
        return $this->permissions()->where('id', $permission->id)->exists();
    }

    public function hasAnyPermission($permissions)
    {
        if (is_array($permissions)) {
            return $this->permissions()->whereIn('name', $permissions)->exists();
        }
        return $this->hasPermission($permissions);
    }

    public function hasAllPermissions($permissions)
    {
        if (is_array($permissions)) {
            return $this->permissions()->whereIn('name', $permissions)->count() === count($permissions);
        }
        return $this->hasPermission($permissions);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}
