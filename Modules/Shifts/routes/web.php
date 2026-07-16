<?php

use Illuminate\Support\Facades\Route;
use Modules\Shifts\Http\Controllers\ScheduleCalendarController;
use Modules\Shifts\Http\Controllers\SchedulesController;
use Modules\Shifts\Http\Controllers\ShiftCategoriesController;
use Modules\Shifts\Http\Controllers\ShiftCategoryAssignmentController;
use Modules\Shifts\Http\Controllers\ShiftsController;
use Modules\Shifts\Http\Controllers\SmartAbsenceController;
use Modules\Shifts\Http\Controllers\TimeSchedulesController;

Route::middleware(['auth', 'permission:view-shifts'])
    ->group(function () {
        Route::resource('shifts', ShiftsController::class)->names('shifts');
    });

// Shift Categories (T040)
Route::middleware(['auth', 'permission:view-shift-categories'])
    ->group(function () {
        Route::resource('shift-categories', ShiftCategoriesController::class)
            ->names('shift-categories');
        Route::get('shift-categories/{id}/schedule-preview', [ShiftCategoriesController::class, 'schedulePreview'])
            ->name('shift-categories.schedule-preview');
    });

// Time Schedules (T041)
Route::middleware(['auth', 'permission:view-time-schedules'])
    ->group(function () {
        Route::resource('time-schedules', TimeSchedulesController::class)
            ->names('time-schedules');
        Route::post('time-schedules/{id}/copy', [TimeSchedulesController::class, 'copy'])
            ->middleware('permission:create-time-schedules')
            ->name('time-schedules.copy');
    });

// Assignments (T057)
Route::middleware(['auth', 'permission:view-shift-categories'])
    ->group(function () {
        Route::get('shift-assignments', [ShiftCategoryAssignmentController::class, 'index'])
            ->name('shift-assignments.index');
        Route::get('shift-assignments/assign', [ShiftCategoryAssignmentController::class, 'create'])
            ->middleware('permission:assign-employees-to-category')
            ->name('shift-assignments.assign');
        Route::post('shift-assignments/assign', [ShiftCategoryAssignmentController::class, 'assign'])
            ->middleware('permission:assign-employees-to-category');
        Route::get('shift-assignments/bulk-assign', [ShiftCategoryAssignmentController::class, 'bulkCreate'])
            ->middleware('permission:assign-employees-to-category')
            ->name('shift-assignments.bulk-assign');
        Route::post('shift-assignments/bulk-assign', [ShiftCategoryAssignmentController::class, 'bulkAssign'])
            ->middleware('permission:assign-employees-to-category');
        Route::get('shift-assignments/search-employees', [ShiftCategoryAssignmentController::class, 'searchEmployees'])
            ->middleware('permission:assign-employees-to-category')
            ->name('shift-assignments.search-employees');
        Route::post('shift-assignments/transfer', [ShiftCategoryAssignmentController::class, 'transfer'])
            ->middleware('permission:assign-employees-to-category')
            ->name('shift-assignments.transfer');
        Route::post('shift-assignments/unassign', [ShiftCategoryAssignmentController::class, 'unassign'])
            ->middleware('permission:assign-employees-to-category')
            ->name('shift-assignments.unassign');
    });

// Schedules (Generated Monthly Schedules)
Route::middleware(['auth', 'permission:view-shift-categories'])
    ->group(function () {
        Route::get('schedules', [SchedulesController::class, 'index'])
            ->name('schedules.index');
        Route::get('schedules/{id}', [SchedulesController::class, 'show'])
            ->name('schedules.show');
        Route::get('schedules/{periodId}/entries', [SchedulesController::class, 'entries'])
            ->name('schedules.entries');
    });

Route::middleware(['auth', 'permission:create-shift-categories'])
    ->group(function () {
        Route::post('schedules', [SchedulesController::class, 'store'])
            ->name('schedules.store');
        Route::post('schedules/{id}/publish', [SchedulesController::class, 'publish'])
            ->name('schedules.publish');
        Route::post('schedules/{id}/regenerate', [SchedulesController::class, 'regenerate'])
            ->name('schedules.regenerate');
    });

// Calendar (T066)
Route::middleware(['auth', 'permission:view-shift-categories'])
    ->group(function () {
        Route::get('schedule-calendar/{employee}', [ScheduleCalendarController::class, 'employee'])
            ->name('schedule-calendar.employee');
        Route::get('schedule-calendar/department/{department}', [ScheduleCalendarController::class, 'department'])
            ->name('schedule-calendar.department');
    });

// Smart Absence (T067)
Route::middleware(['auth', 'permission:view-attendance-by-schedule'])
    ->group(function () {
        Route::get('smart-absence/daily', [SmartAbsenceController::class, 'daily'])
            ->name('smart-absence.daily');
        Route::get('smart-absence/monthly/{employee}', [SmartAbsenceController::class, 'monthly'])
            ->name('smart-absence.monthly');
    });

// Manager Routes (T075)
Route::middleware(['auth'])
    ->group(function () {
        Route::get('team-calendar', [ScheduleCalendarController::class, 'teamSchedule'])
            ->name('team-calendar');
        Route::get('smart-absence/team', [SmartAbsenceController::class, 'teamAbsence'])
            ->name('smart-absence.team');
    });

// Employee Self-Service (T080)
Route::middleware(['auth'])
    ->group(function () {
        Route::get('my-calendar', [ScheduleCalendarController::class, 'myCalendar'])
            ->name('my-calendar');
        Route::get('my-absence', [SmartAbsenceController::class, 'myAbsence'])
            ->name('my-absence');
    });
