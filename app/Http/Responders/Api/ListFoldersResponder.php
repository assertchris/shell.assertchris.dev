<?php

namespace App\Http\Responders\Api;

use App\Http\Resources\FolderResource;
use App\Models\Account;
use App\Services\MailboxService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListFoldersResponder
{
    public function __construct(public MailboxService $mailboxService) {}

    public function __invoke(Request $request, Account $account): AnonymousResourceCollection
    {
        abort_unless($account->user_id === $request->user()->id, 404);
        abort_unless($account->mail_used, 404);

        try {
            $imapMailbox = $this->mailboxService->connect($account);
            $folders = $this->mailboxService->getFolders($imapMailbox);

            return FolderResource::collection($folders);
        } catch (\Throwable) {
            abort(503, 'Unable to connect to mailbox.');
        }
    }
}
