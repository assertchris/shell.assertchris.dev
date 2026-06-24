<?php

namespace App\Http\Responders\Api;

use App\Models\Account;
use App\Services\MailboxService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DownloadAttachmentResponder
{
    public function __construct(public MailboxService $mailboxService) {}

    public function __invoke(Request $request, Account $account, string $folder, int $uid, int $index): StreamedResponse
    {
        try {
            $imapMailbox = $this->mailboxService->connect($account);
            $imapFolder = $this->mailboxService->getFolder($imapMailbox, $folder);

            abort_unless($imapFolder !== null, 404, 'Folder not found.');

            $message = $this->mailboxService->getMessage($imapFolder, $uid);

            abort_unless($message !== null, 404, 'Message not found.');

            $attachments = $message->attachments();

            abort_unless(isset($attachments[$index]), 404, 'Attachment not found.');

            $attachment = $attachments[$index];

            return response()->streamDownload(
                callback: fn () => print $attachment->contents(),
                name: $attachment->filename() ?? "attachment-{$index}",
                headers: ['Content-Type' => $attachment->contentType()],
                disposition: 'attachment',
            );
        } catch (\Throwable $e) {
            if ($e instanceof HttpException) {
                throw $e;
            }

            abort(503, 'Unable to connect to mailbox.');
        }
    }
}
