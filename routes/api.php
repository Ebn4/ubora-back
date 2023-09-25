<?php

use App\Http\Controllers\CandidacyController;
use App\Http\Controllers\EvaluationFinaleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\PreselectionController;
use App\Http\Controllers\UserController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/login', [LoginController::class, "login"]);
Route::post('/checkOtp', [LoginController::class, "checkOtp"]);

Route::get('/getUsers', [UserController::class, "getUsers"]);
Route::get('/getUser', [UserController::class, "getUser"]);
Route::post('/updateUser', [UserController::class, "updateUser"]);
Route::post('/createUser', [UserController::class, "createUser"]);
Route::delete('deleteUser', [UserController::class, "deleteUser"]);

Route::post('/uploadCandidacies',[CandidacyController::class,"uploadCandidacies"]);
Route::post('/uploadCandidaciesDocs',[CandidacyController::class,"uploadCandidaciesDocs"]);
Route::get('/getDoc',[CandidacyController::class,"getDoc"]);
Route::get('/getAllCandidacies',[CandidacyController::class,"getAllCandidacies"]);
Route::get('/getPreselectedCandidacies',[CandidacyController::class,"getPreselectedCandidacies"]);
Route::get('/getCandidacy',[CandidacyController::class,"getCandidacy"]);
Route::delete('/deleteCandidacy',[CandidacyController::class,"deleteCandidacy"]);

Route::post('/validatePreselection',[PreselectionController::class,"createPreselection"]);
Route::post('/updatePreselection',[PreselectionController::class,"updatePreselection"]);
Route::delete('/deletePreselection',[PreselectionController::class,"deletePreselection"]);

Route::post('/saveEvaluators',[EvaluationFinaleController::class,"saveEvaluators"]);



Route::post('/createEvaluationFinale',[EvaluationFinaleController::class,"createEvaluationFinale"]);
Route::post('/updateEvaluationFinale',[EvaluationFinaleController::class,"updateEvaluationFinale"]);
Route::delete('/deleteEvaluationFinale',[EvaluationFinaleController::class,"deleteEvaluationFinale"]);