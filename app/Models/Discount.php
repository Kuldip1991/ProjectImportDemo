<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{
    protected $fillable = ['code', 'percentage', 'active', 'expires_at', 'max_usage'];

    public function userDiscounts()
    {
        return $this->hasMany(UserDiscount::class);
    }

}
