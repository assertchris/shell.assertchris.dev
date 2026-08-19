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
