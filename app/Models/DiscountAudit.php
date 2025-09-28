<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscountAudit extends Model
{
    protected $fillable = ['user_id', 'discount_id', 'context', 'amount'];
}
