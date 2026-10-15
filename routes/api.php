<?php

use App\Http\Responders\Api\CreateCalendarEntryResponder;
use App\Http\Responders\Api\DownloadAttachmentResponder;
use App\Http\Responders\Api\GitHub\GetIssueCommentsResponder;
use App\Http\Responders\Api\GitHub\GetIssueResponder;
use App\Http\Responders\Api\GitHub\GetNotificationResponder;
use App\Http\Responders\Api\GitHub\GetPullCommitsResponder;
use App\Http\Responders\Api\GitHub\GetPullFilesResponder;
use App\Http\Responders\Api\GitHub\GetPullRequestedReviewersResponder;
use App\Http\Responders\Api\GitHub\GetPullResponder;
use App\Http\Responders\Api\GitHub\GetPullReviewCommentsResponder;
use App\Http\Responders\Api\GitHub\GetPullReviewsResponder;
use App\Http\Responders\Api\GitHub\GetRepoResponder;
use App\Http\Responders\Api\GitHub\ListIssuesResponder;
use App\Http\Responders\Api\GitHub\ListNotificationsResponder;
use App\Http\Responders\Api\GitHub\ListPullsResponder;
use App\Http\Responders\Api\GitHub\ListRepoIssuesResponder;
use App\Http\Responders\Api\GitHub\ListReposResponder;
use App\Http\Responders\Api\GitHub\ListUserIssuesResponder;
use App\Http\Responders\Api\GitHub\SearchIssuesResponder;
use App\Http\Responders\Api\GitHub\SearchPullsResponder;
// Jira
use App\Http\Responders\Api\Jira\AddCommentResponder;
use App\Http\Responders\Api\Jira\AddWorklogResponder;
use App\Http\Responders\Api\Jira\AssignIssueResponder;
use App\Http\Responders\Api\Jira\CreateIssueResponder;
use App\Http\Responders\Api\Jira\DeleteCommentResponder;
use App\Http\Responders\Api\Jira\DeleteIssueResponder;
use App\Http\Responders\Api\Jira\DeleteWorklogResponder;
use App\Http\Responders\Api\Jira\GetBoardResponder;
use App\Http\Responders\Api\Jira\GetFilterResponder;
use App\Http\Responders\Api\Jira\GetIssueAttachmentsResponder;
use App\Http\Responders\Api\Jira\GetIssueChangelogResponder;
use App\Http\Responders\Api\Jira\GetIssueCommentsResponder as JiraGetIssueCommentsResponder;
use App\Http\Responders\Api\Jira\GetIssueResponder as JiraGetIssueResponder;
use App\Http\Responders\Api\Jira\GetIssueTransitionsResponder;
use App\Http\Responders\Api\Jira\GetIssueWorklogsResponder;
use App\Http\Responders\Api\Jira\GetMyselfResponder;
use App\Http\Responders\Api\Jira\GetProjectResponder;
use App\Http\Responders\Api\Jira\GetSprintResponder;
use App\Http\Responders\Api\Jira\ListBoardsResponder;
use App\Http\Responders\Api\Jira\ListDashboardsResponder;
use App\Http\Responders\Api\Jira\ListFiltersResponder;
use App\Http\Responders\Api\Jira\ListMyAssignedIssuesResponder;
use App\Http\Responders\Api\Jira\ListMyMentionsResponder;
use App\Http\Responders\Api\Jira\ListMyRecentActivityResponder;
use App\Http\Responders\Api\Jira\ListMyReportedIssuesResponder;
use App\Http\Responders\Api\Jira\ListMyWatchedIssuesResponder;
use App\Http\Responders\Api\Jira\ListProjectComponentsResponder;
use App\Http\Responders\Api\Jira\ListProjectsResponder;
use App\Http\Responders\Api\Jira\ListProjectVersionsResponder;
use App\Http\Responders\Api\Jira\ListSprintIssuesResponder;
use App\Http\Responders\Api\Jira\ListSprintsResponder;
use App\Http\Responders\Api\Jira\MoveIssuesToSprintResponder;
use App\Http\Responders\Api\Jira\SearchIssuesResponder as JiraSearchIssuesResponder;
use App\Http\Responders\Api\Jira\TransitionIssueResponder;
use App\Http\Responders\Api\Jira\UpdateCommentResponder;
use App\Http\Responders\Api\Jira\UpdateIssueResponder;
use App\Http\Responders\Api\Jira\UpdateWorklogResponder;
// Laravel Cloud
use App\Http\Responders\Api\LaravelCloud\GetApplicationResponder;
use App\Http\Responders\Api\LaravelCloud\GetCacheMetricsResponder;
use App\Http\Responders\Api\LaravelCloud\GetCacheResponder;
use App\Http\Responders\Api\LaravelCloud\GetCommandResponder;
use App\Http\Responders\Api\LaravelCloud\GetDatabaseMetricsResponder;
use App\Http\Responders\Api\LaravelCloud\GetDatabaseResponder;
use App\Http\Responders\Api\LaravelCloud\GetDeploymentLogsResponder;
use App\Http\Responders\Api\LaravelCloud\GetDeploymentResponder;
use App\Http\Responders\Api\LaravelCloud\GetEnvironmentMetricsResponder;
use App\Http\Responders\Api\LaravelCloud\GetEnvironmentResponder;
use App\Http\Responders\Api\LaravelCloud\GetInstanceResponder;
use App\Http\Responders\Api\LaravelCloud\GetOrganizationResponder;
use App\Http\Responders\Api\LaravelCloud\GetUsageResponder;
use App\Http\Responders\Api\LaravelCloud\ListApplicationsResponder;
use App\Http\Responders\Api\LaravelCloud\ListCachesResponder;
use App\Http\Responders\Api\LaravelCloud\ListCommandsResponder;
use App\Http\Responders\Api\LaravelCloud\ListDatabasesResponder;
use App\Http\Responders\Api\LaravelCloud\ListDeploymentsResponder;
use App\Http\Responders\Api\LaravelCloud\ListEnvironmentLogsResponder;
use App\Http\Responders\Api\LaravelCloud\ListEnvironmentsResponder;
use App\Http\Responders\Api\LaravelCloud\ListFailedJobsResponder;
use App\Http\Responders\Api\LaravelCloud\ListInstancesResponder;
use App\Http\Responders\Api\LaravelCloud\ListRegionsResponder;
use App\Http\Responders\Api\LaravelCloud\PauseQueueResponder;
use App\Http\Responders\Api\LaravelCloud\PurgeEdgeCacheResponder;
use App\Http\Responders\Api\LaravelCloud\PurgeQueueResponder;
use App\Http\Responders\Api\LaravelCloud\ResumeQueueResponder;
use App\Http\Responders\Api\LaravelCloud\RetryFailedJobResponder;
use App\Http\Responders\Api\LaravelCloud\RunCommandResponder;
use App\Http\Responders\Api\LaravelCloud\StartEnvironmentResponder;
use App\Http\Responders\Api\LaravelCloud\StopEnvironmentResponder;
use App\Http\Responders\Api\ListAccountsResponder;
use App\Http\Responders\Api\ListAttachmentsResponder;
use App\Http\Responders\Api\ListCalendarEntriesResponder;
use App\Http\Responders\Api\ListFoldersResponder;
use App\Http\Responders\Api\ListMessagesResponder;
use App\Http\Responders\Api\ListSlackChannelsResponder;
use App\Http\Responders\Api\ListSlackMessagesResponder;
use App\Http\Responders\Api\ListSlackUsersResponder;
use App\Http\Responders\Api\OpenSlackConversationResponder;
use App\Http\Responders\Api\SendSlackMessageResponder;
use App\Http\Responders\Api\ShowCalendarEntryResponder;
use App\Http\Responders\Api\ShowMessageResponder;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'account.owner'])->group(function () {
    Route::get('/status', fn () => response()->json(['status' => 'ok']));

    Route::get('/accounts', ListAccountsResponder::class);

    Route::middleware('service.connected:mail')->group(function () {
        Route::get('/{account}/mailbox', ListFoldersResponder::class);
        Route::get('/{account}/mailbox/{folder}/messages', ListMessagesResponder::class);
        Route::get('/{account}/mailbox/{folder}/messages/{uid}', ShowMessageResponder::class);
        Route::get('/{account}/mailbox/{folder}/messages/{uid}/attachments', ListAttachmentsResponder::class);
        Route::get('/{account}/mailbox/{folder}/messages/{uid}/attachments/{index}', DownloadAttachmentResponder::class);
    });

    Route::middleware('service.connected:calendar')->group(function () {
        Route::get('/{account}/calendar', ListCalendarEntriesResponder::class)
            ->middleware('permission:google_calendar_read');
        Route::get('/{account}/calendar/{eventId}', ShowCalendarEntryResponder::class)
            ->middleware('permission:google_calendar_read');
        Route::post('/{account}/calendar', CreateCalendarEntryResponder::class)
            ->middleware('permission:google_calendar_write');
    });

    Route::prefix('{account}/github')->middleware('service.connected:github')->group(function () {
        Route::get('/repos', ListReposResponder::class);
        Route::get('/repos/{owner}/{repo}', GetRepoResponder::class);
        Route::get('/repos/{owner}/{repo}/pulls', ListPullsResponder::class);
        Route::get('/repos/{owner}/{repo}/pulls/{number}', GetPullResponder::class);
        Route::get('/repos/{owner}/{repo}/pulls/{number}/files', GetPullFilesResponder::class);
        Route::get('/repos/{owner}/{repo}/pulls/{number}/commits', GetPullCommitsResponder::class);
        Route::get('/repos/{owner}/{repo}/pulls/{number}/reviews', GetPullReviewsResponder::class);
        Route::get('/repos/{owner}/{repo}/pulls/{number}/reviews/{review_id}/comments', GetPullReviewCommentsResponder::class);
        Route::get('/repos/{owner}/{repo}/pulls/{number}/requested-reviewers', GetPullRequestedReviewersResponder::class);
        Route::get('/search/pulls', SearchPullsResponder::class);
        Route::get('/search/issues', SearchIssuesResponder::class);
        Route::get('/issues', ListIssuesResponder::class);
        Route::get('/user/issues', ListUserIssuesResponder::class);
        Route::get('/repos/{owner}/{repo}/issues', ListRepoIssuesResponder::class);
        Route::get('/repos/{owner}/{repo}/issues/{number}', GetIssueResponder::class);
        Route::get('/repos/{owner}/{repo}/issues/{number}/comments', GetIssueCommentsResponder::class);
        Route::get('/notifications', ListNotificationsResponder::class);
        Route::get('/notifications/{thread_id}', GetNotificationResponder::class);
    });

    Route::prefix('{account}/jira')->middleware('service.connected:jira')->group(function () {
        // Projects
        Route::get('/projects', ListProjectsResponder::class);
        Route::get('/projects/{key}', GetProjectResponder::class);
        Route::get('/projects/{key}/components', ListProjectComponentsResponder::class);
        Route::get('/projects/{key}/versions', ListProjectVersionsResponder::class);

        // Issues (read)
        Route::get('/issues/search', JiraSearchIssuesResponder::class);
        Route::get('/issues/{key}', JiraGetIssueResponder::class);
        Route::get('/issues/{key}/comments', JiraGetIssueCommentsResponder::class);
        Route::get('/issues/{key}/worklogs', GetIssueWorklogsResponder::class);
        Route::get('/issues/{key}/transitions', GetIssueTransitionsResponder::class);
        Route::get('/issues/{key}/changelog', GetIssueChangelogResponder::class);
        Route::get('/issues/{key}/attachments', GetIssueAttachmentsResponder::class);

        // Issues (write)
        Route::post('/issues', CreateIssueResponder::class);
        Route::put('/issues/{key}', UpdateIssueResponder::class);
        Route::delete('/issues/{key}', DeleteIssueResponder::class);
        Route::post('/issues/{key}/transitions', TransitionIssueResponder::class);
        Route::put('/issues/{key}/assignee', AssignIssueResponder::class);

        // Comments
        Route::post('/issues/{key}/comments', AddCommentResponder::class);
        Route::put('/issues/{key}/comments/{commentId}', UpdateCommentResponder::class);
        Route::delete('/issues/{key}/comments/{commentId}', DeleteCommentResponder::class);

        // Worklogs
        Route::post('/issues/{key}/worklogs', AddWorklogResponder::class);
        Route::put('/issues/{key}/worklogs/{worklogId}', UpdateWorklogResponder::class);
        Route::delete('/issues/{key}/worklogs/{worklogId}', DeleteWorklogResponder::class);

        // Personal views
        Route::get('/user/issues', ListMyAssignedIssuesResponder::class);
        Route::get('/user/reported', ListMyReportedIssuesResponder::class);
        Route::get('/user/watching', ListMyWatchedIssuesResponder::class);
        Route::get('/user/mentions', ListMyMentionsResponder::class);
        Route::get('/user/activity', ListMyRecentActivityResponder::class);

        // Boards & Sprints
        Route::get('/boards', ListBoardsResponder::class);
        Route::get('/boards/{boardId}', GetBoardResponder::class);
        Route::get('/boards/{boardId}/sprints', ListSprintsResponder::class);
        Route::get('/sprints/{sprintId}', GetSprintResponder::class);
        Route::get('/sprints/{sprintId}/issues', ListSprintIssuesResponder::class);
        Route::post('/sprints/{sprintId}/issues', MoveIssuesToSprintResponder::class);

        // Users
        Route::get('/myself', GetMyselfResponder::class);

        // Filters & Dashboards
        Route::get('/filters', ListFiltersResponder::class);
        Route::get('/filters/{id}', GetFilterResponder::class);
        Route::get('/dashboards', ListDashboardsResponder::class);
    });

    Route::prefix('{account}/laravel-cloud')->middleware('service.connected:laravel-cloud')->group(function () {
        // Applications
        Route::get('/applications', ListApplicationsResponder::class);
        Route::get('/applications/{application}', GetApplicationResponder::class);
        Route::get('/applications/{application}/environments', ListEnvironmentsResponder::class);

        // Environments
        Route::get('/environments/{environment}', GetEnvironmentResponder::class);
        Route::get('/environments/{environment}/logs', ListEnvironmentLogsResponder::class);
        Route::get('/environments/{environment}/metrics', GetEnvironmentMetricsResponder::class);
        Route::get('/environments/{environment}/deployments', ListDeploymentsResponder::class);
        Route::get('/environments/{environment}/instances', ListInstancesResponder::class);
        Route::get('/environments/{environment}/commands', ListCommandsResponder::class);
        Route::post('/environments/{environment}/commands', RunCommandResponder::class);
        Route::post('/environments/{environment}/purge-cache', PurgeEdgeCacheResponder::class);
        Route::post('/environments/{environment}/start', StartEnvironmentResponder::class);
        Route::post('/environments/{environment}/stop', StopEnvironmentResponder::class);

        // Deployments
        Route::get('/deployments/{deployment}', GetDeploymentResponder::class);
        Route::get('/deployments/{deployment}/logs', GetDeploymentLogsResponder::class);

        // Instances
        Route::get('/instances/{instance}', GetInstanceResponder::class);
        Route::get('/instances/{instance}/failed-jobs', ListFailedJobsResponder::class);
        Route::post('/instances/{instance}/failed-jobs/{job}/retry', RetryFailedJobResponder::class);
        Route::post('/instances/{instance}/pause', PauseQueueResponder::class);
        Route::post('/instances/{instance}/resume', ResumeQueueResponder::class);
        Route::post('/instances/{instance}/purge', PurgeQueueResponder::class);

        // Commands
        Route::get('/commands/{command}', GetCommandResponder::class);

        // Databases
        Route::get('/databases', ListDatabasesResponder::class);
        Route::get('/databases/{database}', GetDatabaseResponder::class);
        Route::get('/databases/{database}/metrics', GetDatabaseMetricsResponder::class);

        // Caches
        Route::get('/caches', ListCachesResponder::class);
        Route::get('/caches/{cache}', GetCacheResponder::class);
        Route::get('/caches/{cache}/metrics', GetCacheMetricsResponder::class);

        // Meta
        Route::get('/organization', GetOrganizationResponder::class);
        Route::get('/regions', ListRegionsResponder::class);
        Route::get('/usage', GetUsageResponder::class);
    });

    Route::middleware('service.connected:slack')->group(function () {
        Route::get('/slack/{account}/channels', ListSlackChannelsResponder::class)
            ->middleware('permission:slack_read');
        Route::get('/slack/{account}/channels/{channel}/messages', ListSlackMessagesResponder::class)
            ->middleware('permission:slack_read');
        Route::post('/slack/{account}/channels/{channel}/messages', SendSlackMessageResponder::class)
            ->middleware('permission:slack_write');
        Route::get('/slack/{account}/users', ListSlackUsersResponder::class)
            ->middleware('permission:slack_read');
        Route::post('/slack/{account}/conversations/open', OpenSlackConversationResponder::class)
            ->middleware('permission:slack_write');
    });
});
