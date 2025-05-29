<?php

namespace App\Http\Controllers;

use App\Models\Period;
use App\Models\StatusHistorique;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PeriodController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Period::orderBy('year', 'desc');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('year', 'LIKE', "%{$search}%");
        }

        if ($request->filled('status')) {
            $status = $request->input('status');
            $query->where('status', 'LIKE', "%{$status}%");
        }

        $perPage = $request->input('per_page', 3);

        try {
            $paginated = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $paginated
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la récupération des périodes.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function store(Request $request): JsonResponse
    {
        $user_id = $request->user()->id;
        $data = $request->validate([
            'year' => 'integer|unique:periods,year|nullable',
        ]);

        $yearNow = now()->year;
        $status = 'Dispatch';

        if( isset($data['year']) && $data['year'] != null){
            $yearNow= $data['year'];
        }

        try {
            $period = Period::create([
                'year' => $yearNow,
                'status' => $status
            ]);
            StatusHistorique::create([
                'period_id' => $period->id,
                'user_id' => $user_id,
                'status' => $period->status
            ]);
            return response()->json([
                'success' => true,
                'data' => $period
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la création de la période.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $period = Period::findOrFail($id);
            return response()->json([
                'success' => true,
                'data' => $period
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Période non trouvée.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
