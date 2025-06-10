<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Evaluator extends Model
{
    protected $fillable = ['user_id', 'period_id', 'type'];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function period(): \Illuminate\Database\Eloquent\Relations\BelongsTo{
        return $this->belongsTo(Period::class);
    }
}
