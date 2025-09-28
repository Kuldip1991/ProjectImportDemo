<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserDiscount extends Model
{
    protected $fillable = ['user_id', 'discount_id', 'usage_count'];

    public function discount()
    {
        return $this->belongsTo(Discount::class);
    }

}
