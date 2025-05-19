<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Period extends Model
{
    protected $fillable = [
        'year'
    ];

    public function criteria()
    {
        return $this->belongsToMany(Criteria::class, 'period_criteria');
    }
}
