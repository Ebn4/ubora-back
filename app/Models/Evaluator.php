<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Evaluator extends Model
{
    protected $fillable = ['user_id', 'period_id', 'type'];
}
