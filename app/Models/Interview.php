<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Interview extends Model
{
    protected $fillable = [
        'candidacy_id',
    ];

    public function candidacy()
    {
        return $this->belongsTo(Candidacy::class);
    }

}
