<?php

use Illuminate\Support\Facades\Route;
use Modules\Shifts\Http\Controllers\RotationsController;
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

// Rotations
Route::middleware(['auth', 'permission:create-rotations'])
    ->group(function () {
        Route::get('rotations/create', [RotationsController::class, 'create'])
            ->name('rotations.create');
        Route::post('rotations', [RotationsController::class, 'store'])
            ->name('rotations.store');
    });

Route::middleware(['auth', 'permission:assign-employees-to-rotation'])
    ->group(function () {
        Route::get('rotations/search-employees', [RotationsController::class, 'searchEmployees'])
            ->name('rotations.search-employees');
    });

Route::middleware(['auth', 'permission:view-rotations'])
    ->group(function () {
        Route::get('rotations', [RotationsController::class, 'index'])
            ->name('rotations.index');
        Route::get('rotations/{id}/preview', [RotationsController::class, 'preview'])
            ->name('rotations.preview');
        Route::get('rotations/{id}/timeline', [RotationsController::class, 'timeline'])
            ->name('rotations.timeline');
        Route::get('rotations/{id}/groups', [RotationsController::class, 'getGroups'])
            ->name('rotations.groups');
        Route::get('rotations/{id}', [RotationsController::class, 'show'])
            ->name('rotations.show');
    });

Route::middleware(['auth', 'permission:edit-rotations'])
    ->group(function () {
        Route::get('rotations/{id}/edit', [RotationsController::class, 'edit'])
            ->name('rotations.edit');
        Route::put('rotations/{id}', [RotationsController::class, 'update'])
            ->name('rotations.update');
        Route::post('rotations/{rotationId}/groups', [RotationsController::class, 'addGroup'])
            ->name('rotations.groups.add');
        Route::put('rotations/groups/{groupId}', [RotationsController::class, 'updateGroup'])
            ->name('rotations.groups.update');
        Route::delete('rotations/groups/{groupId}', [RotationsController::class, 'deleteGroup'])
            ->name('rotations.groups.delete');
    });

Route::middleware(['auth', 'permission:delete-rotations'])
    ->group(function () {
        Route::delete('rotations/{id}', [RotationsController::class, 'destroy'])
            ->name('rotations.destroy');
    });

// Rotation Assignments
Route::middleware(['auth', 'permission:assign-employees-to-rotation'])
    ->group(function () {
        Route::get('rotation-assignments', [RotationsController::class, 'assignPage'])
            ->name('rotations.assign');
        Route::get('rotation-assignments/bulk', [RotationsController::class, 'bulkAssignPage'])
            ->name('rotations.assign.bulk-page');
        Route::get('rotation-assignments/manage', [RotationsController::class, 'manageAssignments'])
            ->name('rotations.assign.manage');
    });

Route::middleware(['auth', 'permission:assign-employees-to-rotation'])
    ->group(function () {
        Route::post('rotation-assignments/assign', [RotationsController::class, 'assign'])
            ->name('rotations.assign.store');
        Route::post('rotation-assignments/bulk-assign', [RotationsController::class, 'bulkAssign'])
            ->name('rotations.assign.bulk');
        Route::post('rotation-assignments/transfer', [RotationsController::class, 'transfer'])
            ->name('rotations.assign.transfer');
        Route::post('rotation-assignments/unassign', [RotationsController::class, 'unassign'])
            ->name('rotations.assign.unassign');
        Route::post('rotation-assignments/bulk-unassign', [RotationsController::class, 'bulkUnassign'])
            ->name('rotations.assign.bulk-unassign');
        Route::post('rotation-assignments/bulk-transfer', [RotationsController::class, 'bulkTransfer'])
            ->name('rotations.assign.bulk-transfer');
    });

Route::middleware(['auth', 'permission:view-rotations'])
    ->group(function () {
        Route::get('rotations/{id}/employees', [RotationsController::class, 'getRotationEmployees'])
            ->name('rotations.employees');
    });
