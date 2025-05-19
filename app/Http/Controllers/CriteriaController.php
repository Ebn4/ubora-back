<?php

namespace App\Http\Controllers;

use App\Models\Criteria;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CriteriaController extends Controller
{
    public function index(): JsonResponse
    {
        $criteria = Criteria::all();

        return response()->json([
            'success' => true,
            'data' => $criteria
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string',
        ]);

        try {
            $criteria = Criteria::create($request->only(['name']));

            return response()->json([
                'success' => true,
                'message' => 'Critère créé avec succès.',
                'data' => $criteria
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du critère.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        $criteria = Criteria::find($id);

        if (!$criteria) {
            return response()->json([
                'success' => false,
                'message' => 'Critère non trouvé.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $criteria
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|string|unique:criterias,name,' . $id,
        ]);

        $criteria = Criteria::find($id);

        if (!$criteria) {
            return response()->json([
                'success' => false,
                'message' => 'Critère non trouvé.'
            ], 404);
        }

        try {
            $criteria->update($request->only(['name']));

            return response()->json([
                'success' => true,
                'message' => 'Critère mis à jour avec succès.',
                'data' => $criteria
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du critère.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        $criteria = Criteria::find($id);

        if (!$criteria) {
            return response()->json([
                'success' => false,
                'message' => 'Critère non trouvé.'
            ], 404);
        }

        try {
            $criteria->delete();

            return response()->json([
                'success' => true,
                'message' => 'Critère supprimé avec succès.'
            ], 204);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du critère.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
