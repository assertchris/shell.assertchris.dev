<?php

namespace App\Http\Responders\Api;

use App\Http\Resources\MessageResource;
use App\Models\Account;
use App\Services\MailboxService;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ShowMessageResponder
{
    public function __construct(public MailboxService $mailboxService) {}

    public function __invoke(Request $request, Account $account, string $folder, int $uid): MessageResource
    {
        try {
            $imapMailbox = $this->mailboxService->connect($account);
            $imapFolder = $this->mailboxService->getFolder($imapMailbox, $folder);

            abort_unless($imapFolder !== null, 404, 'Folder not found.');

            $message = $this->mailboxService->getMessage($imapFolder, $uid);

            abort_unless($message !== null, 404, 'Message not found.');

            return new MessageResource($message);
        } catch (\Throwable $e) {
            if ($e instanceof HttpException) {
                throw $e;
            }

            abort(503, 'Unable to connect to mailbox.');
        }
    }
}
