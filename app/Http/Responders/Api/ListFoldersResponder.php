<?php

namespace App\Http\Responders\Api;

use App\Http\Resources\FolderResource;
use App\Models\Mailbox;
use App\Services\MailboxService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListFoldersResponder
{
    public function __construct(public MailboxService $mailboxService) {}

    public function __invoke(Request $request, Mailbox $mailbox): AnonymousResourceCollection
    {
        abort_unless($mailbox->user_id === $request->user()->id, 404);

        try {
            $imapMailbox = $this->mailboxService->connect($mailbox);
            $folders = $this->mailboxService->getFolders($imapMailbox);

            return FolderResource::collection($folders);
        } catch (\Throwable) {
            abort(503, 'Unable to connect to mailbox.');
        }
    }
}
