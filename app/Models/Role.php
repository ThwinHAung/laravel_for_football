<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;
    public function parentRole()
    {
        return $this->belongsTo(Role::class, 'parent_role_id');
    }
    public function users()
    {
        return $this->hasMany(User::class);
    }
    protected $fillable = ['name','parent_role_id'];
}
