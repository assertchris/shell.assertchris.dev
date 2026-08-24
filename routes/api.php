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
