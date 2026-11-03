<?php

use App\Http\Controllers\Api\V1\Admin\AdminDepartmentController;
use App\Http\Controllers\Api\V1\Admin\AdminUserController;
use App\Http\Controllers\Api\V1\Admin\AdminWorkflowController;
use App\Http\Controllers\Api\V1\Admin\AdminWorkflowDefinitionController;
use App\Http\Controllers\Api\V1\Admin\AdminWorkTypeController;
use App\Http\Controllers\Api\V1\DepartmentActivityExportController;
use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\ResetPasswordController;
use App\Http\Controllers\Api\V1\Auth\UserController;
use App\Http\Controllers\Api\V1\DepartmentActivityReportController;
use App\Http\Controllers\Api\V1\DepartmentDashboardController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\ManagementDashboardController;
use App\Http\Controllers\Api\V1\MeetingBulkActionsController;
use App\Http\Controllers\Api\V1\MeetingController;
use App\Http\Controllers\Api\V1\MyWorkController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ProjectBoardController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\ProjectTaskController;
use App\Http\Controllers\Api\V1\WorkItemActivityController;
use App\Http\Controllers\Api\V1\WorkItemAttachmentController;
use App\Http\Controllers\Api\V1\WorkItemAssignController;
use App\Http\Controllers\Api\V1\WorkItemCommentController;
use App\Http\Controllers\Api\V1\WorkItemController;
use App\Http\Controllers\Api\V1\WorkItemTransitionController;
use App\Http\Controllers\Api\V1\WorkItemAssigneeController;
use App\Http\Controllers\Api\V1\WorkTypeController;
use App\Http\Controllers\Api\V1\WorkspaceBoardController;
use App\Http\Controllers\Api\V1\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', HealthController::class);

    Route::post('/auth/login', [LoginController::class, 'store'])->middleware('throttle:login');
    Route::post('/auth/forgot-password', [ForgotPasswordController::class, 'store'])->middleware('throttle:password-reset');
    Route::post('/auth/reset-password', [ResetPasswordController::class, 'store'])->middleware('throttle:password-reset');
    Route::post('/auth/logout', [LogoutController::class, 'destroy'])->middleware('auth:sanctum');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/auth/user', UserController::class);

        Route::get('/work-types', [WorkTypeController::class, 'index']);
        Route::get('/my-work', MyWorkController::class);

        Route::get('/work-items', [WorkItemController::class, 'index']);
        Route::post('/work-items', [WorkItemController::class, 'store']);
        Route::get('/work-items/{workItem}', [WorkItemController::class, 'show']);
        Route::patch('/work-items/{workItem}', [WorkItemController::class, 'update']);

        Route::post('/work-items/{workItem}/transitions', [WorkItemTransitionController::class, 'store']);
        Route::post('/work-items/{workItem}/assign', [WorkItemAssignController::class, 'store']);
        Route::get('/work-items/{workItem}/assignees', [WorkItemAssigneeController::class, 'index']);
        Route::get('/work-items/{workItem}/comments', [WorkItemCommentController::class, 'index']);
        Route::post('/work-items/{workItem}/comments', [WorkItemCommentController::class, 'store']);
        Route::get('/work-items/{workItem}/activity', [WorkItemActivityController::class, 'index']);
        Route::get('/work-items/{workItem}/attachments', [WorkItemAttachmentController::class, 'index']);
        Route::post('/work-items/{workItem}/attachments', [WorkItemAttachmentController::class, 'store']);
        Route::get('/work-items/{workItem}/attachments/{attachment}/download', [WorkItemAttachmentController::class, 'download']);

        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);

        Route::get('/workspaces', [WorkspaceController::class, 'index']);
        Route::get('/workspaces/{workspace:slug}/board', WorkspaceBoardController::class);

        Route::get('/dashboards/management', ManagementDashboardController::class);
        Route::get('/dashboards/department/{department}', DepartmentDashboardController::class);
        Route::get('/reports/department-activity', DepartmentActivityReportController::class);
        Route::get('/reports/department-activity/export', DepartmentActivityExportController::class);

        Route::get('/meetings', [MeetingController::class, 'index']);
        Route::post('/meetings', [MeetingController::class, 'store']);
        Route::get('/meetings/{meeting}', [MeetingController::class, 'show']);
        Route::patch('/meetings/{meeting}', [MeetingController::class, 'update']);
        Route::delete('/meetings/{meeting}', [MeetingController::class, 'destroy']);
        Route::post('/meetings/{meeting}/actions/bulk', [MeetingBulkActionsController::class, 'store']);

        Route::get('/projects', [ProjectController::class, 'index']);
        Route::post('/projects', [ProjectController::class, 'store']);
        Route::get('/projects/{project}', [ProjectController::class, 'show']);
        Route::patch('/projects/{project}', [ProjectController::class, 'update']);
        Route::delete('/projects/{project}', [ProjectController::class, 'destroy']);
        Route::get('/projects/{project}/board', ProjectBoardController::class);
        Route::post('/projects/{project}/tasks', [ProjectTaskController::class, 'store']);

        Route::get('/admin/users', [AdminUserController::class, 'index']);
        Route::post('/admin/users', [AdminUserController::class, 'store']);
        Route::patch('/admin/users/{user}', [AdminUserController::class, 'update']);
        Route::get('/admin/departments', [AdminDepartmentController::class, 'index']);
        Route::post('/admin/departments', [AdminDepartmentController::class, 'store']);
        Route::patch('/admin/departments/{department}', [AdminDepartmentController::class, 'update']);
        Route::get('/admin/work-types', [AdminWorkTypeController::class, 'index']);
        Route::patch('/admin/work-types/{workType}', [AdminWorkTypeController::class, 'update']);
        Route::get('/admin/workflows', [AdminWorkflowController::class, 'index']);
        Route::post('/admin/workflows', [AdminWorkflowController::class, 'store']);
        Route::get('/admin/workflows/{workflow}', [AdminWorkflowController::class, 'show']);
        Route::patch('/admin/workflows/{workflow}', [AdminWorkflowController::class, 'update']);
        Route::put('/admin/workflows/{workflow}/definition', [AdminWorkflowDefinitionController::class, 'update']);
        Route::delete('/admin/workflows/{workflow}', [AdminWorkflowController::class, 'destroy']);
    });
});
