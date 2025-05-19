<?php

namespace App\Http\Controllers;

use App\Models\Period;
use Illuminate\Http\Request;

class PeriodController extends Controller
{
    public function index()
    {
        return response()->json(Period::all());
    }

    public function store(Request $request)
    {
        $year = now()->year;

        if (Period::where('year', $year)->exists()) {
            return response()->json(['message' => 'Une période pour cette année existe déjà.'], 400);
        }

        $period = Period::create([
            'year' => $year
        ]);

        return response()->json($period, 201);
    }

    public function show(Period $period)
    {
        //
    }

    public function update(Request $request, Period $period)
    {
        //
    }

    public function destroy(Period $period)
    {
        //
    }
}
