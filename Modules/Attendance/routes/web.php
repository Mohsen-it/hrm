<?php

use Illuminate\Support\Facades\Route;
use Modules\Attendance\Http\Controllers\AttendanceGroupsController;
use Modules\Attendance\Http\Controllers\AttendanceSessionsController;
use Modules\Attendance\Http\Controllers\AttendanceShiftsController;
use Modules\Attendance\Http\Controllers\DailySummariesController;
use Modules\Attendance\Http\Controllers\GroupSchedulesController;
use Modules\Attendance\Http\Controllers\LiveAttendanceController;
use Modules\Attendance\Http\Controllers\MonthlyReportController;
use Modules\Attendance\Http\Controllers\RawLogsController;
use Modules\Attendance\Http\Controllers\ReportsController;
use Modules\Attendance\Http\Controllers\YearlyReportController;

/*
| Routes for the Attendance module.
|
| Naming convention:
|   - `attendance.sessions.*`         : per-session CRUD
|   - `attendance.daily-summaries.*`  : per-day roll-up management
|   - `attendance.raw-logs.*`         : raw device log management
|   - `attendance.reports.*`          : ad-hoc reports landing
|   - `attendance.reports.user`       : per-user report
|   - `attendance.reports.monthly`    : monthly report
|   - `attendance.reports.yearly`     : yearly report
|   - `attendance.live.*`             : real-time monitoring
|
| Permission middleware:
|   - view-attendance   : everything that reads
|   - create-attendance : store / process-all
|   - edit-attendance   : update / recalculate / markProcessed / runDailyScan
|   - delete-attendance : destroy
*/

Route::middleware(['auth'])->group(function () {
    // Live monitoring
    Route::middleware('permission:view-attendance')->prefix('attendance/live')->name('attendance.live.')->group(function () {
        Route::get('/', [LiveAttendanceController::class, 'index'])->name('index');
        Route::get('snapshot', [LiveAttendanceController::class, 'snapshot'])->name('snapshot');
        Route::get('punch-feed', [LiveAttendanceController::class, 'punchFeed'])->name('punch-feed');
        Route::post('daily-scan', [LiveAttendanceController::class, 'runDailyScan'])->name('daily-scan');
    });

    // Reports
    Route::middleware('permission:view-attendance')->prefix('attendance/reports')->name('attendance.reports.')->group(function () {
        Route::get('/', [ReportsController::class, 'index'])->name('index');
        Route::get('user/{user}', [ReportsController::class, 'userReport'])->name('user');
        Route::get('monthly', [MonthlyReportController::class, 'index'])->name('monthly');
        Route::get('yearly', [YearlyReportController::class, 'index'])->name('yearly');
    });

    // Sessions
    Route::middleware('permission:view-attendance')->prefix('attendance/sessions')->name('attendance.sessions.')->group(function () {
        Route::get('/', [AttendanceSessionsController::class, 'index'])->name('index');
        Route::get('create', [AttendanceSessionsController::class, 'create'])
            ->middleware('permission:create-attendance')
            ->name('create');
        Route::post('/', [AttendanceSessionsController::class, 'store'])
            ->middleware('permission:create-attendance')
            ->name('store');
        Route::get('{id}', [AttendanceSessionsController::class, 'show'])->name('show');
        Route::get('{id}/edit', [AttendanceSessionsController::class, 'edit'])
            ->middleware('permission:edit-attendance')
            ->name('edit');
        Route::put('{id}', [AttendanceSessionsController::class, 'update'])
            ->middleware('permission:edit-attendance')
            ->name('update');
        Route::delete('{id}', [AttendanceSessionsController::class, 'destroy'])
            ->middleware('permission:delete-attendance')
            ->name('destroy');
    });

    // Daily summaries
    Route::middleware('permission:view-attendance')->prefix('attendance/daily-summaries')->name('attendance.daily-summaries.')->group(function () {
        Route::get('/', [DailySummariesController::class, 'index'])->name('index');
        Route::get('{id}', [DailySummariesController::class, 'show'])->name('show');
        Route::put('{id}', [DailySummariesController::class, 'update'])
            ->middleware('permission:edit-attendance')
            ->name('update');
        Route::post('recalculate', [DailySummariesController::class, 'recalculate'])
            ->middleware('permission:edit-attendance')
            ->name('recalculate');
        Route::post('recalculate-range', [DailySummariesController::class, 'recalculateRange'])
            ->middleware('permission:edit-attendance')
            ->name('recalculate-range');
    });

    // Raw logs
    Route::middleware('permission:view-attendance')->prefix('attendance/raw-logs')->name('attendance.raw-logs.')->group(function () {
        Route::get('/', [RawLogsController::class, 'index'])->name('index');
        Route::get('{id}', [RawLogsController::class, 'show'])->name('show');
        Route::post('/', [RawLogsController::class, 'store'])
            ->middleware('permission:create-attendance')
            ->name('store');
        Route::post('process-all', [RawLogsController::class, 'processAll'])
            ->middleware('permission:edit-attendance')
            ->name('process-all');
        Route::post('{id}/mark-processed', [RawLogsController::class, 'markProcessed'])
            ->middleware('permission:edit-attendance')
            ->name('mark-processed');
        Route::delete('{id}', [RawLogsController::class, 'destroy'])
            ->middleware('permission:delete-attendance')
            ->name('destroy');
    });

    // Attendance Groups
    Route::middleware('permission:view-attendance-groups')->prefix('attendance/groups')->name('attendance.groups.')->group(function () {
        Route::get('/', [AttendanceGroupsController::class, 'index'])->name('index');
        Route::get('create', [AttendanceGroupsController::class, 'create'])->name('create');
        Route::post('/', [AttendanceGroupsController::class, 'store'])->name('store');
        Route::get('{id}', [AttendanceGroupsController::class, 'show'])->name('show');
        Route::get('{id}/edit', [AttendanceGroupsController::class, 'edit'])->name('edit');
        Route::put('{id}', [AttendanceGroupsController::class, 'update'])->name('update');
        Route::delete('{id}', [AttendanceGroupsController::class, 'destroy'])->name('destroy');
        Route::post('{groupId}/employees', [AttendanceGroupsController::class, 'assignEmployee'])->name('assign-employee');
        Route::delete('{groupId}/employees/{employeeId}', [AttendanceGroupsController::class, 'removeEmployee'])->name('remove-employee');
        Route::get('{groupId}/employees', [AttendanceGroupsController::class, 'employees'])->name('employees');
    });

    // Attendance Shifts
    Route::middleware('permission:view-attendance-shifts')->prefix('attendance/shifts')->name('attendance.shifts.')->group(function () {
        Route::get('/', [AttendanceShiftsController::class, 'index'])->name('index');
        Route::get('create', [AttendanceShiftsController::class, 'create'])->name('create');
        Route::post('/', [AttendanceShiftsController::class, 'store'])->name('store');
        Route::get('{id}', [AttendanceShiftsController::class, 'show'])->name('show');
        Route::get('{id}/edit', [AttendanceShiftsController::class, 'edit'])->name('edit');
        Route::put('{id}', [AttendanceShiftsController::class, 'update'])->name('update');
        Route::delete('{id}', [AttendanceShiftsController::class, 'destroy'])->name('destroy');
    });

    // Group Schedules
    Route::middleware('permission:view-group-schedules')->prefix('attendance/group-schedules')->name('attendance.group-schedules.')->group(function () {
        Route::get('/', [GroupSchedulesController::class, 'index'])->name('index');
        Route::get('create', [GroupSchedulesController::class, 'create'])->name('create');
        Route::post('/', [GroupSchedulesController::class, 'store'])->name('store');
        Route::get('{id}', [GroupSchedulesController::class, 'show'])->name('show');
        Route::get('{id}/edit', [GroupSchedulesController::class, 'edit'])->name('edit');
        Route::put('{id}', [GroupSchedulesController::class, 'update'])->name('update');
        Route::delete('{id}', [GroupSchedulesController::class, 'destroy'])->name('destroy');
    });
});
