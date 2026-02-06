<?php

namespace App\Services;

use App\Models\Mailbox;
use DirectoryTree\ImapEngine\Collections\FolderCollection;
use DirectoryTree\ImapEngine\FolderInterface;
use DirectoryTree\ImapEngine\Laravel\Facades\Imap;
use DirectoryTree\ImapEngine\MailboxInterface;
use DirectoryTree\ImapEngine\MessageInterface;
use DirectoryTree\ImapEngine\Pagination\LengthAwarePaginator;

class MailboxService
{
    public function connect(Mailbox $mailbox): MailboxInterface
    {
        $imapMailbox = Imap::build($mailbox->toImapConfig());
        $imapMailbox->connect();

        return $imapMailbox;
    }

    public function getFolders(MailboxInterface $mailbox): FolderCollection
    {
        return $mailbox->folders()->get();
    }

    public function getFolder(MailboxInterface $mailbox, string $path): ?FolderInterface
    {
        return $mailbox->folders()->find($path);
    }

    /**
     * @return LengthAwarePaginator<int, MessageInterface>
     */
    public function getMessages(FolderInterface $folder, int $page = 1, int $perPage = 15): LengthAwarePaginator
    {
        return $folder->messages()
            ->leaveUnread()
            ->withHeaders()
            ->withBodyStructure()
            ->newest()
            ->paginate($perPage, $page);
    }

    public function getMessage(FolderInterface $folder, int $uid): ?MessageInterface
    {
        return $folder->messages()
            ->leaveUnread()
            ->withHeaders()
            ->withBody()
            ->find($uid);
    }
}
