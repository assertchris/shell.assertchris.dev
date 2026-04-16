<?php

namespace App\Http\Responders\Api;

use App\Http\Resources\MessageSummaryResource;
use App\Models\Account;
use App\Services\MailboxService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ListMessagesResponder
{
    public function __construct(public MailboxService $mailboxService) {}

    public function __invoke(Request $request, Account $account, string $folder): AnonymousResourceCollection
    {
        try {
            $imapMailbox = $this->mailboxService->connect($account);
            $imapFolder = $this->mailboxService->getFolder($imapMailbox, $folder);

            abort_unless($imapFolder !== null, 404, 'Folder not found.');

            $page = max(1, (int) $request->query('page', 1));
            $perPage = min(50, max(1, (int) $request->query('per_page', 15)));

            $paginator = $this->mailboxService->getMessages($imapFolder, $page, $perPage);

            return MessageSummaryResource::collection($paginator->items())
                ->additional([
                    'meta' => [
                        'current_page' => $paginator->currentPage(),
                        'last_page' => $paginator->lastPage(),
                        'per_page' => $paginator->perPage(),
                        'total' => $paginator->total(),
                    ],
                ]);
        } catch (\Throwable $e) {
            if ($e instanceof HttpException) {
                throw $e;
            }

            abort(503, 'Unable to connect to mailbox.');
        }
    }
}
