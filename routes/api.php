<?php

use App\Http\Controllers\AuthenticationController;
use App\Http\Controllers\CandidacyController;
use App\Http\Controllers\CriteriaController;
use App\Http\Controllers\EvaluationFinaleController;
use App\Http\Controllers\PeriodController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PreselectionController;
use App\Http\Controllers\UserController;

Route::post('login', AuthenticationController::class);
Route::apiResource('users', UserController::class)->only(['store']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::apiResource('users', UserController::class)->except(['store']);
    Route::apiResource('candidacies', CandidacyController::class)->only(['index', 'destroy']);
    Route::apiResource('evaluationFinale', EvaluationFinaleController::class)->except(['index','show']);
    Route::apiResource('preselection', PreselectionController::class)->except(['index','show']);

    Route::post('/uploadCandidacies',[CandidacyController::class,"uploadCandidacies"]);
    Route::post('/uploadCandidaciesDocs',[CandidacyController::class,"uploadCandidaciesDocs"]);
    Route::get('/getDoc',[CandidacyController::class,"getDoc"]);
    Route::get('/getPreselectedCandidacies',[CandidacyController::class,"getPreselectedCandidacies"]);
    Route::get('/getCandidacy',[CandidacyController::class,"getCandidacy"]);

    Route::post('/saveEvaluators',[EvaluationFinaleController::class,"saveEvaluators"]);
    Route::apiResource('period', PeriodController::class);
    Route::post('period-search', [PeriodController::class, 'search']);
    Route::apiResource('criteria', CriteriaController::class);
    Route::post('periods/attach-criteria/{id}', [PeriodController::class, 'attachCriteriaToPeriod']);
    Route::get('periods/criteria/{id}', [PeriodController::class, 'getCriteriaForPeriod']);

});



