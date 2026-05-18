<?php

use App\Http\Controllers\Admin\AdminFamilyMembersController;
use App\Http\Controllers\Admin\AdminMessagesController;
use App\Http\Controllers\Admin\AdminRemindersController;
use App\Http\Controllers\Admin\AdminTasksController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('messages', [AdminMessagesController::class, 'index'])->name('messages.index');
        Route::get('messages/{message}', [AdminMessagesController::class, 'show'])->name('messages.show');
        Route::get('tasks', [AdminTasksController::class, 'index'])->name('tasks.index');
        Route::get('tasks/{task}', [AdminTasksController::class, 'show'])->name('tasks.show');
        Route::get('reminders', [AdminRemindersController::class, 'index'])->name('reminders.index');
        Route::get('reminders/{reminder}', [AdminRemindersController::class, 'show'])->name('reminders.show');
        Route::get('family-members', [AdminFamilyMembersController::class, 'index'])->name('family-members.index');
        Route::get('family-members/{familyMember}', [AdminFamilyMembersController::class, 'show'])->name('family-members.show');
    });
});

require __DIR__.'/settings.php';
