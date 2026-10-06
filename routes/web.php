<?php

use App\Http\Controllers\ApprovalsController;
use App\Http\Controllers\ConsoleController;
use App\Http\Controllers\CostsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectsController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\RoomsController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/rooms', [RoomsController::class, 'index'])->name('rooms.index');
    Route::get('/rooms/{room:number}', [RoomsController::class, 'show'])->name('rooms.show');

    Route::get('/console', [ConsoleController::class, 'index'])->name('console.index');
    Route::post('/console/preview', [ConsoleController::class, 'preview'])->name('console.preview');

    Route::get('/projects', [ProjectsController::class, 'index'])->name('projects.index');
    Route::get('/projects/{project:slug}', [ProjectsController::class, 'show'])->name('projects.show');

    Route::get('/approvals', [ApprovalsController::class, 'index'])->name('approvals.index');
    Route::get('/roles', [RolesController::class, 'index'])->name('roles.index');
    Route::get('/costs', [CostsController::class, 'index'])->name('costs.index');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Mengubah struktur tim: owner dan manager.
    Route::middleware('role:owner,manager')->group(function () {
        Route::post('/rooms', [RoomsController::class, 'store'])->name('rooms.store');
        Route::post('/rooms/{room:number}/agents', [RoomsController::class, 'storeAgent'])->name('rooms.agents.store');
        Route::delete('/rooms/{room:number}/agents/{agent}', [RoomsController::class, 'destroyAgent'])->name('rooms.agents.destroy');
        Route::post('/rooms/{room:number}/archive', [RoomsController::class, 'archive'])->name('rooms.archive');

        Route::post('/console/run', [ConsoleController::class, 'run'])->name('console.run');

        Route::post('/roles', [RolesController::class, 'store'])->name('roles.store');

        Route::post('/projects/{project:slug}/pause', [ProjectsController::class, 'pause'])->name('projects.pause');
        Route::post('/projects/{project:slug}/resume', [ProjectsController::class, 'resume'])->name('projects.resume');
        Route::post('/projects/{project:slug}/cancel', [ProjectsController::class, 'cancel'])->name('projects.cancel');
        Route::post('/projects/{project:slug}/tasks/{task}/retry', [ProjectsController::class, 'retry'])->name('projects.tasks.retry');
        Route::post('/projects/{project:slug}/tasks/{task}/revise', [ProjectsController::class, 'revise'])->name('projects.tasks.revise');
        Route::post('/projects/{project:slug}/members', [ProjectsController::class, 'storeMember'])->name('projects.members.store');
        Route::delete('/projects/{project:slug}/members/{member}', [ProjectsController::class, 'destroyMember'])->name('projects.members.destroy');
        Route::post('/projects/{project:slug}/stages/{stage}/move/{direction}', [ProjectsController::class, 'moveStage'])->name('projects.stages.move');
        Route::delete('/projects/{project:slug}/stages/{stage}', [ProjectsController::class, 'removeStage'])->name('projects.stages.remove');
        Route::post('/projects/{project:slug}/stages/reset', [ProjectsController::class, 'resetStages'])->name('projects.stages.reset');
    });

    // Menyetujui gerbang: owner, manager, dan approver.
    Route::middleware('role:owner,manager,approver')->group(function () {
        Route::post('/projects/{project:slug}/approvals/{approval}/approve', [ProjectsController::class, 'approve'])->name('projects.approvals.approve');
        Route::post('/projects/{project:slug}/approvals/{approval}/reject', [ProjectsController::class, 'reject'])->name('projects.approvals.reject');

        Route::post('/approvals/{approval}/approve', [ApprovalsController::class, 'approve'])->name('approvals.approve');
        Route::post('/approvals/{approval}/reject', [ApprovalsController::class, 'reject'])->name('approvals.reject');
    });

    // Pengaturan koneksi dan anggaran: owner saja.
    Route::middleware('role:owner')->group(function () {
        Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::post('/settings/test', [SettingsController::class, 'test'])->name('settings.test');
    });
});

require __DIR__.'/auth.php';
